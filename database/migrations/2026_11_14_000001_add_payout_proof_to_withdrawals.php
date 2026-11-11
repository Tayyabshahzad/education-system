<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Admin's bank receipt / screenshot of the transfer made to the member. */
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('payout_proof_path')->nullable()->after('note');
            $table->string('payout_reference', 100)->nullable()->after('payout_proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['payout_proof_path', 'payout_reference']);
        });
    }
};
