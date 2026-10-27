<?php

return [
    // Languages the menu bot can speak. Add more here later (and in app/Bot/Strings.php).
    'languages' => ['en', 'zh'],

    // WhatsApp shows at most 10 rows in a list message and 3 reply buttons.
    // Titles: 24 characters in a list row, 20 on a button.
    'limits' => [
        'options_per_node' => 10,
        'label_list' => 24,
        'label_button' => 20,
        'text' => 1024,
    ],

    // Typed words the bot understands in any step (lower case, compared after cleaning).
    'keywords' => [
        'menu' => ['menu', 'main menu', 'start', 'start over', '0', '菜单', '主菜单', '返回'],
        'human' => ['human', 'agent', 'staff', 'person', 'real person', 'talk to staff', 'customer service', '真人', '人工', '客服', '转人工'],
        'opt_out' => ['stop', 'unsubscribe', 'opt out', '退订', '取消订阅', '不要再发'],
    ],

    // After this many unmatched or invalid replies in a row the bot hands over to staff.
    'max_misses' => 2,

    // Minutes after a handover when staff have not replied: holding message,
    // reminder, reassign, owner alert. The owner can change these per business later.
    'handover_timeouts' => [5, 10, 20, 30],
];
