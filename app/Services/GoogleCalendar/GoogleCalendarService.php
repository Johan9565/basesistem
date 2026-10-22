<?php

namespace App\Services\GoogleCalendar;

use App\Models\CalendarEvent;
use App\Models\GoogleCalendarSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    /**
     * Get or initialize settings for the user.
     */
    public function getSettings(User $user): GoogleCalendarSetting
    {
        $userId = (string) $user->id;

        $setting = GoogleCalendarSetting::where('user_id', $userId)->first();

        if (!$setting) {
            $setting = GoogleCalendarSetting::create([
                'user_id'              => $userId,
                'calendar_id'          => (string) env('GOOGLE_CALENDAR_ID', 'primary'),
                'service_account_json' => '',
                'timezone'             => (string) env('GOOGLE_CALENDAR_TIMEZONE', 'America/Cancun'),
                'sync_enabled'         => false,
                'auto_sync_on_create'  => true,
            ]);
        }

        return $setting;
    }

    /**
     * Save settings for the user.
     */
    public function saveSettings(User $user, array $data): GoogleCalendarSetting
    {
        $setting = $this->getSettings($user);

        $payload = [
            'calendar_id'         => trim((string) ($data['calendar_id'] ?? $setting->calendar_id ?? 'primary')),
            'timezone'            => trim((string) ($data['timezone'] ?? $setting->timezone ?? 'America/Cancun')),
            'sync_enabled'        => (bool) ($data['sync_enabled'] ?? false),
            'auto_sync_on_create' => (bool) ($data['auto_sync_on_create'] ?? true),
        ];

        if (array_key_exists('service_account_json', $data)) {
            $rawJson = trim((string) $data['service_account_json']);
            if (!empty($rawJson)) {
                $payload['service_account_json'] = $rawJson;
            }
        }

        $setting->update($payload);

        // Clear cached access token for this user so changes take effect
        Cache::forget('google_cal_token_' . $user->id);

        return $setting->fresh();
    }

    /**
     * Obtain a valid OAuth2 Bearer access token using Google Service Account JWT (RS256).
     */
    public function getAccessToken(GoogleCalendarSetting $setting): ?string
    {
        $userId = (string) $setting->user_id;
        $cacheKey = 'google_cal_token_' . $userId;

        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        $credentials = $this->resolveCredentials($setting);
        if (!$credentials) {
            return null;
        }

        $clientEmail = $credentials['client_email'] ?? null;
        $privateKey  = $credentials['private_key'] ?? null;

        if (!$clientEmail || !$privateKey) {
            throw new \RuntimeException('El archivo de credenciales de Google Service Account no contiene client_email o private_key válidos.');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $claims = $this->base64UrlEncode(json_encode([
            'iss'   => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ]));

        $signature = '';
        $success = openssl_sign("{$header}.{$claims}", $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$success) {
            throw new \RuntimeException('No se pudo firmar el JWT con la clave privada proporcionada.');
        }

        $jwtAssertion = "{$header}.{$claims}." . $this->base64UrlEncode($signature);

        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwtAssertion,
        ]);

        if (!$response->successful()) {
            Log::error('Google OAuth2 token error: ' . $response->body());
            throw new \RuntimeException('Error de autenticación con Google: ' . ($response->json('error_description') ?? $response->body()));
        }

        $accessToken = (string) $response->json('access_token');
        $expiresIn   = (int) ($response->json('expires_in') ?? 3600);

        Cache::put($cacheKey, $accessToken, max(60, $expiresIn - 300));

        return $accessToken;
    }

    /**
     * Resolve credentials from DB json or local file fallback.
     */
    protected function resolveCredentials(GoogleCalendarSetting $setting): ?array
    {
        if (!empty($setting->service_account_json)) {
            $parsed = json_decode($setting->service_account_json, true);
            if (is_array($parsed) && isset($parsed['client_email'])) {
                return $parsed;
            }
        }

        $filePath = env('GOOGLE_SERVICE_ACCOUNT_PATH');
        if (!empty($filePath)) {
            $fullPath = base_path($filePath);
            if (file_exists($fullPath)) {
                $content = file_get_contents($fullPath);
                $parsed  = json_decode($content, true);
                if (is_array($parsed) && isset($parsed['client_email'])) {
                    return $parsed;
                }
            }
        }

        return null;
    }

    /**
     * Test connection to Google Calendar.
     */
    public function testConnection(User $user): array
    {
        $setting = $this->getSettings($user);

        try {
            $token = $this->getAccessToken($setting);
            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'No se encontraron credenciales de Cuenta de Servicio (JSON) configuradas.',
                ];
            }

            $calendarId = urlencode($setting->calendar_id ?: 'primary');
            $response = Http::withToken($token)
                ->timeout(20)
                ->get("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}");

            if ($response->successful()) {
                $calData = $response->json();
                return [
                    'success'  => true,
                    'message'  => "Conexión exitosa con el calendario: " . ($calData['summary'] ?? $setting->calendar_id),
                    'calendar' => $calData,
                ];
            }

            return [
                'success' => false,
                'message' => 'Error al consultar calendario en Google (' . $response->status() . '): ' . ($response->json('error.message') ?? $response->body()),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Excepción de conexión: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Push a single local CalendarEvent to Google Calendar.
     */
    public function pushEventToGoogle(User $user, CalendarEvent $event): ?string
    {
        $setting = $this->getSettings($user);
        if (!$setting->sync_enabled && !$setting->auto_sync_on_create) {
            return null;
        }

        try {
            $token = $this->getAccessToken($setting);
            if (!$token) {
                return null;
            }

            $calendarId = urlencode($setting->calendar_id ?: 'primary');
            $tz = $setting->timezone ?: 'America/Cancun';

            $body = [
                'summary'     => $event->title,
                'description' => $event->description,
                'location'    => $event->location,
                'start'       => [
                    'dateTime' => $event->start_time ? $event->start_time->toIso8601String() : null,
                    'timeZone' => $tz,
                ],
                'end'         => [
                    'dateTime' => $event->end_time ? $event->end_time->toIso8601String() : null,
                    'timeZone' => $tz,
                ],
            ];

            if (!empty($event->google_event_id)) {
                // Update existing
                $res = Http::withToken($token)
                    ->timeout(25)
                    ->put("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/" . urlencode($event->google_event_id), $body);
            } else {
                // Insert new
                $res = Http::withToken($token)
                    ->timeout(25)
                    ->post("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events", $body);
            }

            if ($res->successful()) {
                $googleId = (string) $res->json('id');
                $event->update([
                    'google_event_id' => $googleId,
                    'synced_at'       => Carbon::now($tz),
                ]);
                return $googleId;
            } else {
                Log::warning('Google Calendar event push failed: ' . $res->body());
            }
        } catch (\Throwable $e) {
            Log::error('Exception in pushEventToGoogle: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Execute full bidirectional sync (Pull from Google & Push unsynced local events).
     */
    public function sync(User $user): array
    {
        $setting = $this->getSettings($user);
        $token = $this->getAccessToken($setting);

        if (!$token) {
            throw new \RuntimeException('No se pudo autenticar con Google. Verifique las credenciales JSON configuradas.');
        }

        $calendarId = urlencode($setting->calendar_id ?: 'primary');
        $tz = $setting->timezone ?: 'America/Cancun';
        $userId = (string) $user->id;

        $pushedCount = 0;
        $pulledCount = 0;

        // 1. PUSH: Local events without google_event_id
        $localUnsynced = CalendarEvent::where('user_id', $userId)
            ->where(function ($q) {
                $q->whereNull('google_event_id')->orWhere('google_event_id', '');
            })
            ->where('start_time', '>=', Carbon::now($tz)->subDays(7))
            ->get();

        foreach ($localUnsynced as $event) {
            $gId = $this->pushEventToGoogle($user, $event);
            if ($gId) {
                $pushedCount++;
            }
        }

        // 2. PULL: Google Calendar events from 30 days ago to 90 days ahead
        $timeMin = Carbon::now($tz)->subDays(30)->startOfDay()->toIso8601String();
        $timeMax = Carbon::now($tz)->addDays(90)->endOfDay()->toIso8601String();

        $res = Http::withToken($token)
            ->timeout(40)
            ->get("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events", [
                'timeMin'      => $timeMin,
                'timeMax'      => $timeMax,
                'singleEvents' => 'true',
                'orderBy'      => 'startTime',
                'maxResults'   => 150,
            ]);

        if ($res->successful()) {
            $items = $res->json('items') ?? [];
            foreach ($items as $item) {
                $googleId = (string) ($item['id'] ?? '');
                if (empty($googleId)) continue;

                $summary = (string) ($item['summary'] ?? 'Evento sin título');
                $desc    = (string) ($item['description'] ?? '');
                $loc     = (string) ($item['location'] ?? '');

                // Parse start and end (dateTime or date)
                $startRaw = $item['start']['dateTime'] ?? ($item['start']['date'] ? $item['start']['date'] . 'T09:00:00' : null);
                $endRaw   = $item['end']['dateTime'] ?? ($item['end']['date'] ? $item['end']['date'] . 'T10:00:00' : null);

                if (!$startRaw) continue;

                $startTime = Carbon::parse($startRaw, $tz);
                $endTime   = $endRaw ? Carbon::parse($endRaw, $tz) : (clone $startTime)->addHour();

                $existing = CalendarEvent::where('user_id', $userId)
                    ->where('google_event_id', $googleId)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'title'       => $summary,
                        'description' => $desc ?: $existing->description,
                        'location'    => $loc ?: $existing->location,
                        'start_time'  => $startTime,
                        'end_time'    => $endTime,
                        'synced_at'   => Carbon::now($tz),
                    ]);
                } else {
                    CalendarEvent::create([
                        'user_id'         => $userId,
                        'title'           => $summary,
                        'description'     => $desc,
                        'location'        => $loc,
                        'start_time'      => $startTime,
                        'end_time'        => $endTime,
                        'status'          => 'scheduled',
                        'google_event_id' => $googleId,
                        'synced_at'       => Carbon::now($tz),
                    ]);
                    $pulledCount++;
                }
            }
        } else {
            Log::warning('Google Calendar pull events failed: ' . $res->body());
        }

        $setting->update([
            'last_synced_at' => Carbon::now($tz),
        ]);

        return [
            'success'        => true,
            'pushed'         => $pushedCount,
            'pulled'         => $pulledCount,
            'last_synced_at' => $setting->last_synced_at ? $setting->last_synced_at->toIso8601String() : null,
            'message'        => "Sincronización completada. Se subieron {$pushedCount} eventos locales y se descargaron/actualizaron {$pulledCount} eventos desde Google Calendar.",
        ];
    }

    /**
     * Base64 URL safe encode.
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
