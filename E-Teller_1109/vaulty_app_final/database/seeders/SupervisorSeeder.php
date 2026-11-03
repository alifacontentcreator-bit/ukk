<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SupervisorSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'spv'],
            [
                'name' => 'Bapak Supervisor',
                'password' => Hash::make('rahasia123'),
                'role' => 'supervisor',
            ]
        );
    }
}
