

<?php $__env->startSection('title', 'Dasbor Supervisor'); ?>
<?php $__env->startSection('page-title', 'Ringkasan Operasional'); ?>
<?php $__env->startSection('page-subtitle', 'Periksa laporan akhir teller dan pantau penutupan kas harian.'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $berhasilHariIni = \App\Models\Transaction::whereDate('created_at', today())->whereIn('status', ['approved', 'success']);
    $trxHariIni = (clone $berhasilHariIni)->count();
    $setoranHariIni = (clone $berhasilHariIni)->where('type', 'deposit')->sum('amount');
    $penarikanHariIni = (clone $berhasilHariIni)->where('type', 'withdrawal')->sum('amount');

    $laporanMasuk = \App\Models\DailyReport::where('status', 'submitted')->count();
    $koreksiPending = \App\Models\TransactionCorrection::where('status', 'pending')->count();
    $siapDipindah = \App\Models\DailyReport::where('status', 'approved')->where('transfer_status', 'pending')->count();
    $transaksiLama = \App\Models\Transaction::where('status', 'pending')->count();

    // Permintaan tambah modal dari Teller yang belum diproses.
    $permintaanModal = \App\Models\CashRequest::pendingCount();

    $laporanHariIni = \App\Models\DailyReport::with('teller')->whereDate('report_date', today())->get()->map(function ($r) {
        $r->selisih_hitung = $r->selisihKas();
        return $r;
    });
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
?>

<?php if($transaksiLama > 0): ?>
    <div class="alert alert-info">
        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'info']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'info']); ?>
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
        <span>Ada <?php echo e($transaksiLama); ?> transaksi dari alur lama yang masih berstatus menunggu. <a href="<?php echo e(route('supervisor.validasi')); ?>" style="font-weight:700;text-decoration:underline">Selesaikan di sini</a>.</span>
    </div>
<?php endif; ?>

<?php if($permintaanModal > 0): ?>
    <div class="alert alert-warn">
        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'clock']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'clock']); ?>
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
        <span>
            Ada <?php echo e($permintaanModal); ?> permintaan tambah modal dari Teller karena kas laci menipis.
            Tentukan nominalnya di
            <a href="<?php echo e(route('supervisor.rekonsiliasi')); ?>" style="font-weight:700;text-decoration:underline">Rekonsiliasi Kas</a>.
        </span>
    </div>
<?php endif; ?>

<div class="grid-3">
    <a href="<?php echo e(route('supervisor.laporan')); ?>" class="card stat">
        <div class="stat-top"><span class="stat-label">Laporan akhir menunggu</span><span class="stat-icon"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'file']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'file']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span></div>
        <div><div class="stat-value"><?php echo e($laporanMasuk); ?></div><div class="stat-note"><?php echo e($siapDipindah); ?> kas siap ke brankas</div></div>
    </a>
    <a href="<?php echo e(route('supervisor.koreksi')); ?>" class="card stat">
        <div class="stat-top"><span class="stat-label">Koreksi menunggu</span><span class="stat-icon"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'flag']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'flag']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span></div>
        <div><div class="stat-value"><?php echo e($koreksiPending); ?></div><div class="stat-note">Pengajuan dari teller</div></div>
    </a>
    <a href="<?php echo e(route('supervisor.jurnal')); ?>" class="card stat">
        <div class="stat-top"><span class="stat-label">Transaksi hari ini</span><span class="stat-icon"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'swap']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'swap']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span></div>
        <div><div class="stat-value"><?php echo e($trxHariIni); ?></div><div class="stat-note"><?php echo e($rp($setoranHariIni)); ?> masuk &middot; <?php echo e($rp($penarikanHariIni)); ?> keluar</div></div>
    </a>
</div>

<div class="section split">
    <section class="card">
        <div class="section-head">
            <div><h2 class="section-title">Laporan Hari Ini</h2><p class="section-sub">Status penutupan kas tiap teller.</p></div>
            <a href="<?php echo e(route('supervisor.rekonsiliasi')); ?>" class="more">Rekonsiliasi kas</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Teller</th><th>Laporan</th><th>Selisih</th><th>Kas</th></tr></thead>
                <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $laporanHariIni; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td class="name"><?php echo e($r->teller->name ?? '-'); ?></td>
                        <td><span class="badge <?php echo e($r->status === 'approved' ? 'badge-ok' : ($r->status === 'submitted' ? 'badge-warn' : 'badge-soft')); ?>"><?php echo e(['approved' => 'Disetujui', 'submitted' => 'Terkirim', 'draft' => 'Draf', 'rejected' => 'Ditolak'][$r->status] ?? ucfirst($r->status)); ?></span></td>
                        <td><?php if(abs($r->selisih_hitung) < 0.01): ?><span class="badge badge-ok">Klop</span><?php else: ?><span class="badge badge-danger"><?php echo e($rp(abs($r->selisih_hitung))); ?></span><?php endif; ?></td>
                        <td><span class="badge <?php echo e($r->transfer_status === 'approved' ? 'badge-ok' : 'badge-warn'); ?>"><?php echo e($r->transfer_status === 'approved' ? 'Di brankas' : 'Di laci'); ?></span></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="4" class="empty-cell">Belum ada laporan hari ini.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Perlu Tindakan</h2><p class="section-sub">Antrean yang menunggu keputusan Anda.</p></div></div>
        <div class="list">
            <?php $__currentLoopData = [
                ['Laporan akhir teller', $laporanMasuk, 'supervisor.laporan'],
                ['Kas siap dipindahkan', $siapDipindah, 'supervisor.laporan'],
                ['Pengajuan koreksi', $koreksiPending, 'supervisor.koreksi'],
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $count, $route]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e(route($route)); ?>" class="list-row">
                    <span class="name"><?php echo e($label); ?></span>
                    <span class="badge <?php echo e($count > 0 ? 'badge-warn' : 'badge-ok'); ?>"><?php echo e($count > 0 ? $count . ' menunggu' : 'Selesai'); ?></span>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </section>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.supervisor', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/supervisor/dashboard.blade.php ENDPATH**/ ?>