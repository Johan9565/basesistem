<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\BookingStateRepository;
use App\Services\Whatsapp\ConversationRepository;
use App\Services\Whatsapp\EvolutionApiClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConversationsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canSeeAllCompanies = $user ? $user->canSelectAllCompanies() : false;
        $accessibleCompanyIds = $user ? $user->getAccessibleCompanyIds() : [];

        $query = WhatsappInstance::withoutGlobalScopes()->with('company');
        if (! $canSeeAllCompanies) {
            $searchVals = [];
            foreach ($accessibleCompanyIds as $id) {
                $searchVals[] = (string) $id;
                try {
                    $searchVals[] = new \MongoDB\BSON\ObjectId((string) $id);
                } catch (\Throwable $e) {}
            }
            $query->whereIn('company_id', array_values(array_unique($searchVals)));
        }

        $instances = $query
            ->orderBy('instance_name')
            ->get()
            ->map(function (WhatsappInstance $row) {
                $name = (string) $row->instance_name;
                $sessionKey = $row->evolutionName();
                $instanceVariants = array_values(array_unique(array_filter([
                    $row->instance_name,
                    'sim:'.$row->instance_name,
                    $sessionKey,
                    'sim:'.$sessionKey,
                ])));

                $messages = WhatsappMessage::withoutGlobalScopes()
                    ->whereIn('instance_name', $instanceVariants)
                    ->orderBy('_id', 'desc')
                    ->limit(500)
                    ->get();

                $phones = $messages
                    ->pluck('user_phone')
                    ->filter()
                    ->unique()
                    ->values()
                    ->count();

                $last = $messages->first();

                return [
                    'instance_name' => $name,
                    'company_name' => $row->company?->name ?? '',
                    'status' => (string) ($row->status ?? 'active'),
                    'thread_count' => $phones,
                    'message_count' => $messages->count(),
                    'last_at' => $last?->created_at?->toIso8601String(),
                    'last_preview' => $last
                        ? mb_substr((string) ($last->content ?? ''), 0, 80)
                        : null,
                ];
            })
            ->values();

        return Inertia::render('Whatsapp/Conversations/Index', [
            'instances' => $instances,
            'canSeeAllCompanies' => $canSeeAllCompanies,
        ]);
    }

    public function show(Request $request, string $instance, BookingStateRepository $bookingStates)
    {
        $user = $request->user();
        $canSeeAllCompanies = $user ? $user->canSelectAllCompanies() : false;
        $accessibleCompanyIds = $user ? $user->getAccessibleCompanyIds() : [];

        $row = WhatsappInstance::withoutGlobalScopes()->where('instance_name', $instance)->first();
        if (! $row) {
            abort(404);
        }

        if (! $canSeeAllCompanies && ! in_array((string) $row->company_id, $accessibleCompanyIds, true)) {
            abort(403, 'No tienes permiso para ver las conversaciones de esta instancia.');
        }

        $sessionKey = $row->evolutionName();
        $instanceVariants = array_values(array_unique(array_filter([
            $row->instance_name,
            'sim:'.$row->instance_name,
            $sessionKey,
            'sim:'.$sessionKey,
        ])));

        // Obtenemos todos los mensajes ordenados cronológicamente
        $all = WhatsappMessage::withoutGlobalScopes()
            ->whereIn('instance_name', $instanceVariants)
            ->orderBy('_id', 'asc')
            ->get();

        // Agrupar mensajes por teléfono/contacto único
        $grouped = $all->groupBy(fn (WhatsappMessage $m) => (string) ($m->user_phone ?? ''))
            ->filter(fn ($group, $phone) => trim((string) $phone) !== '');

        // Obtener todos los booking states para esta instancia para evitar N+1 queries
        $bookingStatesMap = \App\Models\WhatsappBookingState::withoutGlobalScopes()
            ->whereIn('instance_name', $instanceVariants)
            ->get()
            ->keyBy(fn ($b) => (string) $b->user_phone);

        $threads = $grouped
            ->map(function ($group, $phone) use ($sessionKey, $bookingStates, $bookingStatesMap) {
                /** @var \Illuminate\Support\Collection $group */
                // Buscar el último mensaje humano (descartando tool calls internos de sistema para un preview limpio)
                $conversational = $group->filter(fn (WhatsappMessage $m) => 
                    in_array($m->role, ['user', 'assistant'], true) && 
                    (filled($m->content) || ! empty(data_get($m->metadata, 'image_url')))
                );
                /** @var WhatsappMessage|null $last */
                $last = $conversational->last() ?? $group->last();

                /** @var \App\Models\WhatsappBookingState|null $booking */
                $booking = $bookingStatesMap->get((string) $phone) 
                    ?? $bookingStates->find($sessionKey, (string) $phone);

                $contactName = $booking?->name;
                if (empty($contactName)) {
                    $pushNameMsg = $group->last(fn ($m) => ! empty(data_get($m->metadata, 'push_name')));
                    $contactName = data_get($pushNameMsg?->metadata, 'push_name');
                }

                $lastAt = $last?->created_at 
                    ?? ($last?->_id instanceof \MongoDB\BSON\ObjectId ? $last->_id->getDateTime() : null);

                $lastContent = (string) ($last?->content ?? '');
                if ($lastContent === '' && ! empty(data_get($last?->metadata, 'image_url'))) {
                    $lastContent = '📷 ' . (data_get($last?->metadata, 'item_name') ?: 'Fotografía');
                }

                return [
                    'user_phone' => (string) $phone,
                    'contact_name' => $contactName ?: null,
                    'message_count' => $group->count(),
                    'last_role' => (string) ($last?->role ?? ''),
                    'last_id' => (string) ($last?->_id ?? ''),
                    'last_content' => mb_substr($lastContent, 0, 100),
                    'last_at' => $lastAt ? \Carbon\Carbon::parse($lastAt)->toIso8601String() : null,
                    'last_time' => $lastAt ? \Carbon\Carbon::parse($lastAt)->format('H:i') : '',
                    'last_date' => $lastAt ? \Carbon\Carbon::parse($lastAt)->format('d/m/Y') : '',
                    'is_last_outgoing' => ($last?->role ?? '') === 'assistant',
                    'booking' => $booking
                        ? $bookingStates->toAdminArray($booking)
                        : null,
                ];
            })
            ->sort(function ($a, $b) {
                $cmp = strcmp((string) ($b['last_at'] ?? ''), (string) ($a['last_at'] ?? ''));
                if ($cmp !== 0) {
                    return $cmp;
                }
                return strcmp((string) ($b['last_id'] ?? ''), (string) ($a['last_id'] ?? ''));
            })
            ->values();

        $phoneFilter = (string) $request->query('phone', '');

        // Si no se proporcionó teléfono y hay hilos de conversación, seleccionar automáticamente el más reciente
        if ($phoneFilter === '' && $threads->isNotEmpty()) {
            $phoneFilter = (string) $threads->first()['user_phone'];
        }

        $messagesQuery = $all;
        if ($phoneFilter !== '' && $phoneFilter !== 'all') {
            $messagesQuery = $all->where('user_phone', $phoneFilter)->values();
        }

        $messages = $messagesQuery
            ->map(function (WhatsappMessage $m) {
                $createdAt = $m->created_at 
                    ?? ($m->_id instanceof \MongoDB\BSON\ObjectId ? $m->_id->getDateTime() : null);

                return [
                    'id' => (string) $m->getKey(),
                    'user_phone' => (string) ($m->user_phone ?? ''),
                    'role' => (string) ($m->role ?? ''),
                    'is_incoming' => $m->role === 'user',
                    'is_outgoing' => $m->role === 'assistant',
                    'is_tool' => $m->role === 'tool',
                    'content' => (string) ($m->content ?? ''),
                    'tool_name' => (string) ($m->tool_name ?? ''),
                    'metadata' => is_array($m->metadata) ? $m->metadata : null,
                    'created_at' => $createdAt ? \Carbon\Carbon::parse($createdAt)->toIso8601String() : null,
                    'time' => $createdAt ? \Carbon\Carbon::parse($createdAt)->format('H:i') : '',
                    'date' => $createdAt ? \Carbon\Carbon::parse($createdAt)->format('d/m/Y') : '',
                ];
            })
            ->values()
            ->all();

        $selectedBooking = null;
        if ($phoneFilter !== '' && $phoneFilter !== 'all') {
            $state = $bookingStatesMap->get($phoneFilter) 
                ?? $bookingStates->find($sessionKey, $phoneFilter);
            $selectedBooking = $state ? $bookingStates->toAdminArray($state) : null;
        }

        return Inertia::render('Whatsapp/Conversations/Show', [
            'instance' => [
                'instance_name' => (string) $row->instance_name,
                'evolution_instance_name' => $sessionKey,
                'status' => (string) ($row->status ?? 'active'),
                'business_name' => (string) ($row->business_name ?? ''),
            ],
            'threads' => $threads,
            'messages' => $messages,
            'booking' => $selectedBooking,
            'filters' => [
                'phone' => $phoneFilter,
            ],
        ]);
    }

    public function resumeBot(
        string $instance,
        string $phone,
        BookingStateRepository $bookingStates,
        EvolutionApiClient $evolution,
    ) {
        $row = WhatsappInstance::withoutGlobalScopes()->where('instance_name', $instance)->first();
        if (! $row) {
            abort(404);
        }

        $normalized = $evolution->normalizePhone($phone);
        $bookingStates->resumeBot($row->evolutionName(), $normalized);

        return redirect()
            ->route('whatsapp.conversations.show', [
                'instance' => $instance,
                'phone' => $normalized,
            ])
            ->with('flash', [
                'type' => 'success',
                'message' => "Bot reactivado para {$normalized}. Volverá a responder automáticamente.",
            ]);
    }

    public function destroyThread(string $instance, string $phone, ConversationRepository $conversations, BookingStateRepository $bookingStates)
    {
        $row = WhatsappInstance::withoutGlobalScopes()->where('instance_name', $instance)->first();
        if (! $row) {
            abort(404);
        }

        $sessionKey = $row->evolutionName();
        $normalized = preg_replace('/\D+/', '', $phone) ?? $phone;

        // Historial puede estar bajo la sesión Evolution o prefijo sim: o nombre del perfil
        $deleted = $conversations->clearThread($sessionKey, $normalized);
        $deleted += $conversations->clearThread('sim:'.$sessionKey, $normalized);
        if ($sessionKey !== (string) $row->instance_name) {
            $deleted += $conversations->clearThread((string) $row->instance_name, $normalized);
            $deleted += $conversations->clearThread('sim:'.(string) $row->instance_name, $normalized);
        }

        $bookingStates->resetBookingFields($sessionKey, $normalized);
        $bookingStates->resetBookingFields('sim:'.$sessionKey, $normalized);
        if ($sessionKey !== (string) $row->instance_name) {
            $bookingStates->resetBookingFields((string) $row->instance_name, $normalized);
            $bookingStates->resetBookingFields('sim:'.(string) $row->instance_name, $normalized);
        }

        return redirect()
            ->route('whatsapp.conversations.show', ['instance' => $instance])
            ->with('flash', [
                'type' => 'success',
                'message' => "Conversación borrada ({$deleted} mensajes) y estado de cita reiniciado. El bot empieza limpio con {$normalized}.",
            ]);
    }

    public function destroyInstanceMessages(string $instance, ConversationRepository $conversations, BookingStateRepository $bookingStates)
    {
        $row = WhatsappInstance::withoutGlobalScopes()->where('instance_name', $instance)->first();
        if (! $row) {
            abort(404);
        }

        $sessionKey = $row->evolutionName();
        $aliases = array_values(array_unique(array_filter([
            (string) $row->instance_name,
            'sim:'.(string) $row->instance_name,
            'sim:'.$sessionKey,
        ])));

        $deleted = $conversations->clearInstance($sessionKey, $aliases);
        $reset = $bookingStates->resetAllForInstance($sessionKey);
        if ($sessionKey !== (string) $row->instance_name) {
            $reset += $bookingStates->resetAllForInstance((string) $row->instance_name);
        }

        return redirect()
            ->route('whatsapp.conversations.show', ['instance' => $instance])
            ->with('flash', [
                'type' => 'success',
                'message' => "Se borraron {$deleted} mensajes y se reiniciaron {$reset} estados de cita. El bot empieza limpio.",
            ]);
    }
}
