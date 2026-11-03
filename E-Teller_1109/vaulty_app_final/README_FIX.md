# Vaulty App - RBAC Fix

Perbaikan utama:
- Dashboard dan layout web dipisahkan untuk Administrator, Supervisor, dan Piket Teller.
- Menu sidebar tidak lagi mencampur hak role lain.
- Route dipisahkan menjadi /admin, /supervisor, dan /teller.
- Teller membuat transaksi berstatus pending; saldo baru berubah setelah approval Supervisor.
- Status transaksi diseragamkan menjadi pending/approved/rejected/corrected.
- Model Account diperbaiki agar memakai primary key id dan relasi user.
- View yang sebelumnya hilang ditambahkan agar menu tidak error saat dibuka.
- Desain ketiga role menggunakan CSS Vaulty yang sama.

Catatan: file .env sengaja tidak disertakan. Gunakan .env milik project Anda dan pastikan memakai MySQL database `vaulty`.

Perintah setelah menyalin project:
1. composer install
2. php artisan optimize:clear
3. php artisan migrate
4. php artisan serve

Akun testing yang sudah dibuat pada database sebelumnya:
- admin / admin123
- supervisor / supervisor123
- teller / teller123
