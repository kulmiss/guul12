<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tells staff about server problems: always in the log, and by Telegram when a bot is set up.
 */
class StaffAlert
{
    /** True when a bot token and a chat are set, so alerts also go to Telegram. */
    public function isConfigured(): bool
    {
        $token = config('guul.telegram.bot_token');
        $chat = config('guul.telegram.ops_chat_id');

        return is_string($token) && $token !== '' && is_scalar($chat) && (string) $chat !== '';
    }

    /** Returns true when Telegram accepted the message (false: not set up, or it failed; see the log). */
    public function send(string $text): bool
    {
        Log::warning('[watchdog] '.$text);

        if (! $this->isConfigured()) {
            return false;
        }
        $token = (string) config('guul.telegram.bot_token');
        $chat = config('guul.telegram.ops_chat_id');
        try {
            $response = Http::timeout(10)->asForm()->post(
                'https://api.telegram.org/bot'.$token.'/sendMessage',
                ['chat_id' => is_scalar($chat) ? (string) $chat : '', 'text' => 'Guul 12: '.$text],
            );
            if (! $response->successful()) {
                // Never log the URL (it contains the bot token).
                Log::error('[watchdog] Telegram alert failed with status '.$response->status().'.');

                return false;
            }
        } catch (\Throwable) {
            Log::error('[watchdog] Telegram alert could not be sent.');

            return false;
        }

        return true;
    }
}
