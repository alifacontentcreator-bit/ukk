<?php
/* Tujuan file: Seeder ini mengisi data awal atau data pengembangan untuk fitur database seeder. */


namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * Urutan: Admin -> Supervisor -> Teller & Nasabah contoh -> Nasabah portal contoh.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SupervisorSeeder::class,
            DummyUsersSeeder::class,
            CustomerPortalSeeder::class,
        ]);
    }
}
