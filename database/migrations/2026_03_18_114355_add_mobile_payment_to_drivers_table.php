<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('mobile_payment_number')->nullable()->after('qr_code_image');
            $table->string('mobile_payment_name')->nullable()->after('mobile_payment_number');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['mobile_payment_number', 'mobile_payment_name']);
        });
    }
};