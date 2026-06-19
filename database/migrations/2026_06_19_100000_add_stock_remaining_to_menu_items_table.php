<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('stock_remaining')->nullable()->after('daily_stock');
        });

        DB::table('menu_items')->update(['stock_remaining' => DB::raw('daily_stock')]);
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('stock_remaining');
        });
    }
};
