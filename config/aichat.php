<?php

return [
    // Languages the menu bot can speak. Add more here later (and in app/Bot/Strings.php).
    'languages' => ['en', 'zh'],

    // WhatsApp shows at most 10 rows in a list message and 3 reply buttons.
    // Titles: 24 characters in a list row, 20 on a button.
    'limits' => [
        'options_per_node' => 9, // one slot is kept for the automatic Back button
        'label_list' => 24,
        'label_button' => 20,
        'text' => 1024,
    ],

    // How the lead level is shown to staff. The saved values stay hot, warm and cold; change the words here.
    'lead_levels' => ['hot' => 'Ready to book', 'warm' => 'Interested', 'cold' => 'Just browsing'],

    // Typed words the bot understands in any step (lower case, compared after cleaning).
    'keywords' => [
        'menu' => ['menu', 'main menu', 'start', 'start over', '0', '菜单', '主菜单'],
        'back' => ['back', 'go back', 'previous', '上一步', '返回'],
        'human' => ['human', 'agent', 'staff', 'person', 'real person', 'talk to staff', 'customer service', '真人', '人工', '客服', '转人工'],
        'opt_out' => ['stop', 'unsubscribe', 'opt out', '退订', '取消订阅', '不要再发'],
    ],

    // A WhatsApp/Messenger/Instagram message written more than this many minutes ago (delivered late by Meta)
    // is saved for staff but not answered by the bot.
    'stale_message_minutes' => 30,

    // After this many unmatched or invalid replies in a row the bot hands over to staff.
    'max_misses' => 2,

    // Minutes after a handover when staff have not replied: holding message,
    // reminder, reassign, owner alert. The owner can change these per business later.
    'handover_timeouts' => [5, 10, 20, 30],

    // AI mode (step 5b). The provider is a platform setting; each business chooses its own mode in Settings > AI.
    //   off    no AI anywhere (the default, and what you pay nothing for)
    //   fake   a built-in pretend AI for trying the screens and for tests (no internet, no cost)
    //   claude the real thing, needs ANTHROPIC_API_KEY
    'ai' => [
        'provider' => env('AI_PROVIDER', 'off'),
        'model' => env('AI_MODEL', 'claude-haiku-5-5'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'max_tokens' => 700,
        'history_messages' => 8,        // how much of the chat the AI reads
        'replies_per_conversation_per_hour' => 6,
        'default_monthly_cap' => 300,   // AI calls per business per month; the owner can change it
        // Rough prices in US dollars per million tokens, only to show an estimate (Claude Haiku 5.5, short prompts).
        'price_input_per_million' => 0.10,
        'price_output_per_million' => 0.50,
    ],

    // Phone and desktop alerts when the app is closed (Web Push). Make the keys with: php artisan aichat:push-keys
    'push' => [
        'public' => env('VAPID_PUBLIC_KEY'),
        'private' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'hello@example.com')),
    ],
];
