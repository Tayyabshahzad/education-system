<?php

return [
    // Joining packages a new member can choose at registration (USD).
    'packages' => [
        'basic' => ['label' => 'Join Fee', 'amount' => 25],
        'full'  => ['label' => 'Full Training Package', 'amount' => 200],
    ],

    // A member's uplines earn commission only once the member has paid at least this much.
    'commission_qualifying_amount' => 200,

    // Fixed USD commission per upline level (level 1 = direct sponsor).
    'commission_levels' => [
        1 => 30,
        2 => 20,
        3 => 10,
    ],
];
