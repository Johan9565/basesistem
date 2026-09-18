<?php

namespace App\Console\Commands;

use App\Models\WhatsappInstance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class ProvisionWhatsappClientCommand extends Command
{
    protected $signature = 'whatsapp:provision-client
        {name : Identificador único de instancia (ej. cliente_sistema_a)}
        {--calendar= : Google Calendar ID}
        {--timezone= : Zona horaria}
        {--prompt= : System prompt}
        {--webhook= : URL pública/interna del webhook Laravel}
        {--skip-evolution : Solo registra en Mongo, no llama a Evolution}';

    protected $description = 'Alta de cliente: instancia Evolution + documento Mongo + webhook';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $calendarId = (string) ($this->option('calendar') ?: config('services.google_calendar.calendar_id'));
        $timezone = (string) ($this->option('timezone') ?: config('services.google_calendar.timezone'));
        $prompt = $this->option('prompt');
        $webhook = (string) ($this->option('webhook') ?: '');
        $skipEvolution = (bool) $this->option('skip-evolution');

        if ($calendarId === '') {
            $this->error('Indica --calendar=... o GOOGLE_CALENDAR_ID.');

            return self::FAILURE;
        }

        if (! $skipEvolution) {
            try {
                $this->createEvolutionInstance($name, $webhook !== '' ? $webhook : null);
            } catch (Throwable $e) {
                $this->error('Evolution create falló: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        WhatsappInstance::query()->updateOrCreate(
            ['instance_name' => $name],
            [
                'google_calendar_id' => $calendarId,
                'timezone' => $timezone,
                'system_prompt' => is_string($prompt) && $prompt !== '' ? $prompt : null,
                'status' => 'active',
            ],
        );

        $this->info("Cliente provisionado: {$name}");

        if (! $skipEvolution) {
            $base = rtrim((string) config('services.evolution.base_url'), '/');
            $this->line("QR / connect: GET {$base}/instance/connect/{$name} (header apikey)");
        }

        return self::SUCCESS;
    }

    protected function createEvolutionInstance(string $name, ?string $webhookUrl): void
    {
        $baseUrl = rtrim((string) config('services.evolution.base_url'), '/');
        $apiKey = (string) config('services.evolution.api_key');
        $secret = (string) config('services.evolution.webhook_secret');

        $payload = [
            'instanceName' => $name,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ];

        if ($webhookUrl) {
            $payload['webhook'] = [
                'url' => $webhookUrl,
                'byEvents' => false,
                'base64' => false,
                'events' => ['MESSAGES_UPSERT'],
                'headers' => [
                    'X-Evolution-Secret' => $secret,
                ],
            ];
        }

        Http::baseUrl($baseUrl)
            ->withHeaders(['apikey' => $apiKey])
            ->acceptJson()
            ->timeout(60)
            ->post('/instance/create', $payload)
            ->throw();

        $this->info("Instancia Evolution creada: {$name}");
    }
}
