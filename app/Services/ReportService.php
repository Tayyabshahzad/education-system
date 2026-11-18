<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Monthly chart series and period reports.
 *
 * Every figure is dated by the moment it became final, so the dashboard charts, the
 * report summary and the report tables always tally with each other:
 *  - members joined   -> users.created_at (member role)
 *  - sales            -> approved payments, by reviewed_at
 *  - commission       -> commission wallet credits, by created_at
 *  - withdrawals      -> approved withdrawals, by reviewed_at
 *
 * Grouping by month is done in PHP so it behaves the same on MySQL and SQLite.
 */
class ReportService
{
    public const TYPES = [
        'members' => 'Members joined',
        'payments' => 'Sales (payments)',
        'commissions' => 'Commissions',
        'withdrawals' => 'Withdrawals',
    ];

    /* ------------------------------------------------------------------
     | Dashboard charts
     * ------------------------------------------------------------------ */

    /** A user's own figures: their whole commission-level team joining, commission earned and money withdrawn. */
    public function personalChart(User $user, array $levels, int $months = 12): array
    {
        $start = $this->start($months);
        $team = collect($levels)->flatten(1)->filter(fn (User $u) => $u->created_at >= $start);

        return $this->chart($months, [
            'team' => [
                'label' => 'Team joined', 'format' => 'count', 'unit' => 'member',
                'values' => $this->bucket($team, 'created_at', null, $months),
            ],
            'commission' => [
                'label' => 'Commission', 'format' => 'money', 'unit' => 'commission',
                'values' => $this->bucket(
                    $user->walletTransactions()->where('type', 'commission')->where('created_at', '>=', $start)->get(['amount', 'created_at']),
                    'created_at', 'amount', $months,
                ),
            ],
            'withdrawn' => [
                'label' => 'Withdrawn', 'format' => 'money', 'unit' => 'withdrawal',
                'values' => $this->bucket(
                    $user->withdrawals()->where('status', 'approved')->where('reviewed_at', '>=', $start)->get(['amount', 'reviewed_at']),
                    'reviewed_at', 'amount', $months,
                ),
            ],
        ]);
    }

    /** Whole-business figures for the admin dashboard. */
    public function businessChart(int $months = 12): array
    {
        $start = $this->start($months);

        return $this->chart($months, [
            'members' => [
                'label' => 'Members joined', 'format' => 'count', 'unit' => 'member',
                'values' => $this->bucket(User::role(User::ROLE_MEMBER)->where('created_at', '>=', $start)->get(['id', 'created_at']), 'created_at', null, $months),
            ],
            'sales' => [
                'label' => 'Sales', 'format' => 'money', 'unit' => 'payment',
                'values' => $this->bucket(Payment::where('status', 'approved')->where('reviewed_at', '>=', $start)->get(['amount', 'reviewed_at']), 'reviewed_at', 'amount', $months),
            ],
            'commission' => [
                'label' => 'Commission paid', 'format' => 'money', 'unit' => 'commission',
                'values' => $this->bucket(WalletTransaction::where('type', 'commission')->where('created_at', '>=', $start)->get(['amount', 'created_at']), 'created_at', 'amount', $months),
            ],
            'withdrawals' => [
                'label' => 'Withdrawals', 'format' => 'money', 'unit' => 'withdrawal',
                'values' => $this->bucket(Withdrawal::where('status', 'approved')->where('reviewed_at', '>=', $start)->get(['amount', 'reviewed_at']), 'reviewed_at', 'amount', $months),
            ],
        ]);
    }

    /* ------------------------------------------------------------------
     | Reports page
     * ------------------------------------------------------------------ */

    /** Totals for a date range (both ends inclusive). */
    public function summary(Carbon $from, Carbon $to): array
    {
        $members = $this->query('members', $from, $to);
        $payments = $this->query('payments', $from, $to);
        $byMethod = (clone $payments)->setEagerLoads([])->reorder()->selectRaw('method, COUNT(*) as c, SUM(amount) as total')->groupBy('method')->get()->keyBy('method');
        $method = fn (string $m) => (float) ($byMethod[$m]->total ?? 0);

        $cashIn = $method('proof') + $method('admin');
        $withdrawn = (float) $this->query('withdrawals', $from, $to)->sum('amount');

        return [
            'members' => (clone $members)->count(),
            'membersFullyPaid' => (clone $members)->fullyPaid()->count(),
            'membersActive' => (clone $members)->where('status', 'active')->count(),
            'sales' => $cashIn + $method('wallet'),
            'salesCount' => (int) $byMethod->sum('c'),
            'salesProof' => $method('proof'),
            'salesAdmin' => $method('admin'),
            'salesWallet' => $method('wallet'),
            'cashIn' => $cashIn,
            'commission' => (float) $this->query('commissions', $from, $to)->sum('amount'),
            'commissionCount' => $this->query('commissions', $from, $to)->count(),
            'withdrawn' => $withdrawn,
            'withdrawnCount' => $this->query('withdrawals', $from, $to)->count(),
            'netCash' => $cashIn - $withdrawn,
            // Snapshots (not limited to the date range).
            'pendingPayments' => (float) Payment::where('status', 'pending')->sum('amount'),
            'pendingWithdrawals' => (float) Withdrawal::where('status', 'pending')->sum('amount'),
            'walletBalances' => (float) User::sum('wallet_balance'),
        ];
    }

    /** The records behind each report tab, newest first. */
    public function query(string $type, Carbon $from, Carbon $to): Builder
    {
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        return match ($type) {
            'members' => User::role(User::ROLE_MEMBER)
                ->with(['sponsor:id,name,username', 'sponsor.roles:id,name'])
                ->whereBetween('created_at', $range)
                ->latest()->latest('id'),
            'payments' => Payment::with(['user:id,name,username,phone', 'payer:id,username', 'reviewer:id,name'])
                ->where('status', 'approved')
                ->whereBetween('reviewed_at', $range)
                ->latest('reviewed_at')->latest('id'),
            'commissions' => WalletTransaction::with(['user:id,name,username', 'fromUser:id,name,username'])
                ->where('type', 'commission')
                ->whereBetween('created_at', $range)
                ->latest()->latest('id'),
            'withdrawals' => Withdrawal::with(['user:id,name,username,phone', 'reviewer:id,name'])
                ->where('status', 'approved')
                ->whereBetween('reviewed_at', $range)
                ->latest('reviewed_at')->latest('id'),
        };
    }

    /** CSV header + one row per record, used for the month-end export. */
    public function csvColumns(string $type): array
    {
        $date = fn ($d) => $d?->format('Y-m-d H:i');

        return match ($type) {
            'members' => [
                'Joined' => fn (User $u) => $date($u->created_at),
                'Name' => fn (User $u) => $u->name,
                'Username' => fn (User $u) => $u->username,
                'Email' => fn (User $u) => $u->email,
                'Mobile' => fn (User $u) => $u->phone,
                'CNIC' => fn (User $u) => $u->cnic,
                'Country' => fn (User $u) => $u->country,
                'Sponsor' => fn (User $u) => $u->sponsor?->username,
                'Sponsor role' => fn (User $u) => $u->sponsor ? User::roleLabel($u->sponsor->primaryRole()) : null,
                'Joining amount' => fn (User $u) => $u->joining_amount,
                'Paid so far' => fn (User $u) => $u->paid_amount,
                'Status' => fn (User $u) => $u->status,
                'Referral code' => fn (User $u) => $u->referral_code,
            ],
            'payments' => [
                'Approved' => fn (Payment $p) => $date($p->reviewed_at),
                'Submitted' => fn (Payment $p) => $date($p->created_at),
                'Member' => fn (Payment $p) => $p->user?->name,
                'Username' => fn (Payment $p) => $p->user?->username,
                'Mobile' => fn (Payment $p) => $p->user?->phone,
                'Amount' => fn (Payment $p) => $p->amount,
                'Method' => fn (Payment $p) => $p->methodLabel(),
                'Paid by' => fn (Payment $p) => $p->payer?->username,
                'Reference' => fn (Payment $p) => $p->reference,
                'Approved by' => fn (Payment $p) => $p->reviewer?->name,
                'Payment ID' => fn (Payment $p) => $p->id,
            ],
            'commissions' => [
                'Date' => fn (WalletTransaction $t) => $date($t->created_at),
                'Earned by' => fn (WalletTransaction $t) => $t->user?->name,
                'Earner username' => fn (WalletTransaction $t) => $t->user?->username,
                'From member' => fn (WalletTransaction $t) => $t->fromUser?->username,
                'Level' => fn (WalletTransaction $t) => $t->level,
                'Amount' => fn (WalletTransaction $t) => $t->amount,
                'Transaction ID' => fn (WalletTransaction $t) => $t->id,
            ],
            'withdrawals' => [
                'Paid' => fn (Withdrawal $w) => $date($w->reviewed_at),
                'Requested' => fn (Withdrawal $w) => $date($w->created_at),
                'Member' => fn (Withdrawal $w) => $w->user?->name,
                'Username' => fn (Withdrawal $w) => $w->user?->username,
                'Amount' => fn (Withdrawal $w) => $w->amount,
                'Method' => fn (Withdrawal $w) => $w->method,
                'Bank' => fn (Withdrawal $w) => $w->bank_name,
                'Account title' => fn (Withdrawal $w) => $w->account_title,
                'Account number' => fn (Withdrawal $w) => $w->account_number,
                'Transfer reference' => fn (Withdrawal $w) => $w->payout_reference,
                'Approved by' => fn (Withdrawal $w) => $w->reviewer?->name,
                'Withdrawal ID' => fn (Withdrawal $w) => $w->id,
            ],
        };
    }

    /* ------------------------------------------------------------------
     | Helpers
     * ------------------------------------------------------------------ */

    private function start(int $months): Carbon
    {
        return now()->startOfMonth()->subMonths($months - 1);
    }

    /** @return list<array{key: string, label: string, full: string}> */
    private function monthList(int $months): array
    {
        $start = $this->start($months);

        return array_map(function (int $i) use ($start) {
            $m = $start->copy()->addMonths($i);

            return ['key' => $m->format('Y-m'), 'label' => $m->format('M'), 'full' => $m->format('M Y')];
        }, range(0, $months - 1));
    }

    /**
     * Per-month value and record count, aligned with monthList().
     *
     * @return list<array{value: float, count: int}>
     */
    private function bucket(Collection $rows, string $dateField, ?string $sumField, int $months): array
    {
        $grouped = $rows->filter(fn ($r) => $r->{$dateField})->groupBy(fn ($r) => $r->{$dateField}->format('Y-m'));

        return array_map(function (array $month) use ($grouped, $sumField) {
            $items = $grouped->get($month['key'], collect());

            return [
                'value' => $sumField ? round((float) $items->sum($sumField), 2) : (float) $items->count(),
                'count' => $items->count(),
            ];
        }, $this->monthList($months));
    }

    private function chart(int $months, array $metrics): array
    {
        return ['months' => $this->monthList($months), 'metrics' => $metrics];
    }
}
