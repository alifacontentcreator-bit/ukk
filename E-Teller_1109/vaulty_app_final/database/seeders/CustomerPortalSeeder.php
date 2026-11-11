<?php
/* Tujuan file: Seeder ini mengisi data awal atau data pengembangan untuk fitur customer portal seeder. */


namespace Database\Seeders;

use App\Models\User;
use App\Models\Account;
use App\Services\AccountNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerPortalSeeder extends Seeder
{
    public function run(): void
    {
        // Contoh nasabah kelas XII RPL 2
        $classAttrs = AccountNumberGenerator::resolveClassAttributes('XII RPL2');

        $user = User::firstOrCreate(
            ['username' => 'siswa01'],
            [
                'name' => 'Siswa Vaulty',
                'password' => Hash::make('siswa123'),
                'role' => 'customer',
                'nis' => '00002',
                'kelas' => $classAttrs['kelas'],
                'jurusan' => $classAttrs['jurusan'],
                'tingkat' => $classAttrs['tingkat'],
                'kelas_urut' => $classAttrs['kelas_urut'],
                'angkatan' => $classAttrs['angkatan'],
            ]
        );

        if (!$user->account) {
            $accountNumber = AccountNumberGenerator::generate(
                $classAttrs['jurusan'],
                $classAttrs['angkatan'],
                $classAttrs['kelas_urut']
            );

            Account::create([
                'user_id' => $user->id,
                'account_number' => $accountNumber,
                'pin' => Hash::make(Str::random(64)),
                'pin_is_default' => true,
                'balance' => 150000,
                'qr_token' => Str::random(48),
            ]);
        }
    }
}
