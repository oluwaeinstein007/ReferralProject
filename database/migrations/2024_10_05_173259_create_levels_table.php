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
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            // Name of the commission
            $table->string('name');

            // Amount of the commission
            $table->integer('amount')->default(0);

            // Percentages for referrers and admin
            $table->integer('referrer_1_percentage');
            $table->integer('referrer_2_percentage');
            $table->integer('admin_percentage');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};
