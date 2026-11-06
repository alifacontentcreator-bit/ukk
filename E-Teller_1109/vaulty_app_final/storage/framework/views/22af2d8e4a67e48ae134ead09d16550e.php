

<?php $__env->startSection('title', 'Halaman Tidak Ditemukan'); ?>
<?php $__env->startSection('heading', 'Halaman tidak ditemukan'); ?>
<?php $__env->startSection('subheading', 'Alamat yang Anda buka tidak tersedia atau sudah dipindahkan.'); ?>
<?php $__env->startSection('visual-title', 'Halaman yang Anda tuju tidak tersedia.'); ?>
<?php $__env->startSection('visual-text', 'Periksa kembali tautan atau alamat yang Anda masukkan, lalu ulangi dari menu aplikasi.'); ?>

<?php $__env->startSection('content'); ?>
    <div class="err-code">404</div>
    <p class="auth-desc">
        Tautan mungkin salah ketik, sudah kedaluwarsa, atau datanya telah dihapus.
        Seluruh catatan tabungan tetap aman dan tidak terpengaruh.
    </p>

    <div class="err-actions">
        <a class="btn btn-primary btn-block" href="<?php echo e(url('/')); ?>">Kembali ke Aplikasi</a>
        <button class="btn btn-soft btn-block" type="button" onclick="history.back()">Halaman Sebelumnya</button>
    </div>

    <p class="err-note">Kode galat <code>404</code> &middot; Bila masalah berlanjut, hubungi petugas teller.</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/errors/404.blade.php ENDPATH**/ ?>