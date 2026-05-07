<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            // Store driver's GPS location
            $table->decimal('latitude', 10, 7)->nullable()->after('status');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->timestamp('location_updated_at')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'location_updated_at']);
        });
    }
};