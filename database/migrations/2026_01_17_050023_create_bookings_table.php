<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('passenger_id')->constrained('users');
                $table->foreignId('driver_id')->nullable()->constrained('users');
                $table->string('pickup_location');
                $table->string('dropoff_location');
                $table->enum('vehicle_type', ['4-seater', '7-seater', '8-seater']);
                $table->decimal('estimated_price', 8, 2);
                $table->decimal('final_price', 8, 2)->nullable();
                $table->enum('status', ['pending', 'accepted', 'started', 'completed', 'cancelled'])->default('pending');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};