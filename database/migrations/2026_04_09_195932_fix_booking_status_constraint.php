<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old constraint
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_status_check');

        // Add new constraint with all status values including in_progress
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('pending', 'accepted', 'in_progress', 'completed', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_status_check');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('pending', 'accepted', 'started', 'completed', 'cancelled'))");
    }
};