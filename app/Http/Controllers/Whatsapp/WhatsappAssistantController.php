<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappInstance;
use App\Services\Whatsapp\EvolutionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WhatsappAssistantController extends Controller
{
    /**
     * @var EvolutionApiService
     */
    protected $evolution;

    public function __construct(EvolutionApiService $evolution)
    {
        $this->evolution = $evolution;
    }

    /**
     * Muestra la vista principal del Asistente de WhatsApp personal.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $instance = WhatsappInstance::where('user_id', (string) $user->getKey())->first();

        $qrCode = null;
        $connectionState = 'disconnected';

        if ($instance) {
            try {
                $stateRes = $this->evolution->connectionState($instance->instance_name);
                $state = data_get($stateRes, 'instance.state') ?? 'close';

                // Si está abierto, sincronizamos info de perfil
                if ($state === 'open') {
                    $connectionState = 'connected';
                    $fetch = $this->evolution->fetchInstance($instance->instance_name);
                    if ($fetch) {
                        $instance->update([
                            'status'          => 'connected',
                            'phone'           => $fetch['ownerJid'] ?? $instance->phone,
                            'profile_name'    => $fetch['profileName'] ?? $instance->profile_name,
                            'profile_pic_url' => $fetch['profilePicUrl'] ?? $instance->profile_pic_url,
                        ]);
                    }
                } elseif ($state === 'connecting') {
                    $connectionState = 'connecting';
                    $instance->update(['status' => 'connecting']);
                    $qrCode = $this->extractQr($this->evolution->connect($instance->instance_name));
                } else {
                    $connectionState = 'disconnected';
                    $instance->update(['status' => 'disconnected']);
                }
            } catch (Throwable $e) {
                $connectionState = $instance->status ?? 'disconnected';
            }
        }

        return Inertia::render('Whatsapp/Assistant', [
            'instance' => $instance ? [
                'id'              => (string) $instance->getKey(),
                'instance_name'   => $instance->instance_name,
                'phone'           => $instance->phone,
                'status'          => $connectionState,
                'profile_name'    => $instance->profile_name,
                'profile_pic_url' => $instance->profile_pic_url,
                'system_prompt'   => $instance->system_prompt ?? '',
                'enabled'         => (bool) ($instance->enabled ?? true),
                'updated_at'      => $instance->updated_at ? $instance->updated_at->toIso8601String() : null,
            ] : null,
            'qrCode' => $qrCode,
        ]);
    }

    /**
     * Crea o inicializa la instancia de Evolution API para el usuario actual.
     */
    public function storeInstance(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'system_prompt' => 'nullable|string|max:4000',
        ]);

        $safeUserSlug = Str::slug($user->name ?: 'user') . '_' . substr(md5((string) $user->getKey()), 0, 8);
        $instanceName = 'wa_' . $safeUserSlug;

        try {
            $this->evolution->createInstance($instanceName);
        } catch (Throwable $e) {
            // Si ya existe en Evolution, la reutilizamos
        }

        $instance = WhatsappInstance::updateOrCreate(
            ['user_id' => (string) $user->getKey()],
            [
                'instance_name' => $instanceName,
                'status'        => 'connecting',
                'system_prompt' => $request->input('system_prompt', 'Eres un asistente personal inteligente y amable en WhatsApp. Ayudas al usuario con recordatorios, notas, agenda y dudas generales.'),
                'enabled'       => true,
            ]
        );

        return back()->with('flash', [
            'bannerStyle' => 'success',
            'banner'      => 'Instancia de WhatsApp configurada. Escanea el código QR para conectar.',
        ]);
    }

    /**
     * Obtiene el código QR más reciente vía AJAX / polling.
     */
    public function getQrCode(Request $request): JsonResponse
    {
        $user = $request->user();
        $instance = WhatsappInstance::where('user_id', (string) $user->getKey())->first();

        if (!$instance) {
            return response()->json(['error' => 'Instancia no encontrada'], 404);
        }

        try {
            $stateRes = $this->evolution->connectionState($instance->instance_name);
            $state = data_get($stateRes, 'instance.state') ?? 'close';

            if ($state === 'open') {
                $instance->update(['status' => 'connected']);
                return response()->json([
                    'state'  => 'connected',
                    'qrcode' => null,
                ]);
            }

            try {
                $connectRes = $this->evolution->connect($instance->instance_name);
            } catch (Throwable $connectError) {
                if (str_contains($connectError->getMessage(), 'does not exist') || str_contains($connectError->getMessage(), '404')) {
                    $this->evolution->createInstance($instance->instance_name);
                    $connectRes = $this->evolution->connect($instance->instance_name);
                } else {
                    throw $connectError;
                }
            }
            $qr = $this->extractQr($connectRes);
            $pairingCode = data_get($connectRes, 'pairingCode');

            return response()->json([
                'state'       => 'connecting',
                'qrcode'      => $qr,
                'pairingCode' => $pairingCode,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'state' => 'error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza la configuración del Asistente de WhatsApp del usuario.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $user = $request->user();
        $instance = WhatsappInstance::where('user_id', (string) $user->getKey())->firstOrFail();

        $validated = $request->validate([
            'system_prompt' => 'nullable|string|max:4000',
            'enabled'       => 'required|boolean',
        ]);

        $instance->update($validated);

        return back()->with('flash', [
            'bannerStyle' => 'success',
            'banner'      => 'Configuración de WhatsApp guardada con éxito.',
        ]);
    }

    /**
     * Desconecta la sesión de WhatsApp en Evolution API.
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $instance = WhatsappInstance::where('user_id', (string) $user->getKey())->firstOrFail();

        try {
            $this->evolution->logout($instance->instance_name);
            $instance->update(['status' => 'disconnected']);
        } catch (Throwable $e) {
            // Ignorar errores si ya estaba desconectado
        }

        return back()->with('flash', [
            'bannerStyle' => 'success',
            'banner'      => 'Sesión de WhatsApp cerrada.',
        ]);
    }

    /**
     * Elimina completamente la instancia de WhatsApp.
     */
    public function deleteInstance(Request $request): RedirectResponse
    {
        $user = $request->user();
        $instance = WhatsappInstance::where('user_id', (string) $user->getKey())->firstOrFail();

        try {
            $this->evolution->deleteInstance($instance->instance_name);
        } catch (Throwable $e) {
            // Ignorar si no existía en Evolution
        }

        $instance->delete();

        return back()->with('flash', [
            'bannerStyle' => 'success',
            'banner'      => 'Instancia de WhatsApp eliminada.',
        ]);
    }

    /**
     * Envía un mensaje de prueba desde la instancia conectada.
     */
    public function testMessage(Request $request): RedirectResponse
    {
        $user = $request->user();
        $instance = WhatsappInstance::where('user_id', (string) $user->getKey())->firstOrFail();

        $validated = $request->validate([
            'phone'   => 'required|string|min:8|max:25',
            'message' => 'required|string|max:1000',
        ]);

        try {
            $this->evolution->sendHumanizedMessage(
                $instance->instance_name,
                $validated['phone'],
                $validated['message']
            );

            return back()->with('flash', [
                'bannerStyle' => 'success',
                'banner'      => 'Mensaje de prueba enviado exitosamente.',
            ]);
        } catch (Throwable $e) {
            return back()->with('flash', [
                'bannerStyle' => 'danger',
                'banner'      => 'Error al enviar mensaje: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Extrae el Base64 del código QR devuelto por Evolution API.
     */
    protected function extractQr(array $connect): ?string
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
                return 'data:image/png;base64,' . $value;
            }
        }

        return null;
    }
}
