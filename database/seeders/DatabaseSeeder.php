<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user if not exists
        if (!User::where('email', 'drukridetaxi@gmail.com')->exists()) {
            User::create([
                'name'      => 'Admin',
                'email'     => 'drukridetaxi@gmail.com',
                'password'  => Hash::make('admin1234'),
                'user_type' => 'admin',
            ]);
        }
    }
}