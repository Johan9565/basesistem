<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\BookingStateRepository;
use App\Services\Whatsapp\ConversationRepository;
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
                $messages = WhatsappMessage::query()
                    ->where('instance_name', $name)
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

    public function show(Request $request, string $instance)
    {
        $row = WhatsappInstance::query()->where('instance_name', $instance)->first();
        if (! $row) {
            abort(404);
        }

        $phoneFilter = (string) $request->query('phone', '');

        $all = WhatsappMessage::query()
            ->where('instance_name', $instance)
            ->orderBy('_id', 'desc')
            ->limit(2000)
            ->get()
            ->reverse()
            ->values();

        $threads = $all
            ->groupBy(fn (WhatsappMessage $m) => (string) ($m->user_phone ?? ''))
            ->map(function ($group, $phone) {
                /** @var WhatsappMessage $last */
                $last = $group->last();

                return [
                    'user_phone' => (string) $phone,
                    'message_count' => $group->count(),
                    'last_role' => (string) ($last->role ?? ''),
                    'last_content' => mb_substr((string) ($last->content ?? ''), 0, 100),
                    'last_at' => $last->created_at?->toIso8601String(),
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

        return Inertia::render('Whatsapp/Conversations/Show', [
            'instance' => [
                'instance_name' => (string) $row->instance_name,
                'status' => (string) ($row->status ?? 'active'),
            ],
            'threads' => $threads,
            'messages' => $messages,
            'filters' => [
                'phone' => $phoneFilter,
            ],
        ]);
    }

    public function destroyThread(string $instance, string $phone, ConversationRepository $conversations, BookingStateRepository $bookingStates)
    {
        $exists = WhatsappInstance::query()->where('instance_name', $instance)->exists();
        if (! $exists) {
            abort(404);
        }

        $deleted = $conversations->clearThread($instance, $phone);
        $bookingStates->resetBookingFields($instance, preg_replace('/\D+/', '', $phone) ?? $phone);

        return redirect()
            ->route('whatsapp.conversations.show', ['instance' => $instance])
            ->with('flash', [
                'type' => 'success',
                'message' => "Conversación borrada ({$deleted} mensajes). El bot empezará limpio con ese número.",
            ]);
    }

    public function destroyInstanceMessages(string $instance, ConversationRepository $conversations)
    {
        $exists = WhatsappInstance::query()->where('instance_name', $instance)->exists();
        if (! $exists) {
            abort(404);
        }

        $deleted = $conversations->clearInstance($instance);

        return redirect()
            ->route('whatsapp.conversations.show', ['instance' => $instance])
            ->with('flash', [
                'type' => 'success',
                'message' => "Se borraron {$deleted} mensajes de la instancia.",
            ]);
    }
}
