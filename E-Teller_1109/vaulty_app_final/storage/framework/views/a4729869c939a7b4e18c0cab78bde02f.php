

<?php $__env->startSection('title', 'Akses Ditolak'); ?>
<?php $__env->startSection('heading', 'Akses ditolak'); ?>
<?php $__env->startSection('subheading', 'Peran akun Anda tidak memiliki izin untuk halaman ini.'); ?>
<?php $__env->startSection('visual-title', 'Halaman ini hanya untuk peran tertentu.'); ?>
<?php $__env->startSection('visual-text', 'Gunakan akun dengan hak akses yang sesuai, atau kembali ke dasbor peran Anda.'); ?>

<?php $__env->startSection('content'); ?>
    <div class="err-code">403</div>
    <p class="auth-desc">
        Halaman yang Anda tuju dibatasi untuk peran lain. Bila Anda merasa berhak mengaksesnya,
        mintalah admin menyesuaikan hak akses akun Anda.
    </p>

    <div class="err-actions">
        <a class="btn btn-primary btn-block" href="<?php echo e(url('/')); ?>">Kembali ke Aplikasi</a>
        <button class="btn btn-soft btn-block" type="button" onclick="history.back()">Halaman Sebelumnya</button>
    </div>

    <p class="err-note">Kode galat <code>403</code> &middot; Hubungi admin bila hak akses perlu diubah.</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/errors/403.blade.php ENDPATH**/ ?>