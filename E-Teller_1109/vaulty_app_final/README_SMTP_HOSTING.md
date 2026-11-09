# Email & SMTP pada Hosting

Panduan singkat agar web Laravel yang sudah di-hosting
(`https://vaultybankmini.web.id`) benar-benar **mengirim kode aktivasi ke email
akun nasabah** yang tersimpan pada menu Admin > Manajemen Pengguna.

> **Penting:** selama file di bawah belum di-upload ke hosting, hosting tetap
> menjawab `503 ACTIVATION_EMAIL_UNAVAILABLE` karena masih memakai kode lama.
> Paket siap-upload tersedia pada `upload-perbaikan-hosting.zip` (isi: folder
> `app/`, `config/mail.php`, `resources/views/admin/users/index.blade.php`,
> `README_SMTP_HOSTING.md`, `CONTOH_ENV_HOSTING.txt`).
> Ekstrak ZIP itu ke folder Laravel di hosting (sejajar `artisan`), lalu
> jalankan `php artisan config:clear` **dan** `php artisan view:clear`.

## Gejala yang ditangani

Aplikasi nasabah menampilkan salah satu pesan berikut saat menekan
**Kirim kode ke email terdaftar**:

| Respons server | Arti |
| --- | --- |
| `503` + `ACTIVATION_EMAIL_UNAVAILABLE` | Endpoint hidup dan NIS ditemukan, tetapi server **gagal mengirim email** (SMTP belum diisi / kredensial salah / port diblokir). Ini bukan masalah koneksi aplikasi. |
| `404` + `USER_NOT_FOUND` | NIS belum terdaftar pada Manajemen Pengguna. Tambahkan nasabah beserta emailnya. |
| `202` + `ACTIVATION_CODE_SENT` | Kode terkirim. Email tujuan ditampilkan tersamar (`ir***67@gmail.com`). |
| `429` | Batas 15 detik antar permintaan kode. |

Sejak perbaikan terakhir, urutan percobaan pengiriman email adalah:

1. SMTP dari `.env` hosting (`MAIL_MAILER=smtp`).
2. SMTP dari menu **Admin > Konfigurasi Sistem** (kolom `otp_smtp_*`).
3. Fungsi `mail()` milik hosting sebagai cadangan terakhir.

Setiap kegagalan dicatat pada `storage/logs/laravel.log` **beserta pesan
aslinya**, misalnya `Connection could not be established with host
smtp.gmail.com` atau `535-5.7.8 Username and Password not accepted`.

## 1. Isi `.env` di hosting

Buka **File Manager hosting > `.env`** lalu sesuaikan baris berikut (nilai
contoh memakai Gmail yang sandi aplikasinya sudah terbukti valid):

```env
APP_URL=https://vaultybankmini.web.id

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=akunukk93@gmail.com
MAIL_PASSWORD=sandi-aplikasi-16-karakter
MAIL_FROM_ADDRESS=akunukk93@gmail.com
MAIL_FROM_NAME="LKM Mitra Siswa Abadi"
```

Catatan penting:

- `MAIL_SCHEME` **harus `null` atau dikosongkan**. Jangan diisi `starttls`
  atau `ssl`: aplikasi sekarang menentukan enkripsi dari port (465 = SMTPS,
  587/25 = STARTTLS). Nilai `starttls` pada `MAIL_SCHEME` ditolak Symfony dan
  membuat email gagal terkirim.
- `MAIL_PASSWORD` Gmail **bukan** sandi login, melainkan *App Password* 16
  karakter (Google Account > Keamanan > Sandi aplikasi). Sandi biasa selalu
  ditolak dengan `535-5.7.8 Username and Password not accepted`.
- Bila hosting memblokir port 465, ganti `MAIL_PORT=587`.
- Bila SMTP tetap tidak bisa dipakai, isi alamat pengirim cadangan pada domain
  sendiri agar email lewat `mail()` tidak masuk spam:

  ```env
  MAIL_FALLBACK_FROM_ADDRESS=no-reply@vaultybankmini.web.id
  ```

Setelah mengubah `.env`, jalankan dari Terminal hosting (atau Cron sekali):

```bash
php artisan config:clear
```

Kalau hosting memakai config cache (`php artisan config:cache`), wajib
`config:clear` / `config:cache` ulang setiap kali `.env` berubah.

## 2. Uji dari terminal hosting

```bash
php artisan mail:test alamat-anda@gmail.com
```

Perintah ini memakai kode pengiriman yang sama dengan kode aktivasi. Keluaran
`Email uji berhasil dikirim` berarti pengiriman dari hosting sudah benar. Bila
gagal, alasan teknisnya langsung ditampilkan (port diblokir, sandi salah, dsb.)
dan lengkapnya ada di `storage/logs/laravel.log`.

## 3. Uji dari web Admin

1. Login sebagai **Administrator**.
2. Buka **Konfigurasi** > bagian **Pengiriman sandi reset (SMTP)**.
3. Pilih **Aktif**, isi host, port, enkripsi, nama pengguna, sandi aplikasi, dan
   alamat email pengirim (sama seperti `.env` di atas).
4. Isi **Kirim email uji ke** dengan alamat yang bisa Anda periksa, lalu tekan
   **Kirim Email Uji**.

Bila muncul pesan gagal, penyebabnya kini ikut ditampilkan (bukan hanya
"periksa konfigurasi"), misalnya sandi aplikasi salah atau port terblokir.

## 4. Uji dari aplikasi nasabah

1. Pastikan email nasabah sudah diisi pada **Admin > Manajemen Pengguna**
   (menu yang dipakai untuk menambah/mengubah nasabah). Alamat inilah yang
   menerima kode — bukan email yang diketik di aplikasi.
2. Buka aplikasi, masuk ke **Aktivasi Akun**, isi NIS, tekan
   **Kirim kode ke email terdaftar**.
3. Respons `202` menampilkan `Kode aktivasi sudah dikirim ke ir***67@gmail.com`.
   Cek kotak masuk **dan folder Spam/Junk**, lalu masukkan 8 karakter kode
   beserta password baru pada form aktivasi.

## 5. Bila masih gagal

1. Buka `storage/logs/laravel.log` (bisa diunduh lewat File Manager) dan cari
   baris `Pengiriman email lewat ... gagal.` — di situ tertulis penyebabnya.
2. `Could not resolve host` / `php_network_getaddresses`: hosting tidak punya
   akses DNS keluar — hubungi penyedia hosting.
3. `Connection timed out` pada 465 **dan** 587: hosting memblokir SMTP keluar —
   minta penyedia hosting membuka port tersebut, atau andalkan
   `MAIL_FALLBACK_FROM_ADDRESS` (jalur `mail()`).
4. `535 Username and Password not accepted`: buat ulang *App Password* Gmail,
   lalu simpan ulang pada `.env` **dan** Konfigurasi Sistem.
5. `UnsupportedSchemeException` / skema tidak didukung: kosongkan
   `MAIL_SCHEME` (lihat bagian 1).
6. Pastikan tabel `customer_activation_tokens` sudah ada:
   `php artisan migrate --force`.
7. Bila `APP_DEBUG=true` pada hosting, respons `503` juga memuat field
   `detail` berisi alasan teknis sehingga bisa diperiksa langsung dari browser:

   ```bash
   curl -i -X POST https://vaultybankmini.web.id/api/v1/auth/request-activation-code \
        -H "Accept: application/json" -H "Content-Type: application/json" \
        -d "{\"username\":\"17758\"}"
   ```

## Berkas yang berubah

- `app/Services/ConfiguredOtpMailer.php` — percobaan berurutan `.env` →
  Konfigurasi Sistem → `mail()` hosting, penentuan skema TLS dari port, serta
  pencatatan alasan kegagalan.
- `app/Http/Controllers/Api/V1/AuthController.php` — log memuat pesan asli dan
  respons `503` menyertakan `detail` saat `APP_DEBUG=true`.
- `app/Http/Controllers/SystemSettingsController.php` — pesan gagal email uji
  menampilkan penyebabnya.
- `app/Console/Commands/TestOtpMail.php` — perintah `php artisan mail:test`.
## Perbaikan lain yang ikut di paket upload

- `resources/views/admin/users/index.blade.php` — tombol **hapus** kini tampil
  di **semua baris** halaman Manajemen Pengguna (sesuai ketentuan kolom Aksi =
  ubah, blokir, hapus). Sebelumnya tombol hapus disembunyikan untuk akun
  nasabah, sehingga baris nasabah hanya punya 2 tombol.
- `app/Http/Controllers/UserController.php` — penghapusan berlaku untuk semua
  role. Akun nasabah tanpa riwayat transaksi dihapus permanen beserta rekening,
  notifikasi, dan QR-nya; akun yang **sudah** punya riwayat transaksi/laporan
  ditolak dengan pesan yang jelas agar jejak audit tetap utuh (gunakan tombol
  nonaktifkan/blokir). Token login aplikasi mobile ikut dicabut saat akun
  dihapus.
- Setelah upload view, jalankan `php artisan view:clear` supaya Blade lama tidak
  dipakai dari cache.

