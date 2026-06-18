<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();
            $table->decimal('dropoff_latitude', 10, 7)->nullable();
            $table->decimal('dropoff_longitude', 10, 7)->nullable();
        });
    }

    public function down()
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn(['pickup_latitude', 'pickup_longitude', 'dropoff_latitude', 'dropoff_longitude']);
        });
    }
};