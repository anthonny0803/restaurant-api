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
        Schema::create('cancellation_policy_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('cancellation_deadline_hours');
            $table->unsignedTinyInteger('refund_percentage');
            $table->unsignedTinyInteger('admin_fee_percentage');
            $table->timestamp('policy_accepted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cancellation_policy_snapshots');
    }
};
