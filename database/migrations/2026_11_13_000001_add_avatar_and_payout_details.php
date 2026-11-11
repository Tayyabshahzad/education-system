<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CNIC + unique mobile number, profile picture, and the member's saved payout details.
        // CNIC and phone are stored normalised (see App\Support\Identity) so uniqueness is reliable.
        // Lengths leave room for the "#deleted-{id}" suffix added when an account is deleted.
        Schema::table('users', function (Blueprint $table) {
            $table->string('cnic', 40)->nullable()->unique()->after('phone');
            $table->string('phone', 50)->nullable()->change();
            $table->unique('phone');
            $table->string('avatar_path')->nullable()->after('country');
            $table->string('payout_method', 40)->nullable()->after('avatar_path');
            $table->string('bank_name', 100)->nullable()->after('payout_method');
            $table->string('account_title', 100)->nullable()->after('bank_name');
            $table->string('account_number', 100)->nullable()->after('account_title');
        });

        // Each request keeps its own copy of the payout details used at that moment.
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('bank_name', 100)->nullable()->after('method');
            $table->string('account_title', 100)->nullable()->after('bank_name');
            $table->string('account_number', 100)->nullable()->after('account_title');
            $table->text('account_details')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_title', 'account_number']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropUnique(['cnic']);
            $table->dropColumn(['cnic', 'avatar_path', 'payout_method', 'bank_name', 'account_title', 'account_number']);
        });
    }
};
