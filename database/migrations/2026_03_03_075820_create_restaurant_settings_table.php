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
        Schema::create('restaurant_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('deposit_per_person', 8, 2)->default(5.00);
            $table->unsignedSmallInteger('cancellation_deadline_hours')->default(24);
            $table->unsignedTinyInteger('refund_percentage')->default(50);
            $table->unsignedTinyInteger('admin_fee_percentage')->default(10);
            $table->unsignedSmallInteger('default_reservation_duration_minutes')->default(120);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_settings');
    }
};
