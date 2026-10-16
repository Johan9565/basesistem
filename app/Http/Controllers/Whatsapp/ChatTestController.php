<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\WhatsappBookingState;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Services\Tenancy\CompanyContext;
use App\Services\Whatsapp\AppointmentOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ChatTestController extends Controller
{
    /**
     * Muestra la interfaz del simulador Chat Test para pruebas de bot sin WhatsApp.
     */
    public function index(Request $request, AppointmentOrchestrator $orchestrator): Response
    {
        $companyContext = app(CompanyContext::class);
        $activeCompany = $companyContext->getCompany();

        $instances = [];
        $selectedInstance = null;
        $testHistory = [];
        $defaultPhone = '+52 999 000 0000';

        if ($activeCompany) {
            $companyId = (string) $activeCompany->_id;

            // Obtener instancias registradas de la empresa activa
            $instanceModels = WhatsappInstance::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->get();

            // Si no existe ninguna instancia, crear una de prueba automáticamente para permitir testear de inmediato
            if ($instanceModels->isEmpty()) {
                $created = WhatsappInstance::create([
                    'company_id' => $companyId,
                    'instance_name' => 'bot_simulador_'.$activeCompany->slug,
                    'evolution_instance_name' => 'sim_'.$activeCompany->slug,
                    'business_name' => $activeCompany->name,
                    'status' => 'active',
                    'ai_provider' => 'deepseek',
                    'timezone' => $activeCompany->settings['timezone'] ?? 'America/Mexico_City',
                    'system_prompt' => "Eres el asistente virtual inteligente de {$activeCompany->name}. Atiende con amabilidad y resuelve dudas sobre nuestros productos, servicios y citas.",
                ]);
                $instanceModels = collect([$created]);
            }

            $instances = $instanceModels->map(fn ($inst) => [
                'id' => (string) $inst->_id,
                'instance_name' => $inst->instance_name,
                'business_name' => $inst->business_name ?: $activeCompany->name,
                'company_name' => $activeCompany->name,
                'status' => $inst->status ?? 'active',
                'ai_provider' => $inst->ai_provider ?? 'deepseek',
                'timezone' => $inst->timezone ?? 'America/Mexico_City',
                'system_prompt' => $inst->system_prompt ?? '',
            ])->values()->toArray();

            // Instancia seleccionada (por query param o la primera)
            $requestedId = $request->query('instance_id');
            $currentInstanceModel = $instanceModels->firstWhere('_id', $requestedId) ?? $instanceModels->first();

            if ($currentInstanceModel) {
                $selectedInstance = [
                    'id' => (string) $currentInstanceModel->_id,
                    'instance_name' => $currentInstanceModel->instance_name,
                    'business_name' => $currentInstanceModel->business_name ?: $activeCompany->name,
                    'company_name' => $activeCompany->name,
                    'status' => $currentInstanceModel->status ?? 'active',
                    'ai_provider' => $currentInstanceModel->ai_provider ?? 'deepseek',
                    'timezone' => $currentInstanceModel->timezone ?? 'America/Mexico_City',
                    'system_prompt' => $currentInstanceModel->system_prompt ?? '',
                ];

                $testHistory = $orchestrator->getTestHistory($currentInstanceModel, $defaultPhone, 40);
            }
        }

        $conversations = isset($currentInstanceModel) && $currentInstanceModel
            ? $this->getConversationsForInstance($currentInstanceModel)
            : [];

        $productsCount = $activeCompany ? Product::where('company_id', (string) $activeCompany->_id)->count() : 0;
        $servicesCount = $activeCompany ? Service::where('company_id', (string) $activeCompany->_id)->count() : 0;

        return Inertia::render('Whatsapp/ChatTest', [
            'instances' => $instances,
            'selectedInstance' => $selectedInstance,
            'initialHistory' => $testHistory,
            'initialConversations' => $conversations,
            'defaultPhone' => $defaultPhone,
            'companyKnowledge' => [
                'products_count' => $productsCount,
                'services_count' => $servicesCount,
                'company_name' => $activeCompany?->name ?? 'Sin Empresa',
                'timezone' => $activeCompany->settings['timezone'] ?? 'America/Mexico_City',
            ],
        ]);
    }

    /**
     * Procesa un mensaje enviado en el simulador y devuelve la contestación del bot sin usar WhatsApp.
     */
    public function send(Request $request, AppointmentOrchestrator $orchestrator): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:3000',
            'instance_id' => 'required|string',
            'phone' => 'nullable|string|max:30',
            'provider' => 'nullable|string|in:deepseek,mimo',
        ]);

        $instance = WhatsappInstance::withoutGlobalScopes()->find($validated['instance_id']);
        if (! $instance) {
            return response()->json([
                'ok' => false,
                'error' => 'Instancia de bot no encontrada.',
            ], 404);
        }

        $user = $request->user();
        $canSeeAllCompanies = $user ? $user->canSelectAllCompanies() : false;
        $accessibleCompanyIds = $user ? $user->getAccessibleCompanyIds() : [];

        if (! $canSeeAllCompanies && ! in_array((string) $instance->company_id, $accessibleCompanyIds, true)) {
            return response()->json([
                'ok' => false,
                'error' => 'No tienes permiso para acceder a esta instancia.',
            ], 403);
        }

        $phone = $validated['phone'] ?? '+52 999 000 0000';
        $provider = $validated['provider'] ?? null;
        $text = trim($validated['message']);

        $startTime = microtime(true);

        try {
            $result = $orchestrator->simulateIncoming($instance, $phone, $text, $provider);
            $executionMs = (int) round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'ok' => true,
                'reply' => $result['reply'],
                'tool_calls' => $result['tool_calls'],
                'booking_state' => $result['booking_state'],
                'provider' => $result['provider'],
                'static_prompt' => $result['static_prompt'],
                'dynamic_prompt' => $result['dynamic_prompt'],
                'calendar_ok' => $result['calendar_ok'],
                'execution_time_ms' => $executionMs,
            ]);
        } catch (Throwable $e) {
            $executionMs = (int) round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'ok' => false,
                'error' => 'Error al procesar contestación del bot: '.$e->getMessage(),
                'execution_time_ms' => $executionMs,
            ], 500);
        }
    }

    /**
     * Devuelve la lista de hilos de conversaciones (simuladas y reales) para la instancia.
     */
    public function conversations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'instance_id' => 'required|string',
            'source' => 'nullable|string|in:all,sim,real',
            'search' => 'nullable|string|max:100',
        ]);

        $instance = WhatsappInstance::withoutGlobalScopes()->find($validated['instance_id']);
        if (! $instance) {
            return response()->json(['ok' => false, 'error' => 'Instancia no encontrada.'], 404);
        }

        $user = $request->user();
        $canSeeAllCompanies = $user ? $user->canSelectAllCompanies() : false;
        $accessibleCompanyIds = $user ? $user->getAccessibleCompanyIds() : [];

        if (! $canSeeAllCompanies && ! in_array((string) $instance->company_id, $accessibleCompanyIds, true)) {
            return response()->json(['ok' => false, 'error' => 'No tienes permiso para acceder a esta instancia.'], 403);
        }

        $source = $validated['source'] ?? 'all';
        $search = trim((string) ($validated['search'] ?? ''));

        $conversations = $this->getConversationsForInstance($instance, $source, $search);

        return response()->json([
            'ok' => true,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Obtiene el historial actualizado para la instancia y teléfono de prueba.
     */
    public function history(Request $request, AppointmentOrchestrator $orchestrator): JsonResponse
    {
        $validated = $request->validate([
            'instance_id' => 'required|string',
            'phone' => 'nullable|string|max:30',
            'source' => 'nullable|string|in:sim,real,all',
        ]);

        $instance = WhatsappInstance::withoutGlobalScopes()->find($validated['instance_id']);
        if (! $instance) {
            return response()->json(['ok' => false, 'error' => 'Instancia no encontrada.'], 404);
        }

        $user = $request->user();
        $canSeeAllCompanies = $user ? $user->canSelectAllCompanies() : false;
        $accessibleCompanyIds = $user ? $user->getAccessibleCompanyIds() : [];

        if (! $canSeeAllCompanies && ! in_array((string) $instance->company_id, $accessibleCompanyIds, true)) {
            return response()->json(['ok' => false, 'error' => 'No tienes permiso para acceder a esta instancia.'], 403);
        }

        $phone = $validated['phone'] ?? '+52 999 000 0000';
        $source = $validated['source'] ?? 'sim';
        $history = $orchestrator->getTestHistory($instance, $phone, 60, $source);

        $sessionKey = ($source === 'real' ? '' : 'sim:').$instance->evolutionName();
        $normPhone = app(\App\Services\Whatsapp\EvolutionApiClient::class)->normalizePhone($phone);
        $booking = WhatsappBookingState::withoutGlobalScopes()
            ->where('instance_name', $sessionKey)
            ->where('user_phone', $normPhone)
            ->first();

        return response()->json([
            'ok' => true,
            'history' => $history,
            'booking_state' => $booking ? $booking->toArray() : [],
        ]);
    }

    /**
     * Reinicia el historial y el estado de citas de la sesión de prueba.
     */
    public function reset(Request $request, AppointmentOrchestrator $orchestrator): JsonResponse
    {
        $validated = $request->validate([
            'instance_id' => 'required|string',
            'phone' => 'nullable|string|max:30',
        ]);

        $instance = WhatsappInstance::withoutGlobalScopes()->find($validated['instance_id']);
        if (! $instance) {
            return response()->json(['ok' => false, 'error' => 'Instancia no encontrada.'], 404);
        }

        $user = $request->user();
        $canSeeAllCompanies = $user ? $user->canSelectAllCompanies() : false;
        $accessibleCompanyIds = $user ? $user->getAccessibleCompanyIds() : [];

        if (! $canSeeAllCompanies && ! in_array((string) $instance->company_id, $accessibleCompanyIds, true)) {
            return response()->json(['ok' => false, 'error' => 'No tienes permiso para acceder a esta instancia.'], 403);
        }

        $phone = $validated['phone'] ?? '+52 999 000 0000';
        $orchestrator->resetTestSession($instance, $phone);

        return response()->json([
            'ok' => true,
            'message' => 'Sesión de prueba reiniciada con éxito.',
        ]);
    }

    /**
     * Obtiene los hilos de conversaciones asociados a una instancia.
     *
     * @return list<array<string, mixed>>
     */
    protected function getConversationsForInstance(WhatsappInstance $instance, string $source = 'all', string $search = ''): array
    {
        $simSessionKey = 'sim:'.$instance->evolutionName();
        $realSessionKey = $instance->evolutionName();

        $sessionKeys = [];
        if ($source === 'all' || $source === 'sim') {
            $sessionKeys[] = $simSessionKey;
        }
        if ($source === 'all' || $source === 'real') {
            $sessionKeys[] = $realSessionKey;
        }

        $query = WhatsappMessage::withoutGlobalScopes()
            ->whereIn('instance_name', $sessionKeys);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('user_phone', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $messages = $query->orderBy('created_at', 'desc')
            ->limit(1000)
            ->get();

        $threads = [];
        foreach ($messages as $msg) {
            $phone = (string) $msg->user_phone;
            if ($phone === '') {
                continue;
            }

            $isSim = str_starts_with((string) $msg->instance_name, 'sim:');
            $threadKey = ($isSim ? 'sim:' : 'real:').$phone;

            if (! isset($threads[$threadKey])) {
                $threads[$threadKey] = [
                    'id' => $threadKey,
                    'phone' => $phone,
                    'source' => $isSim ? 'sim' : 'real',
                    'source_label' => $isSim ? 'Simulador' : 'WhatsApp Real',
                    'customer_name' => null,
                    'last_message' => mb_substr((string) ($msg->content ?? ''), 0, 100),
                    'last_role' => $msg->role,
                    'last_at' => $msg->created_at ? $msg->created_at->toIso8601String() : null,
                    'last_at_formatted' => $msg->created_at ? $msg->created_at->diffForHumans() : '',
                    'message_count' => 0,
                    'booking_stage' => null,
                ];
            }

            $threads[$threadKey]['message_count']++;
        }

        // Enriquecer con nombres de cliente desde BookingState u Orders
        foreach ($threads as &$thread) {
            $phone = $thread['phone'];
            $sessionKey = $thread['source'] === 'real' ? $realSessionKey : $simSessionKey;

            $booking = WhatsappBookingState::withoutGlobalScopes()
                ->where('instance_name', $sessionKey)
                ->where('user_phone', $phone)
                ->first();

            if ($booking && ! empty($booking->name)) {
                $thread['customer_name'] = $booking->name;
                $thread['booking_stage'] = $booking->step ?? null;
            } elseif (! empty($instance->company_id)) {
                $order = Order::where('company_id', (string) $instance->company_id)
                    ->where('customer_phone', $phone)
                    ->orderBy('created_at', 'desc')
                    ->first();
                if ($order && ! empty($order->customer_name)) {
                    $thread['customer_name'] = $order->customer_name;
                }
            }
        }
        unset($thread);

        return array_values($threads);
    }
}
