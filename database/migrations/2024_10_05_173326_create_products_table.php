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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('sub_title');
            $table->string('youtube_url');
            $table->text('hidden_information')->nullable();
            $table->boolean('is_approved')->default(0);
            $table->string('levels_id')->nullable();
            $table->enum('status', ['submitted', 'approved', 'denied'])->nullable();
            $table->enum('visibility', ['unpublished', 'private', 'public'])->nullable();
            $table->integer('reward_amount')->nullable();
            $table->integer('view_count')->default(0);
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
