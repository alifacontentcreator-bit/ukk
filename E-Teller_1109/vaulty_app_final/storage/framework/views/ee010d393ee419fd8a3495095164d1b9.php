<?php
    $bellUser = auth()->user();

    // Notifikasi hanya relevan untuk Teller. Tabel diperiksa lebih dahulu
    // agar halaman tetap tampil walau migrasi belum dijalankan.
    $bellUnread = 0;

    if ($bellUser
        && $bellUser->role === 'teller'
        && \Illuminate\Support\Facades\Schema::hasTable('report_notifications')) {
        $bellUnread = \App\Models\ReportNotification::unreadCountFor((int) $bellUser->id);
    }
?>

<?php if($bellUser && $bellUser->role === 'teller'): ?>
    <a class="topbar-icon-btn"
       href="<?php echo e(route('teller.notifikasi')); ?>"
       title="Notifikasi laporan (<?php echo e($bellUnread); ?> belum dibaca)"
       aria-label="Notifikasi laporan, <?php echo e($bellUnread); ?> belum dibaca">
        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'bell']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bell']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
        <?php if($bellUnread > 0): ?>
            <span class="dot"><?php echo e($bellUnread > 9 ? '9+' : $bellUnread); ?></span>
        <?php endif; ?>
    </a>
<?php endif; ?>
<?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/components/report-notification-bell.blade.php ENDPATH**/ ?>