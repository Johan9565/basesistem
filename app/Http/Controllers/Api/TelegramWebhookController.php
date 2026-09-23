<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappInstance;
use App\Services\Telegram\AdminAssistantOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $instance,
        AdminAssistantOrchestrator $orchestrator,
    ): JsonResponse {
        /** @var WhatsappInstance|null $row */
        $row = $request->attributes->get('whatsapp_instance');

        if (! $row instanceof WhatsappInstance) {
            $row = WhatsappInstance::query()->where('instance_name', $instance)->first();
        }

        if (! $row || ($row->status ?? '') !== 'active') {
            return response()->json(['ok' => true, 'ignored' => true, 'reason' => 'inactive_or_missing']);
        }

        if (trim((string) ($row->telegram_bot_token ?? '')) === '') {
            return response()->json(['ok' => true, 'ignored' => true, 'reason' => 'no_telegram_token']);
        }

        $message = $request->input('message');
        if (! is_array($message)) {
            return response()->json(['ok' => true, 'ignored' => true, 'reason' => 'not_message']);
        }

        $chatId = (int) data_get($message, 'chat.id', 0);
        $userId = (int) data_get($message, 'from.id', 0);
        $text = trim((string) (data_get($message, 'text') ?? data_get($message, 'caption') ?? ''));

        if ($chatId === 0 || $userId === 0 || $text === '') {
            return response()->json(['ok' => true, 'ignored' => true, 'reason' => 'incomplete']);
        }

        // Solo chats privados admin ↔ bot.
        $chatType = (string) data_get($message, 'chat.type', 'private');
        if ($chatType !== 'private') {
            return response()->json(['ok' => true, 'ignored' => true, 'reason' => 'not_private']);
        }

        try {
            $orchestrator->handleIncoming($row, $chatId, $userId, $text);
        } catch (Throwable $e) {
            Log::error('Telegram admin webhook failed', [
                'instance' => $instance,
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
