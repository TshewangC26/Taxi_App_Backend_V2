<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove old unique constraint on email alone
            $table->dropUnique(['email']);

            // Add new unique constraint on email + user_type combo
            $table->unique(['email', 'user_type']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email', 'user_type']);
            $table->unique('email');
        });
    }
};