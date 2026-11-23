<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tells staff about server problems: always in the log, and by Telegram when a bot is set up.
 */
class StaffAlert
{
    public function send(string $text): void
    {
        Log::warning('[watchdog] '.$text);

        $token = config('guul.telegram.bot_token');
        $chat = config('guul.telegram.ops_chat_id');
        if (! is_string($token) || $token === '' || ! is_scalar($chat) || (string) $chat === '') {
            return;
        }
        try {
            $response = Http::timeout(10)->asForm()->post(
                'https://api.telegram.org/bot'.$token.'/sendMessage',
                ['chat_id' => (string) $chat, 'text' => 'Guul 12: '.$text],
            );
            if (! $response->successful()) {
                // Never log the URL (it contains the bot token).
                Log::error('[watchdog] Telegram alert failed with status '.$response->status().'.');
            }
        } catch (\Throwable) {
            Log::error('[watchdog] Telegram alert could not be sent.');
        }
    }
}
