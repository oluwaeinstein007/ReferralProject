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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->nullable();

            $table->string('username')->nullable()->unique();
            $table->string('phone_number')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('gender')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->string('country')->nullable();

            $table->float('ref_balance', 10, 2)->default(0.00);
            $table->float('task_balance', 10, 2)->default(0.00);
            $table->float('ref_sort', 10, 2)->nullable();

            $table->string('frozen_page_url')->nullable();

            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number', 11)->nullable();
            $table->integer('bank_country_id')->nullable();


            $table->integer('country_id')->nullable();
            $table->integer('state_id')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();

            $table->string('status')->default('active');
            $table->tinyInteger('user_role_id')->default(3);
            $table->integer('level_id')->nullable();

            $table->integer('auth_otp')->nullable();
            $table->timestamp('auth_otp_expires_at')->nullable();
            $table->integer('payment_otp')->nullable();
            $table->timestamp('payment_otp_expires_at')->nullable();

            $table->string('referral_code')->nullable();
            $table->string('referred_by_user_id_1')->nullable();
            $table->string('referred_by_user_id_2')->nullable();
            $table->boolean('referral_code_used')->default(0);

            $table->string('social_type')->nullable();
            $table->boolean('is_social')->default(0);
            $table->boolean('is_suspended')->default(0);
            $table->string('suspension_reason')->nullable();
            $table->timestamp('suspension_date')->nullable();
            $table->tinyInteger('suspension_duration')->nullable();

            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            // $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Schema::create('personal_access_tokens', function (Blueprint $table) {
        //     $table->id();
        //     $table->morphs('tokenable');
        //     $table->string('name');
        //     $table->string('token', 64)->unique();
        //     $table->text('abilities')->nullable();
        //     $table->timestamp('last_used_at')->nullable();
        //     $table->timestamp('expires_at')->nullable();
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('personal_access_tokens');
    }
};
