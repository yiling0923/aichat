<?php

// The plans a business can choose. Prices are in US cents per month. Edit freely: limits are read from here.
// A limit of null means unlimited.
return [
    'currency' => 'USD',
    'trial_days' => 14,
    'provider' => env('BILLING_PROVIDER', 'local'),   // local = test mode, nobody is charged

    'plans' => [
        'trial' => [
            'name' => 'Free trial', 'price_cents' => 0,
            'limits' => ['ai' => 100, 'staff' => 2, 'practitioners' => 2, 'channels' => 1],
        ],
        'starter' => [
            'name' => 'Starter', 'price_cents' => 2900,
            'limits' => ['ai' => 500, 'staff' => 3, 'practitioners' => 3, 'channels' => 2],
        ],
        'growth' => [
            'name' => 'Growth', 'price_cents' => 7900,
            'limits' => ['ai' => 3000, 'staff' => 10, 'practitioners' => 10, 'channels' => 5],
        ],
    ],

    'labels' => ['ai' => 'AI replies this month', 'staff' => 'Team members', 'practitioners' => 'Practitioners / resources', 'channels' => 'Connected channels'],
];
