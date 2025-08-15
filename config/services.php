<?php

return [
    'sleeper' => [
        // Per-endpoint TTLs in seconds
        'ttl' => [
            'user' => env('SLEEPER_TTL_USER', 86400),
            'league' => env('SLEEPER_TTL_LEAGUE', 300),
            'rosters' => env('SLEEPER_TTL_ROSTERS', 120),
            'matchups' => env('SLEEPER_TTL_MATCHUPS', 60),
            'transactions' => env('SLEEPER_TTL_TRANSACTIONS', 60),
            'drafts' => env('SLEEPER_TTL_DRAFTS', 86400),
            'draft_picks' => env('SLEEPER_TTL_DRAFT_PICKS', 86400),
            'players_catalog' => env('SLEEPER_TTL_PLAYERS', 86400),
            'players_trending' => env('SLEEPER_TTL_TRENDING', 900),
            'projections' => env('SLEEPER_TTL_PROJECTIONS', 600),
            'adp' => env('SLEEPER_TTL_ADP', 86400),
            'state' => env('SLEEPER_TTL_STATE', 300),
        ],
        'retry' => [
            'max_attempts' => env('SLEEPER_RETRY_ATTEMPTS', 3),
            'base_ms' => env('SLEEPER_RETRY_BASE_MS', 200),
            'max_ms' => env('SLEEPER_RETRY_MAX_MS', 2000),
        ],
    ],

    'espn' => [
        'ttl' => [
            'core_athletes' => env('ESPN_TTL_CORE_ATHLETES', 86400),
            'fantasy_players' => env('ESPN_TTL_FANTASY_PLAYERS', 3600),
        ],
        'retry' => [
            'max_attempts' => env('ESPN_RETRY_ATTEMPTS', 3),
            'base_ms' => env('ESPN_RETRY_BASE_MS', 200),
            'max_ms' => env('ESPN_RETRY_MAX_MS', 2000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
