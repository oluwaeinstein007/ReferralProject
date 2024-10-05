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
            // Title and sub-title of the video
            $table->string('title');
            $table->string('sub_title');

            // YouTube URL for the video
            $table->string('youtube_url');

            // Hidden information for the video
            $table->text('hidden_information')->nullable();

            // Foreign key to levels (if needed)
            $table->string('levels_id')->nullable();

            // Enum for status and visibility
            $table->enum('status', ['submitted', 'approved', 'denied'])->nullable();
            $table->enum('visibility', ['unpublished', 'private', 'public'])->nullable();

            // Reward amount and view count
            $table->integer('reward_amount')->nullable();
            $table->integer('view_count')->default(0);

            // Foreign key to the user who uploaded the video
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
