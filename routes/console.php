<?php

use App\Models\HeldCommission;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CommissionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| First super admin on a fresh server (demo accounts are only seeded locally):
|   php artisan app:create-super-admin
| Asks for name, email and password; the username and referral code are generated.
*/
Artisan::command('app:create-super-admin {--name=} {--email=} {--password=}', function () {
    $data = [
        'name' => $this->option('name') ?: $this->ask('Full name'),
        'email' => strtolower(trim((string) ($this->option('email') ?: $this->ask('Email')))),
        'password' => $this->option('password') ?: $this->secret('Password (min 8 characters)'),
    ];

    $validator = Validator::make($data, [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', Password::defaults()],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = DB::transaction(function () use ($data) {
        $user = User::create($data);
        $user->forceFill(['status' => 'active', 'email_verified_at' => now()])->save();
        $user->assignRole(User::ROLE_SUPER_ADMIN);

        return $user;
    });

    $this->info("Super admin created. Log in with {$user->email} (username: {$user->username}, referral code: {$user->referral_code}).");

    return 0;
})->purpose('Create a super admin account (use once on a fresh server)');

/*
| Re-price commission already paid using the CURRENT percentages in Admin > Settings,
| e.g. after rates were changed but some commission had gone out at the old rates.
|   php artisan app:recalculate-commissions --since="2026-11-19"          (dry run: shows changes only)
|   php artisan app:recalculate-commissions --since="2026-11-19" --apply  (writes them)
| The commission records themselves are corrected (amount, description), then each affected
| wallet balance and every balance_after in that user's history are recomputed.
| Refuses to apply if a wallet would go negative (money already withdrawn) or a wallet
| does not match its own history.
*/
Artisan::command('app:recalculate-commissions {--since= : Only commission credited on/after this date or datetime} {--until= : Only commission credited up to this date or datetime} {--apply : Write the changes (default is a dry run)}', function () {
    if (! $this->option('since')) {
        $this->error('Give --since (e.g. --since="2026-11-19") so older, correctly priced commission is not touched.');

        return 1;
    }

    $query = WalletTransaction::where('type', 'commission')
        ->where('created_at', '>=', Carbon::parse($this->option('since')))
        ->when($this->option('until'), fn ($q, $until) => $q->where('created_at', '<=', strlen($until) <= 10 ? Carbon::parse($until)->endOfDay() : Carbon::parse($until)))
        ->with(['user', 'fromUser'])
        ->orderBy('id');

    $changes = [];
    foreach ($query->get() as $tx) {
        $member = $tx->fromUser;
        // What the member had paid when their commission was released: every approved
        // payment up to and including the one that triggered it.
        $base = $tx->source_type === (new Payment)->getMorphClass() && $tx->source_id
            ? (float) Payment::where('user_id', $member->id)->where('status', 'approved')->where('id', '<=', $tx->source_id)->sum('amount')
            : (float) $member->paid_amount;
        $percent = Setting::commissionLevels()[$tx->level] ?? 0;
        $new = Setting::commissionAmount((int) $tx->level, $base);

        if (abs($new - (float) $tx->amount) >= 0.005) {
            $changes[] = compact('tx', 'member', 'base', 'percent', 'new');
        }
    }

    if (! $changes) {
        $this->info('Nothing to change: all commission in that period already matches the current percentages.');

        return 0;
    }

    $this->table(
        ['Tx', 'Date', 'Earner', 'From member', 'Level', 'Paid base', 'Rate', 'Old $', 'New $', 'Diff $'],
        array_map(fn ($c) => [
            $c['tx']->id, $c['tx']->created_at->format('Y-m-d H:i'), $c['tx']->user->username, $c['member']->username,
            $c['tx']->level, number_format($c['base'], 2), ($c['percent'] + 0).'%',
            number_format((float) $c['tx']->amount, 2), number_format($c['new'], 2),
            number_format($c['new'] - (float) $c['tx']->amount, 2),
        ], $changes),
    );

    // Per-wallet effect and safety checks.
    $byUser = collect($changes)->groupBy(fn ($c) => $c['tx']->user_id);
    $problems = [];
    $rows = [];
    foreach ($byUser as $userId => $items) {
        $user = User::withTrashed()->find($userId);
        $diff = round($items->sum(fn ($c) => $c['new'] - (float) $c['tx']->amount), 2);
        $ledger = round((float) WalletTransaction::where('user_id', $userId)->sum('amount'), 2);
        $after = round((float) $user->wallet_balance + $diff, 2);
        $rows[] = [$user->username, number_format((float) $user->wallet_balance, 2), number_format($diff, 2), number_format($after, 2)];

        if (abs($ledger - (float) $user->wallet_balance) >= 0.005) {
            $problems[] = "{$user->username}: wallet ({$user->wallet_balance}) does not match its transaction history ({$ledger}).";
        }
        if ($after < 0) {
            $problems[] = "{$user->username}: wallet would become {$after} (already withdrawn). Correct this one manually.";
        }
    }
    $this->table(['Wallet', 'Now $', 'Change $', 'After $'], $rows);

    if ($problems) {
        foreach ($problems as $p) {
            $this->error($p);
        }
        $this->warn('Nothing was changed.');

        return 1;
    }

    if (! $this->option('apply')) {
        $this->warn('Dry run: nothing was changed. Run again with --apply to write these corrections.');

        return 0;
    }

    DB::transaction(function () use ($changes, $byUser) {
        foreach ($changes as $c) {
            $c['tx']->forceFill([
                'amount' => $c['new'],
                'description' => "Level {$c['tx']->level} commission (".($c['percent'] + 0).'% of $'.number_format($c['base'], 2).") from {$c['member']->username}",
            ])->save();
        }

        // Rebuild each affected wallet from its full history so every balance_after stays right.
        foreach ($byUser->keys() as $userId) {
            $user = User::withTrashed()->whereKey($userId)->lockForUpdate()->first();
            $running = 0.0;
            foreach (WalletTransaction::where('user_id', $userId)->orderBy('id')->lockForUpdate()->get() as $t) {
                $running = round($running + (float) $t->amount, 2);
                if (abs($running - (float) $t->balance_after) >= 0.005) {
                    $t->forceFill(['balance_after' => $running])->save();
                }
            }
            $user->forceFill(['wallet_balance' => $running])->save();
        }
    });

    $this->info(count($changes).' commission record(s) corrected and '.$byUser->count().' wallet(s) updated.');

    return 0;
})->purpose('Re-price commission already paid using the current percentages (dry run unless --apply)');

/*
| Apply the 7x7 rule to commission that was paid BEFORE the rule existed.
|   php artisan app:apply-7x7            (dry run: shows what would move to "waiting")
|   php artisan app:apply-7x7 --apply    (does it)
| For every upline and every level 2-7, with D = their active directs today:
|   level closed (D < level) -> all commission on that level goes back to waiting;
|   level open (D >= level) -> everything on that level stays paid.
| Moved commission leaves the wallet (the wallet history is rebuilt) and is paid back
| automatically, at the same amount, once the upline has enough active directs.
| Refuses to apply if a wallet would go negative (money already withdrawn) or a wallet
| does not match its own history. Running it twice changes nothing the second time.
*/
Artisan::command('app:apply-7x7 {--apply : Write the changes (default is a dry run)}', function () {
    $paymentClass = (new Payment)->getMorphClass();
    $moves = [];

    $earners = WalletTransaction::where('type', 'commission')->where('level', '>=', 2)->distinct()->pluck('user_id');
    foreach ($earners as $userId) {
        $user = User::withTrashed()->find($userId);
        $directs = $user->qualifiedDirectCount();

        $byLevel = WalletTransaction::where('user_id', $userId)->where('type', 'commission')->where('level', '>=', 2)
            ->with('fromUser')->orderBy('id')->get()->groupBy('level');

        foreach ($byLevel as $level => $txs) {
            if ($directs >= $level) {
                continue; // open level: all of it stays paid
            }
            foreach ($txs as $tx) {
                $member = $tx->fromUser;
                $base = $tx->source_type === $paymentClass && $tx->source_id && $member
                    ? (float) Payment::where('user_id', $member->id)->where('status', 'approved')->where('id', '<=', $tx->source_id)->sum('amount')
                    : (float) ($member?->paid_amount ?? 0);
                $moves[] = compact('tx', 'user', 'directs', 'base');
            }
        }
    }

    if (! $moves) {
        $this->info('Nothing to change: all level 2-7 commission already follows the 7x7 rule.');

        return 0;
    }

    $this->table(
        ['Tx', 'Date', 'Earner', 'Active directs', 'Level', 'From member', 'Amount $', 'Becomes'],
        array_map(fn ($m) => [
            $m['tx']->id, $m['tx']->created_at->format('Y-m-d H:i'), $m['user']->username, $m['directs'],
            $m['tx']->level, $m['tx']->fromUser?->username, number_format((float) $m['tx']->amount, 2),
            "waiting (needs {$m['tx']->level} directs)",
        ], $moves),
    );

    $byUser = collect($moves)->groupBy(fn ($m) => $m['user']->id);
    $problems = [];
    $rows = [];
    foreach ($byUser as $userId => $items) {
        $user = $items->first()['user'];
        $out = round($items->sum(fn ($m) => (float) $m['tx']->amount), 2);
        $ledger = round((float) WalletTransaction::where('user_id', $userId)->sum('amount'), 2);
        $after = round((float) $user->wallet_balance - $out, 2);
        $rows[] = [$user->username, number_format((float) $user->wallet_balance, 2), '-'.number_format($out, 2), number_format($after, 2)];

        if (abs($ledger - (float) $user->wallet_balance) >= 0.005) {
            $problems[] = "{$user->username}: wallet ({$user->wallet_balance}) does not match its transaction history ({$ledger}).";
        }
        if ($after < 0) {
            $problems[] = "{$user->username}: wallet would become {$after} (already withdrawn). Handle this one manually.";
        }
    }
    $this->table(['Wallet', 'Now $', 'To waiting $', 'After $'], $rows);

    if ($problems) {
        foreach ($problems as $p) {
            $this->error($p);
        }
        $this->warn('Nothing was changed.');

        return 1;
    }

    if (! $this->option('apply')) {
        $this->warn('Dry run: nothing was changed. Run again with --apply to move these to waiting.');

        return 0;
    }

    DB::transaction(function () use ($moves, $byUser) {
        foreach ($moves as $m) {
            $tx = $m['tx'];
            $held = HeldCommission::create([
                'user_id' => $tx->user_id,
                'from_user_id' => $tx->from_user_id,
                'level' => $tx->level,
                'percent' => $m['base'] > 0 ? round((float) $tx->amount / $m['base'] * 100, 2) : 0,
                'base' => $m['base'],
                'amount' => $tx->amount,
                'source_type' => $tx->source_type,
                'source_id' => $tx->source_id,
            ]);
            // Keep the original date: the commission has been "waiting" since it was earned.
            $held->forceFill(['created_at' => $tx->created_at])->save();
            $tx->delete();
        }

        foreach ($byUser->keys() as $userId) {
            $user = User::withTrashed()->whereKey($userId)->lockForUpdate()->first();
            $running = 0.0;
            foreach (WalletTransaction::where('user_id', $userId)->orderBy('id')->lockForUpdate()->get() as $t) {
                $running = round($running + (float) $t->amount, 2);
                if (abs($running - (float) $t->balance_after) >= 0.005) {
                    $t->forceFill(['balance_after' => $running])->save();
                }
            }
            $user->forceFill(['wallet_balance' => $running])->save();
        }
    });

    $this->info(count($moves).' commission(s) moved to waiting for '.$byUser->count().' user(s). They are paid automatically once those users have enough active directs.');

    return 0;
})->purpose('Apply the 7x7 rule to commission paid before it existed (dry run unless --apply)');

/*
| Apply the "commission pays the remaining fee" rule to commission earned before it existed.
|   php artisan app:apply-fee-share            (dry run)
|   php artisan app:apply-fee-share --apply    (does it)
| For every active member still below the full fee, each commission not yet shared gives
| Setting "commission_fee_share" % (50% by default) to their fee, oldest first, never more
| than they still owe and never more than is in their wallet. Running it twice does nothing.
*/
Artisan::command('app:apply-fee-share {--apply : Write the changes (default is a dry run)}', function () {
    $percent = Setting::money('commission_fee_share');
    if ($percent <= 0) {
        $this->info('commission_fee_share is 0% in Admin > Settings: nothing to do.');

        return 0;
    }

    $fullFee = Setting::money('full_fee');
    $plan = [];
    $members = User::role(User::ROLE_MEMBER)->where('status', 'active')->where('paid_amount', '<', $fullFee)->orderBy('id')->get();

    foreach ($members as $member) {
        $pending = (float) $member->payments()->where('status', 'pending')->sum('amount');
        $owed = round($fullFee - (float) $member->paid_amount - $pending, 2);
        $wallet = (float) $member->wallet_balance;

        $txs = $member->walletTransactions()->where('type', 'commission')->whereNull('fee_share_payment_id')->orderBy('id')->get();
        foreach ($txs as $tx) {
            $share = min(round((float) $tx->amount * $percent / 100, 2), $owed, $wallet);
            if ($share <= 0) {
                break;
            }
            $owed = round($owed - $share, 2);
            $wallet = round($wallet - $share, 2);
            $plan[] = ['member' => $member, 'tx' => $tx, 'share' => $share];
        }
    }

    if (! $plan) {
        $this->info('Nothing to change: no member below the full fee has unshared commission.');

        return 0;
    }

    $this->table(
        ['Member', 'Paid now $', 'Commission tx', 'Commission $', 'To fee $'],
        array_map(fn ($p) => [
            $p['member']->username, number_format((float) $p['member']->paid_amount, 2),
            '#'.$p['tx']->id.' L'.$p['tx']->level, number_format((float) $p['tx']->amount, 2), number_format($p['share'], 2),
        ], $plan),
    );
    $rows = collect($plan)->groupBy(fn ($p) => $p['member']->id)->map(function ($items) {
        $m = $items->first()['member'];
        $total = $items->sum('share');

        return [$m->username, number_format((float) $m->wallet_balance, 2), number_format((float) $m->wallet_balance - $total, 2),
            number_format((float) $m->paid_amount, 2), number_format((float) $m->paid_amount + $total, 2)];
    })->values()->all();
    $this->table(['Member', 'Wallet now $', 'Wallet after $', 'Paid now $', 'Paid after $'], $rows);

    if (! $this->option('apply')) {
        $this->warn('Dry run: nothing was changed. Run again with --apply to pay these amounts towards the fees.');

        return 0;
    }

    $commission = app(CommissionService::class);
    $applied = DB::transaction(function () use ($plan, $commission) {
        $total = 0.0;
        foreach ($plan as $p) {
            $total += $commission->applyFeeShare($p['member']->fresh(), $p['tx']->fresh());
        }

        return $total;
    });

    $this->info('$'.number_format($applied, 2).' of commission paid towards members\' remaining fees.');

    return 0;
})->purpose('Pay part of earlier commission towards the remaining fee of members below the full fee (dry run unless --apply)');
