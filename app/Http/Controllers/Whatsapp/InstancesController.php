<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappInstance;
use App\Services\Telegram\AdminAssistantOrchestrator;
use App\Services\Whatsapp\EvolutionApiClient;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;
use Throwable;

class InstancesController extends Controller
{
    public function index()
    {
        $instances = WhatsappInstance::query()
            ->orderBy('instance_name')
            ->get()
            ->map(fn (WhatsappInstance $row) => $this->serialize($row))
            ->values();

        return Inertia::render('Whatsapp/Instances/Index', [
            'instances' => $instances,
            'defaults' => [
                'timezone' => (string) config('services.google_calendar.timezone', 'America/Merida'),
                'calendar_id' => (string) config('services.google_calendar.calendar_id', ''),
                'webhook_url' => (string) config('services.evolution.webhook_url', ''),
                'telegram_webhook_base' => (string) config('services.telegram.webhook_base_url', ''),
            ],
            'connectResult' => session('whatsapp_connect'),
        ]);
    }

    public function store(Request $request, EvolutionApiClient $evolution)
    {
        $validated = $request->validate([
            'instance_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/',
            ],
            'google_calendar_id' => 'required|string|max:255',
            'timezone' => 'nullable|string|max:64',
            'system_prompt' => 'nullable|string|max:5000',
            'business_name' => 'nullable|string|max:255',
            'services' => 'nullable|string|max:5000',
            'business_hours' => 'nullable|string|max:2000',
            'prices' => 'nullable|string|max:3000',
            'promotions' => 'nullable|string|max:3000',
            'locations' => 'nullable|string|max:3000',
            'ai_provider' => 'nullable|string|in:deepseek,mimo',
            'status' => 'nullable|string|in:active,inactive',
            'create_in_evolution' => 'sometimes|boolean',
            'webhook_url' => 'nullable|string|max:500',
            'credentials_file' => 'required|file|max:512|mimes:json,txt',
            'telegram_bot_token' => 'nullable|string|max:200',
            'telegram_allowed_user_ids' => 'nullable|string|max:2000',
            'register_telegram_webhook' => 'sometimes|boolean',
        ]);

        if (WhatsappInstance::query()->where('instance_name', $validated['instance_name'])->exists()) {
            return back()->withInput()->withErrors([
                'instance_name' => 'Ya existe una instancia con ese nombre.',
            ]);
        }

        try {
            $credentials = $this->storeCredentialsFile(
                $request->file('credentials_file'),
                $validated['instance_name'],
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors([
                'credentials_file' => $e->getMessage(),
            ]);
        }

        $createInEvolution = filter_var(
            $request->input('create_in_evolution', true),
            FILTER_VALIDATE_BOOLEAN,
        );

        if ($createInEvolution) {
            try {
                $evolution->createInstance(
                    $validated['instance_name'],
                    ! empty($validated['webhook_url']) ? $validated['webhook_url'] : null,
                );
            } catch (Throwable $e) {
                $this->deleteCredentialsFile($credentials['path']);

                return back()->withInput()->with('flash', [
                    'type' => 'error',
                    'message' => $e->getMessage(),
                ]);
            }
        }

        WhatsappInstance::query()->create([
            'instance_name' => $validated['instance_name'],
            'google_calendar_id' => $validated['google_calendar_id'],
            'google_credentials_path' => $credentials['path'],
            'google_service_email' => $credentials['email'],
            'timezone' => $validated['timezone'] ?: config('services.google_calendar.timezone'),
            'system_prompt' => $validated['system_prompt'] ?? null,
            'business_name' => $validated['business_name'] ?? null,
            'services' => $validated['services'] ?? null,
            'business_hours' => $validated['business_hours'] ?? null,
            'prices' => $validated['prices'] ?? null,
            'promotions' => $validated['promotions'] ?? null,
            'locations' => $validated['locations'] ?? null,
            'ai_provider' => $validated['ai_provider'] ?? 'deepseek',
            'status' => $validated['status'] ?? 'active',
            'evolution_instance_name' => $validated['instance_name'],
            ...$this->telegramPayloadFromValidated($validated, null),
        ]);

        $created = WhatsappInstance::query()
            ->where('instance_name', $validated['instance_name'])
            ->first();

        if ($created && ($created->status ?? '') === 'active') {
            $this->deactivateSiblingProfiles($created);
        }

        $telegramNote = '';
        if (
            $created
            && filter_var($request->input('register_telegram_webhook', false), FILTER_VALIDATE_BOOLEAN)
            && trim((string) ($created->telegram_bot_token ?? '')) !== ''
        ) {
            try {
                app(AdminAssistantOrchestrator::class)->provisionBot($created);
                $telegramNote = ' Webhook de Telegram registrado.';
            } catch (Throwable $e) {
                $telegramNote = ' Telegram no se registró: '.$e->getMessage();
            }
        }

        return redirect()->route('whatsapp.instances')->with('flash', [
            'type' => str_contains($telegramNote, 'no se registró') ? 'error' : 'success',
            'message' => 'Instancia creada correctamente.'.$telegramNote,
        ]);
    }

    public function update(Request $request, string $instance)
    {
        $row = $this->findInstance($instance);

        $validated = $request->validate([
            'google_calendar_id' => 'required|string|max:255',
            'timezone' => 'nullable|string|max:64',
            'system_prompt' => 'nullable|string|max:5000',
            'business_name' => 'nullable|string|max:255',
            'services' => 'nullable|string|max:5000',
            'business_hours' => 'nullable|string|max:2000',
            'prices' => 'nullable|string|max:3000',
            'promotions' => 'nullable|string|max:3000',
            'locations' => 'nullable|string|max:3000',
            'ai_provider' => 'required|string|in:deepseek,mimo',
            'status' => 'required|string|in:active,inactive',
            'credentials_file' => 'nullable|file|max:512|mimes:json,txt',
            'telegram_bot_token' => 'nullable|string|max:200',
            'telegram_allowed_user_ids' => 'nullable|string|max:2000',
            'clear_telegram_bot' => 'sometimes|boolean',
            'register_telegram_webhook' => 'sometimes|boolean',
            'regenerate_telegram_link_code' => 'sometimes|boolean',
        ]);

        $payload = [
            'google_calendar_id' => $validated['google_calendar_id'],
            'timezone' => $validated['timezone'] ?: config('services.google_calendar.timezone'),
            'system_prompt' => $validated['system_prompt'] ?? null,
            'business_name' => $validated['business_name'] ?? null,
            'services' => $validated['services'] ?? null,
            'business_hours' => $validated['business_hours'] ?? null,
            'prices' => $validated['prices'] ?? null,
            'promotions' => $validated['promotions'] ?? null,
            'locations' => $validated['locations'] ?? null,
            'ai_provider' => $validated['ai_provider'],
            'status' => $validated['status'],
            ...$this->telegramPayloadFromValidated($validated, $row),
        ];

        if ($request->hasFile('credentials_file')) {
            try {
                $credentials = $this->storeCredentialsFile(
                    $request->file('credentials_file'),
                    (string) $row->instance_name,
                    (string) ($row->google_credentials_path ?? ''),
                );
                $payload['google_credentials_path'] = $credentials['path'];
                $payload['google_service_email'] = $credentials['email'];
            } catch (RuntimeException $e) {
                return back()->withInput()->withErrors([
                    'credentials_file' => $e->getMessage(),
                ]);
            }
        }

        $row->update($payload);
        $row->refresh();

        if (($row->status ?? '') === 'active') {
            $this->deactivateSiblingProfiles($row);
        }

        $telegramNote = '';
        $shouldRegister = filter_var(
            $request->input('register_telegram_webhook', false),
            FILTER_VALIDATE_BOOLEAN,
        ) || (
            trim((string) ($validated['telegram_bot_token'] ?? '')) !== ''
            && ! filter_var($request->input('clear_telegram_bot', false), FILTER_VALIDATE_BOOLEAN)
        );

        if ($shouldRegister && trim((string) ($row->telegram_bot_token ?? '')) !== '') {
            try {
                app(AdminAssistantOrchestrator::class)->provisionBot($row);
                $telegramNote = ' Webhook de Telegram actualizado.';
            } catch (Throwable $e) {
                $telegramNote = ' Telegram webhook: '.$e->getMessage();
            }
        }

        $type = str_contains($telegramNote, 'Telegram webhook:') ? 'error' : 'success';

        return redirect()->route('whatsapp.instances')->with('flash', [
            'type' => $type,
            'message' => 'Instancia actualizada.'.$telegramNote,
        ]);
    }

    /**
     * Copia el perfil de negocio reutilizando la misma sesión Evolution (WhatsApp),
     * Calendar y credenciales. No crea instancia nueva en Evolution.
     */
    public function duplicate(Request $request, string $instance)
    {
        $source = $this->findInstance($instance);

        $validated = $request->validate([
            'instance_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/',
            ],
            'business_name' => 'nullable|string|max:255',
            'copy_telegram' => 'sometimes|boolean',
        ]);

        if (WhatsappInstance::query()->where('instance_name', $validated['instance_name'])->exists()) {
            return back()->withInput()->withErrors([
                'instance_name' => 'Ya existe una instancia con ese nombre.',
            ]);
        }

        $copyTelegram = filter_var($request->input('copy_telegram', false), FILTER_VALIDATE_BOOLEAN);

        $payload = [
            'instance_name' => $validated['instance_name'],
            'evolution_instance_name' => $source->evolutionName(),
            'google_calendar_id' => $source->google_calendar_id,
            'google_credentials_path' => $source->google_credentials_path,
            'google_service_email' => $source->google_service_email,
            'timezone' => $source->timezone,
            'ai_provider' => $source->ai_provider ?: 'deepseek',
            'system_prompt' => $source->system_prompt,
            'business_name' => $validated['business_name']
                ?? (($source->business_name ? $source->business_name.' (copia)' : null)),
            'services' => $source->services,
            'business_hours' => $source->business_hours,
            'prices' => $source->prices,
            'promotions' => $source->promotions,
            'locations' => $source->locations,
            // Inactiva: al activarla se desactivan los otros perfiles de la misma sesión.
            'status' => 'inactive',
        ];

        if ($copyTelegram && trim((string) ($source->telegram_bot_token ?? '')) !== '') {
            $payload['telegram_bot_token'] = $source->telegram_bot_token;
            $payload['telegram_bot_username'] = $source->telegram_bot_username;
            $payload['telegram_webhook_secret'] = $source->telegram_webhook_secret;
            $payload['telegram_link_code'] = $source->telegram_link_code;
            $payload['telegram_allowed_user_ids'] = $source->telegram_allowed_user_ids;
        }

        $created = WhatsappInstance::query()->create($payload);

        return redirect()->route('whatsapp.instances')->with('flash', [
            'type' => 'success',
            'message' => sprintf(
                'Perfil «%s» duplicado desde «%s». Comparte la sesión WhatsApp «%s». Está inactivo: edita el negocio y actívalo cuando quieras (la otra se pausará sola).',
                $created->instance_name,
                $source->instance_name,
                $source->evolutionName(),
            ),
        ]);
    }

    public function destroy(string $instance)
    {
        $row = $this->findInstance($instance);
        $credentialsPath = (string) ($row->google_credentials_path ?? '');
        $row->delete();

        // Solo borrar el JSON si ningún otro perfil lo comparte.
        if ($credentialsPath !== '') {
            $stillUsed = WhatsappInstance::query()
                ->where('google_credentials_path', $credentialsPath)
                ->exists();
            if (! $stillUsed) {
                $this->deleteCredentialsFile($credentialsPath);
            }
        }

        return redirect()->route('whatsapp.instances')->with('flash', [
            'type' => 'success',
            'message' => 'Perfil eliminado de Mongo (Evolution / sesión WhatsApp no se tocó).',
        ]);
    }

    public function connect(string $instance, EvolutionApiClient $evolution)
    {
        $row = $this->findInstance($instance);
        $name = $row->evolutionName();

        try {
            $state = $evolution->connectionState($name);
            $connect = $evolution->connect($name);
        } catch (RuntimeException $e) {
            return redirect()->route('whatsapp.instances')->with('flash', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        $qrBase64 = $this->extractQrBase64($connect);

        return redirect()->route('whatsapp.instances')->with('whatsapp_connect', [
            'instance_name' => $name,
            'profile_name' => (string) $row->instance_name,
            'state' => $state,
            'connect' => $connect,
            'qr_base64' => $qrBase64,
        ])->with('flash', [
            'type' => 'success',
            'message' => "Estado / QR de sesión {$name} actualizado.",
        ]);
    }

    /**
     * Solo un perfil activo por sesión Evolution.
     */
    protected function deactivateSiblingProfiles(WhatsappInstance $active): void
    {
        $session = $active->evolutionName();
        if ($session === '') {
            return;
        }

        WhatsappInstance::query()
            ->where('_id', '!=', $active->getKey())
            ->where('status', 'active')
            ->get()
            ->filter(fn (WhatsappInstance $row) => $row->evolutionName() === $session)
            ->each(function (WhatsappInstance $row) {
                $row->update(['status' => 'inactive']);
            });
    }

    protected function findInstance(string $id): WhatsappInstance
    {
        $row = WhatsappInstance::query()->find($id);

        if (! $row) {
            abort(404);
        }

        return $row;
    }

    /**
     * @return array{path: string, email: string}
     */
    protected function storeCredentialsFile(
        UploadedFile $file,
        string $instanceName,
        string $previousRelativePath = '',
    ): array {
        $raw = file_get_contents($file->getRealPath());
        if ($raw === false || $raw === '') {
            throw new RuntimeException('No se pudo leer el archivo JSON.');
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ($decoded['type'] ?? '') !== 'service_account') {
            throw new RuntimeException('El archivo debe ser un JSON de cuenta de servicio de Google.');
        }

        $email = (string) ($decoded['client_email'] ?? '');
        if ($email === '' || ! str_contains($email, '.iam.gserviceaccount.com')) {
            throw new RuntimeException('El JSON no incluye un client_email de cuenta de servicio válido.');
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $instanceName) ?: 'instance';
        $relativeDir = 'storage/app/google/instances';
        $absoluteDir = base_path($relativeDir);
        File::ensureDirectoryExists($absoluteDir);

        $relativePath = $relativeDir.'/'.$safeName.'.json';
        $absolutePath = base_path($relativePath);

        if (file_put_contents($absolutePath, $raw) === false) {
            throw new RuntimeException('No se pudo guardar el JSON de credenciales.');
        }

        if (
            $previousRelativePath !== ''
            && $previousRelativePath !== $relativePath
            && ! str_ends_with($previousRelativePath, '/service-account.json')
        ) {
            $this->deleteCredentialsFile($previousRelativePath);
        }

        return [
            'path' => $relativePath,
            'email' => $email,
        ];
    }

    protected function deleteCredentialsFile(string $relativePath): void
    {
        if ($relativePath === '') {
            return;
        }

        // No borrar el fallback global legacy.
        if (str_ends_with($relativePath, '/service-account.json')
            && ! str_contains($relativePath, '/instances/')) {
            return;
        }

        $absolute = str_starts_with($relativePath, '/')
            ? $relativePath
            : base_path($relativePath);

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(WhatsappInstance $row): array
    {
        $credentialsPath = (string) ($row->google_credentials_path ?? '');
        $hasCredentials = $credentialsPath !== '' && is_readable(
            str_starts_with($credentialsPath, '/')
                ? $credentialsPath
                : base_path($credentialsPath)
        );

        return [
            'id' => (string) $row->getKey(),
            'instance_name' => (string) ($row->instance_name ?? ''),
            'evolution_instance_name' => $row->evolutionName(),
            'shares_session' => $row->evolutionName() !== (string) ($row->instance_name ?? ''),
            'google_calendar_id' => (string) ($row->google_calendar_id ?? ''),
            'google_service_email' => (string) ($row->google_service_email ?? ''),
            'has_credentials' => $hasCredentials,
            'timezone' => (string) ($row->timezone ?? ''),
            'system_prompt' => (string) ($row->system_prompt ?? ''),
            'business_name' => (string) ($row->business_name ?? ''),
            'services' => (string) ($row->services ?? ''),
            'business_hours' => (string) ($row->business_hours ?? ''),
            'prices' => (string) ($row->prices ?? ''),
            'promotions' => (string) ($row->promotions ?? ''),
            'locations' => (string) ($row->locations ?? ''),
            'ai_provider' => (string) ($row->ai_provider ?? 'deepseek'),
            'status' => (string) ($row->status ?? 'active'),
            'has_telegram_bot' => trim((string) ($row->telegram_bot_token ?? '')) !== '',
            'telegram_bot_username' => (string) ($row->telegram_bot_username ?? ''),
            'telegram_bot_token_hint' => $this->tokenHint((string) ($row->telegram_bot_token ?? '')),
            'telegram_link_code' => (string) ($row->telegram_link_code ?? ''),
            'telegram_allowed_user_ids' => implode(', ', array_map(
                'strval',
                is_array($row->telegram_allowed_user_ids) ? $row->telegram_allowed_user_ids : [],
            )),
            'telegram_webhook_url' => trim((string) ($row->telegram_bot_token ?? '')) !== ''
                ? app(\App\Services\Telegram\TelegramBotClient::class)
                    ->webhookUrlForInstance((string) $row->instance_name)
                : '',
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function telegramPayloadFromValidated(array $validated, ?WhatsappInstance $existing): array
    {
        $clear = filter_var($validated['clear_telegram_bot'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($clear) {
            return [
                'telegram_bot_token' => null,
                'telegram_bot_username' => null,
                'telegram_webhook_secret' => null,
            ];
        }

        $payload = [
            'telegram_allowed_user_ids' => $this->parseTelegramUserIds(
                (string) ($validated['telegram_allowed_user_ids'] ?? ''),
            ),
        ];

        $newToken = trim((string) ($validated['telegram_bot_token'] ?? ''));
        if ($newToken !== '') {
            $payload['telegram_bot_token'] = $newToken;
            if (! $existing || trim((string) ($existing->telegram_webhook_secret ?? '')) === '') {
                $payload['telegram_webhook_secret'] = Str::random(32);
            }
            if (! $existing || trim((string) ($existing->telegram_link_code ?? '')) === '') {
                $payload['telegram_link_code'] = Str::lower(Str::random(10));
            }
        } elseif ($existing === null) {
            // create without token: nothing else
        }

        if (filter_var($validated['regenerate_telegram_link_code'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $payload['telegram_link_code'] = Str::lower(Str::random(10));
        }

        return $payload;
    }

    /**
     * @return list<int>
     */
    protected function parseTelegramUserIds(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $ids = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $ids[] = (int) $part;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function tokenHint(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        if (strlen($token) <= 8) {
            return '••••';
        }

        return '••••'.substr($token, -4);
    }

    /**
     * @param  array<string, mixed>  $connect
     */
    protected function extractQrBase64(array $connect): ?string
    {
        $candidates = [
            data_get($connect, 'base64'),
            data_get($connect, 'qrcode.base64'),
            data_get($connect, 'qr.base64'),
            data_get($connect, 'instance.qrcode.base64'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                if (str_starts_with($value, 'data:')) {
                    return $value;
                }

                return 'data:image/png;base64,'.$value;
            }
        }

        return null;
    }
}
