<?php

namespace App\Support;

/**
 * Every permission in the system, grouped for the staff-permission screen.
 * The super-admin role bypasses all checks (see AppServiceProvider Gate::before).
 */
class Permissions
{
    public const GROUPS = [
        'Members' => [
            'members.view' => 'View members & genealogy',
            'members.create' => 'Create members (under any sponsor)',
            'members.edit' => 'Edit members & change sponsor',
            'members.status' => 'Activate / block members',
            'members.delete' => 'Delete members',
        ],
        'Payments' => [
            'payments.view' => 'View payments',
            'payments.review' => 'Approve / reject payments',
        ],
        'Withdrawals' => [
            'withdrawals.view' => 'View withdrawal requests',
            'withdrawals.review' => 'Approve / reject withdrawals',
        ],
        'Classes' => [
            'batches.manage' => 'Create batches & assign members',
            'teachers.manage' => 'Add / edit teachers',
        ],
        'System' => [
            'settings.manage' => 'Change fees, commission & settings',
            'staff.manage' => 'Manage staff accounts & permissions',
        ],
    ];

    /** Sensible starting permissions for a new staff account. */
    public const STAFF_DEFAULTS = [
        'members.view', 'members.create', 'members.status',
        'payments.view', 'payments.review',
        'withdrawals.view',
        'batches.manage',
    ];

    public static function all(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::GROUPS)));
    }
}
