<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Account;
use App\Services\AccountNumberGenerator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DummyUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Membuat akun Teller
        User::firstOrCreate(
            ['username' => 'teller'],
            [
                'name' => 'Budi Teller',
                'password' => Hash::make('rahasia123'),
                'role' => 'teller',
            ]
        );

        // Membuat akun Nasabah (Customer) contoh: kelas X RPL 1
        $classAttrs = AccountNumberGenerator::resolveClassAttributes('X RPL1');

        $nasabah = User::firstOrCreate(
            ['username' => 'nasabah'],
            [
                'name' => 'Siti Nasabah',
                'password' => Hash::make('rahasia123'),
                'role' => 'customer',
                'nis' => '00001',
                'kelas' => $classAttrs['kelas'],
                'jurusan' => $classAttrs['jurusan'],
                'tingkat' => $classAttrs['tingkat'],
                'kelas_urut' => $classAttrs['kelas_urut'],
                'angkatan' => $classAttrs['angkatan'],
            ]
        );

        if (!$nasabah->account) {
            $accountNumber = AccountNumberGenerator::generate(
                $classAttrs['jurusan'],
                $classAttrs['angkatan'],
                $classAttrs['kelas_urut']
            );

            Account::create([
                'user_id' => $nasabah->id,
                'account_number' => $accountNumber,
                'pin' => Hash::make(Str::random(64)),
                'pin_is_default' => true,
                'balance' => 150000,
                'qr_token' => Str::random(48),
            ]);
        }
    }
}
