<?php

// Operations settings (server .env). Nothing secret has a default here.
return [
    'backups' => [
        'path' => storage_path('app/backups'),
        'keep' => (int) env('GUUL_BACKUPS_KEEP', 14),
        // The watchdog complains when the newest backup is older than this.
        'max_age_hours' => (int) env('GUUL_BACKUPS_MAX_AGE_HOURS', 26),
        // Off on a developer PC (no backups there); on the server set it to true.
        'required' => (bool) env('GUUL_BACKUPS_REQUIRED', env('APP_ENV') === 'production'),
        'mysqldump' => env('GUUL_MYSQLDUMP', 'mysqldump'),
    ],
    'watchdog' => [
        'min_free_disk_percent' => (int) env('GUUL_MIN_FREE_DISK_PERCENT', 10),
        'ai_budget_warn_percent' => (int) env('GUUL_AI_BUDGET_WARN_PERCENT', 80),
    ],
    // Staff alerts by Telegram (optional). Empty = alerts only go to the log.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'ops_chat_id' => env('TELEGRAM_OPS_CHAT_ID'),
    ],
];
