<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin Meddiscus',
            'username' => 'admin',
            'email' => 'admin@gmail.com',
            'university' => 'Universitas Meddiscus',
            'phone' => '08123456789',
            'role' => 'admin',
            'password' => Hash::make('kikipoiu'),
            'email_verified_at' => now(),
            'is_active' => true,
            'device_id' => null,
            'device_name' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
            'user_agent' => null,
        ]);

        User::create([
            'name' => 'User Meddiscus',
            'username' => 'user',
            'email' => 'user@gmail.com',
            'university' => 'Universitas Indonesia',
            'phone' => '08987654321',
            'role' => 'user',
            'password' => Hash::make('kikipoiu'),
            'email_verified_at' => now(),
            'is_active' => true,
            'device_id' => null,
            'device_name' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
            'user_agent' => null,
        ]);
    }
}