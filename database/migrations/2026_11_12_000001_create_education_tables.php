<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Joining fee / top-up payments. Proof uploads wait for review; wallet payments are approved instantly.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete(); // who funded it (self, sponsor, admin)
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['proof', 'wallet', 'admin']);
            $table->string('proof_path')->nullable();
            $table->string('reference', 100)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // Every wallet movement. amount is signed (+ credit, - debit).
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['commission', 'withdrawal', 'withdrawal_refund', 'payment', 'adjustment']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('level')->nullable();
            $table->nullableMorphs('source');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 40);
            $table->text('account_details');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->string('days', 100)->nullable();     // e.g. "Mon, Wed, Fri"
            $table->time('class_time')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('meeting_link')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['upcoming', 'running', 'completed'])->default('upcoming');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('batch_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['batch_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_user');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('settings');
    }
};
