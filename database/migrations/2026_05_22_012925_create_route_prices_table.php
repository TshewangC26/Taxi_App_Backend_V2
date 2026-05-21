<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_type_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['route_id', 'vehicle_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_prices');
    }
};