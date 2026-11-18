<?php

/*
| Default business settings. Admins can override every value below from
| Admin > Settings; overrides are stored in the `settings` table and read via
| App\Models\Setting::get(). Always read through Setting, never config() directly.
*/

return [
    'defaults' => [
        // Lowest first payment a member can make to join (USD).
        'min_join_fee' => 25,

        // Paid total at which a member counts as "fully paid": uplines receive
        // commission and the member becomes eligible for class batches.
        'full_fee' => 200,

        // Highest total a member can pay (registration + top-ups).
        'max_fee' => 200,

        // Fixed USD commission per upline level (level 1 = direct sponsor).
        // Admins change these from Admin > Settings.
        'commission_level_1' => 30,
        'commission_level_2' => 20,
        'commission_level_3' => 10,
        'commission_level_4' => 5,
        'commission_level_5' => 5,
        'commission_level_6' => 5,
        'commission_level_7' => 5,

        // When true, an upline must be fully paid themselves to receive commission.
        'upline_must_be_full' => false,

        'min_withdrawal' => 10,

        // Shown to members on the payment page (bank / wallet account details).
        'payment_instructions' => "Bank: —\nAccount title: —\nAccount number: —",

        'withdrawal_methods' => 'Bank Transfer, Easypaisa, JazzCash, USDT (TRC20)',
    ],

    // How many upline levels earn commission, and how deep "My Team" and the
    // dashboards look. Every level needs a commission_level_N default above.
    'commission_levels' => 7,
];
