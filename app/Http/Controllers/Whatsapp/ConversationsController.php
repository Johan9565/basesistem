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
    public function index()
    {
        $instances = WhatsappInstance::query()
            ->orderBy('instance_name')
            ->get()
            ->map(function (WhatsappInstance $row) {
                $name = (string) $row->instance_name;
                $sessionKey = $row->evolutionName();
                $messages = WhatsappMessage::query()
                    ->where('instance_name', $sessionKey)
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
        ]);
    }

    public function show(Request $request, string $instance, BookingStateRepository $bookingStates)
    {
        $row = WhatsappInstance::query()->where('instance_name', $instance)->first();
        if (! $row) {
            abort(404);
        }

        $sessionKey = $row->evolutionName();
        $phoneFilter = (string) $request->query('phone', '');

        $all = WhatsappMessage::query()
            ->where('instance_name', $sessionKey)
            ->orderBy('_id', 'desc')
            ->limit(2000)
            ->get()
            ->reverse()
            ->values();

        $threads = $all
            ->groupBy(fn (WhatsappMessage $m) => (string) ($m->user_phone ?? ''))
            ->map(function ($group, $phone) use ($sessionKey, $bookingStates) {
                /** @var WhatsappMessage $last */
                $last = $group->last();
                $booking = $bookingStates->find($sessionKey, (string) $phone);

                return [
                    'user_phone' => (string) $phone,
                    'message_count' => $group->count(),
                    'last_role' => (string) ($last->role ?? ''),
                    'last_content' => mb_substr((string) ($last->content ?? ''), 0, 100),
                    'last_at' => $last->created_at?->toIso8601String(),
                    'booking' => $booking
                        ? $bookingStates->toAdminArray($booking)
                        : null,
                ];
            })
            ->filter(fn (array $t) => $t['user_phone'] !== '')
            ->sortByDesc('last_at')
            ->values();

        $messagesQuery = $all;
        if ($phoneFilter !== '') {
            $messagesQuery = $all->where('user_phone', $phoneFilter)->values();
        }

        $messages = $messagesQuery
            ->map(fn (WhatsappMessage $m) => [
                'id' => (string) $m->getKey(),
                'user_phone' => (string) ($m->user_phone ?? ''),
                'role' => (string) ($m->role ?? ''),
                'content' => (string) ($m->content ?? ''),
                'tool_name' => (string) ($m->tool_name ?? ''),
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $selectedBooking = null;
        if ($phoneFilter !== '') {
            $state = $bookingStates->find($sessionKey, $phoneFilter);
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
        $row = WhatsappInstance::query()->where('instance_name', $instance)->first();
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
        $row = WhatsappInstance::query()->where('instance_name', $instance)->first();
        if (! $row) {
            abort(404);
        }

        $sessionKey = $row->evolutionName();
        $normalized = preg_replace('/\D+/', '', $phone) ?? $phone;

        // Historial puede estar bajo la sesión Evolution o (legado) bajo el nombre del perfil.
        $deleted = $conversations->clearThread($sessionKey, $normalized);
        if ($sessionKey !== (string) $row->instance_name) {
            $deleted += $conversations->clearThread((string) $row->instance_name, $normalized);
        }

        $bookingStates->resetBookingFields($sessionKey, $normalized);
        if ($sessionKey !== (string) $row->instance_name) {
            $bookingStates->resetBookingFields((string) $row->instance_name, $normalized);
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
        $row = WhatsappInstance::query()->where('instance_name', $instance)->first();
        if (! $row) {
            abort(404);
        }

        $sessionKey = $row->evolutionName();
        $deleted = $conversations->clearInstance($sessionKey, [(string) $row->instance_name]);
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
