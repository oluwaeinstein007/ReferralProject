<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_user_id');
            $table->foreign('sender_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->enum('receiver_account_type', ['ref_balance', 'task_balance', 'ref_task'])->default('ref_balance');
            $table->enum('status', ['pending_payment', 'pending_approval', 'completed', 'failed', 'cancelled', 'initiated'])->default('initiated');
            $table->integer('otp');
            $table->decimal('amount', 10, 2);
            $table->string('transaction_id')->unique();
            $table->string('link')->unique();
            // Foreign key to the user who is receiving
            $table->unsignedBigInteger('receiver_user_id');
            $table->foreign('receiver_user_id')->references('id')->on('users')->onDelete('cascade');

            // Description field to hold any additional details about the transaction
            $table->string('description')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
