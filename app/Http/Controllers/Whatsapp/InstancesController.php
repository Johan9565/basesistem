<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappInstance;
use App\Services\Whatsapp\EvolutionApiClient;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
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
            'status' => 'nullable|string|in:active,inactive',
            'create_in_evolution' => 'sometimes|boolean',
            'webhook_url' => 'nullable|string|max:500',
            'credentials_file' => 'required|file|max:512|mimes:json,txt',
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
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()->route('whatsapp.instances')->with('flash', [
            'type' => 'success',
            'message' => 'Instancia creada correctamente.',
        ]);
    }

    public function update(Request $request, string $instance)
    {
        $row = $this->findInstance($instance);

        $validated = $request->validate([
            'google_calendar_id' => 'required|string|max:255',
            'timezone' => 'nullable|string|max:64',
            'system_prompt' => 'nullable|string|max:5000',
            'status' => 'required|string|in:active,inactive',
            'credentials_file' => 'nullable|file|max:512|mimes:json,txt',
        ]);

        $payload = [
            'google_calendar_id' => $validated['google_calendar_id'],
            'timezone' => $validated['timezone'] ?: config('services.google_calendar.timezone'),
            'system_prompt' => $validated['system_prompt'] ?? null,
            'status' => $validated['status'],
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

        return redirect()->route('whatsapp.instances')->with('flash', [
            'type' => 'success',
            'message' => 'Instancia actualizada.',
        ]);
    }

    public function destroy(string $instance)
    {
        $row = $this->findInstance($instance);
        $this->deleteCredentialsFile((string) ($row->google_credentials_path ?? ''));
        $row->delete();

        return redirect()->route('whatsapp.instances')->with('flash', [
            'type' => 'success',
            'message' => 'Instancia eliminada de Mongo (Evolution no se tocó).',
        ]);
    }

    public function connect(string $instance, EvolutionApiClient $evolution)
    {
        $row = $this->findInstance($instance);
        $name = (string) $row->instance_name;

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
            'state' => $state,
            'connect' => $connect,
            'qr_base64' => $qrBase64,
        ])->with('flash', [
            'type' => 'success',
            'message' => "Estado / QR de {$name} actualizado.",
        ]);
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
            'google_calendar_id' => (string) ($row->google_calendar_id ?? ''),
            'google_service_email' => (string) ($row->google_service_email ?? ''),
            'has_credentials' => $hasCredentials,
            'timezone' => (string) ($row->timezone ?? ''),
            'system_prompt' => (string) ($row->system_prompt ?? ''),
            'status' => (string) ($row->status ?? 'active'),
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
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
