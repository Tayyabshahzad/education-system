<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commission an upline could not receive yet because of the 7x7 rule (not enough
     * full-fee directs for that level, or that level's slots were full). It is paid into the
     * wallet automatically as soon as the upline has enough directs (oldest first).
     */
    public function up(): void
    {
        Schema::create('held_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();                  // upline who will receive it
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete(); // member who paid
            $table->unsignedTinyInteger('level');
            $table->decimal('percent', 5, 2);
            $table->decimal('base', 12, 2);   // what the member had paid
            $table->decimal('amount', 12, 2); // fixed when held: base × percent
            $table->nullableMorphs('source');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'released_at', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_commissions');
    }
};
