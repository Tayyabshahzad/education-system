<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Members below the full fee get part of every commission (Admin > Settings,
     * "commission_fee_share", 50% by default) paid towards their remaining fee.
     * The commission row remembers the fee payment it funded so it is never applied twice.
     */
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('fee_share_payment_id')->nullable()->after('source_id')->constrained('payments')->nullOnDelete();
        });

        Setting::insertMissingDefaults(); // stores commission_fee_share = 50
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fee_share_payment_id');
        });
    }
};
