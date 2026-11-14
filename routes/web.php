<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Member;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/payout', [ProfileController::class, 'updatePayout'])->name('profile.payout');
    Route::post('/profile/avatar', [AvatarController::class, 'update'])->name('profile.avatar');
    Route::delete('/profile/avatar', [AvatarController::class, 'destroy'])->name('profile.avatar.destroy');
    Route::get('/avatars/{user}', [AvatarController::class, 'show'])->name('avatars.show');

    // Payment proof files (owner or payment staff; checked in the controller).
    Route::get('/payments/{payment}/proof', [Member\PaymentController::class, 'proof'])->name('payments.proof');
    // Admin's transfer receipt for a withdrawal (requesting member or withdrawal staff; checked in the controller).
    Route::get('/withdrawals/{withdrawal}/receipt', [Admin\WithdrawalController::class, 'receipt'])->name('withdrawals.receipt');

    /* ---------------- Referral network & earnings (every role) ---------------- */
    // Every user (member, teacher, staff, super admin) has a referral link, their own team
    // tree, earns commission into a wallet and can withdraw it.
    Route::get('/team', [Member\TeamController::class, 'index'])->name('team.index');
    Route::get('/team/tree/{root?}', [Member\TeamController::class, 'tree'])->name('team.tree');
    Route::get('/team/register', [Member\TeamController::class, 'create'])->name('team.create');
    Route::post('/team/register', [Member\TeamController::class, 'store'])->name('team.store');

    Route::get('/wallet', [Member\WalletController::class, 'index'])->name('wallet.index');
    Route::get('/withdrawals', [Member\WalletController::class, 'withdrawals'])->name('withdrawals.index');
    Route::post('/withdrawals', [Member\WalletController::class, 'requestWithdrawal'])->name('withdrawals.store');

    /* ---------------- Member area (fee payments & classes) ---------------- */
    Route::middleware('role:'.User::ROLE_MEMBER)->group(function () {
        Route::get('/payments', [Member\PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments', [Member\PaymentController::class, 'store'])->name('payments.store');

        Route::get('/classes', [Member\ClassController::class, 'index'])->name('classes.index');
    });

    /* ---------------- Teacher area ---------------- */
    Route::middleware('role:'.User::ROLE_TEACHER)->prefix('teacher')->name('teacher.')->group(function () {
        Route::get('/batches/{batch}', [Teacher\BatchController::class, 'show'])->name('batches.show');
    });

    /* ---------------- Admin / staff area ---------------- */
    Route::middleware('role:'.User::ROLE_SUPER_ADMIN.'|'.User::ROLE_STAFF)->prefix('admin')->name('admin.')->group(function () {
        // One "Add user" screen for every role; which roles are allowed is checked in the controller.
        Route::get('/users/create', [Admin\UserController::class, 'create'])->name('users.create');
        Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');

        Route::controller(Admin\MemberController::class)->group(function () {
            Route::get('/members', 'index')->middleware('can:members.view')->name('members.index');
            Route::get('/members/{member}', 'show')->middleware('can:members.view')->name('members.show');
            Route::get('/members/{member}/edit', 'edit')->middleware('can:members.edit')->name('members.edit');
            Route::put('/members/{member}', 'update')->middleware('can:members.edit')->name('members.update');
            Route::patch('/members/{member}/status', 'updateStatus')->middleware('can:members.status')->name('members.status');
            Route::post('/members/{member}/payments', 'recordPayment')->middleware('can:payments.review')->name('members.payments.store');
            Route::delete('/members/{member}', 'destroy')->middleware('can:members.delete')->name('members.destroy');
            Route::get('/genealogy/{root?}', 'tree')->middleware('can:members.view')->name('genealogy');
        });

        Route::middleware('can:payments.view')->get('/payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
        Route::middleware('can:payments.review')->group(function () {
            Route::post('/payments/{payment}/approve', [Admin\PaymentController::class, 'approve'])->name('payments.approve');
            Route::post('/payments/{payment}/reject', [Admin\PaymentController::class, 'reject'])->name('payments.reject');
        });

        Route::middleware('can:withdrawals.view')->group(function () {
            Route::get('/withdrawals', [Admin\WithdrawalController::class, 'index'])->name('withdrawals.index');
            Route::get('/withdrawals/{withdrawal}', [Admin\WithdrawalController::class, 'show'])->name('withdrawals.show');
        });
        Route::middleware('can:withdrawals.review')->group(function () {
            Route::post('/withdrawals/{withdrawal}/approve', [Admin\WithdrawalController::class, 'approve'])->name('withdrawals.approve');
            Route::post('/withdrawals/{withdrawal}/reject', [Admin\WithdrawalController::class, 'reject'])->name('withdrawals.reject');
        });

        Route::middleware('can:reports.view')->group(function () {
            Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/export', [Admin\ReportController::class, 'export'])->name('reports.export');
        });

        Route::middleware('can:batches.manage')->group(function () {
            Route::resource('batches', Admin\BatchController::class);
            Route::post('/batches/{batch}/members', [Admin\BatchController::class, 'assign'])->name('batches.assign');
            Route::delete('/batches/{batch}/members/{member}', [Admin\BatchController::class, 'unassign'])->name('batches.unassign');
        });

        foreach (['teachers' => [User::ROLE_TEACHER, 'teachers.manage'], 'staff' => [User::ROLE_STAFF, 'staff.manage']] as $path => [$role, $ability]) {
            Route::middleware("can:{$ability}")->controller(Admin\TeamUserController::class)->group(function () use ($path, $role) {
                Route::get("/{$path}", 'index')->name("{$path}.index")->defaults('role', $role);
                Route::get("/{$path}/{user}/edit", 'edit')->name("{$path}.edit")->defaults('role', $role);
                Route::put("/{$path}/{user}", 'update')->name("{$path}.update")->defaults('role', $role);
                Route::delete("/{$path}/{user}", 'destroy')->name("{$path}.destroy")->defaults('role', $role);
            });
        }

        Route::middleware('can:settings.manage')->group(function () {
            Route::get('/settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        });
    });
});

require __DIR__.'/auth.php';
