<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Membuat akun Admin Bank
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Alifa Admin',
                'password' => Hash::make('rahasia123'),
                'role' => 'admin',
            ]
        );
    }
}
