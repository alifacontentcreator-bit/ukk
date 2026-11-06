<?php

/*
|--------------------------------------------------------------------------
| Bootstrap PHPUnit
|--------------------------------------------------------------------------
|
| Pada sebagian mesin (mis. Windows + Laragon) variabel seperti APP_ENV
| dan DB_CONNECTION sudah ter-set di lingkungan sistem. Nilai tersebut
| dibaca lebih dulu oleh Illuminate\Support\Env melalui $_SERVER, sehingga
| <env> di phpunit.xml kalah dan test ikut berjalan di basis data
| pengembangan (MySQL) — test dengan RefreshDatabase akan menghapus data.
|
| Karena itu nilai di bawah dipaksa di $_SERVER (prioritas tertinggi pada
| Env Laravel) supaya test selalu memakai SQLite in-memory.
|
*/

$forced = [
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
];

foreach ($forced as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__ . '/../vendor/autoload.php';
