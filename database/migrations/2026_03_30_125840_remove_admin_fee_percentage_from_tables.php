<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn('admin_fee_percentage');
        });

        Schema::table('cancellation_policy_snapshots', function (Blueprint $table) {
            $table->dropColumn('admin_fee_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('admin_fee_percentage')->default(10);
        });

        Schema::table('cancellation_policy_snapshots', function (Blueprint $table) {
            $table->unsignedTinyInteger('admin_fee_percentage')->default(10);
        });
    }
};
