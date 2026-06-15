<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE INDEX idx_reservations_due_completion
            ON reservations ((CAST(date AS timestamp) + end_time))
            WHERE status = 'confirmed'
        ");

        DB::statement("
            CREATE INDEX idx_reservations_due_reminder
            ON reservations ((CAST(date AS timestamp) + start_time))
            WHERE status = 'confirmed' AND reminder_sent_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_reservations_due_completion');
        DB::statement('DROP INDEX IF EXISTS idx_reservations_due_reminder');
    }
};
