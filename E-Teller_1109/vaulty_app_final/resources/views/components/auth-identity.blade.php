{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman auth identity, termasuk data dan aksi yang disediakan aplikasi. --}}
{{-- Papan merek halaman masuk: logo sekolah + nama lembaga + subjudul sistem.
     Dipakai dua kali di layouts/auth: di pojok kiri atas foto (layar lebar) dan di
     atas formulir (layar sempit, saat panel foto disembunyikan). --}}
<div class="auth-identity">
    <span class="auth-identity-mark">
        <img src="{{ asset('images/logo-smkn1-bantul.png') }}" alt="Logo SMK Negeri 1 Bantul">
    </span>
    <span class="auth-identity-text">
        <strong class="auth-identity-name">{{ config('app.name') }}</strong>
        <span class="auth-identity-sub">Sistem Tabungan Siswa</span>
    </span>
</div>
