<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('reservation_items', function (Blueprint $table) {
            $table->index('menu_item_id');
        });

        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('reservation_items', function (Blueprint $table) {
            $table->dropIndex(['menu_item_id']);
        });

        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->dropIndex(['role_id']);
        });
    }
};
