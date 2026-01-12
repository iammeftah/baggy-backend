<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default admin user
        User::create([
            'first_name' => 'Ahmed Reda',
            'last_name' => 'Meftah',
            'email' => 'meftahahmedreda02@gmail.com',
            'phone' => '+212707641333',
            'password' => Hash::make('$Admin1234'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }
}
