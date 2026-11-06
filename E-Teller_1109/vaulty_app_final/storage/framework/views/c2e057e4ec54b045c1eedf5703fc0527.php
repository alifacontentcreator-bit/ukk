

<?php $__env->startSection('title', 'Data Petugas'); ?>
<?php $__env->startSection('page-title', 'Data Petugas'); ?>
<?php $__env->startSection('page-subtitle', 'Daftar teller beserta kelas dan nomor WhatsApp yang bisa dihubungi, dapat diimpor dan diunduh sebagai Excel.'); ?>
<?php $__env->startSection('page-actions'); ?>
    <a class="btn btn-soft btn-sm" href="<?php echo e(route('supervisor.petugas.export', request()->query())); ?>"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'down']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'down']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Unduh Excel</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="split">
    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Daftar Petugas</h2><p class="section-sub"><?php echo e($petugas->total()); ?> teller &middot; <?php echo e($tanpaWa); ?> belum punya nomor WA.</p></div></div>
        <form method="GET" action="<?php echo e(route('supervisor.petugas')); ?>" class="toolbar" data-auto-filter style="border-bottom:1px solid var(--line-2)">
            <div class="field" style="flex:1 1 220px;max-width:none"><label for="pQ">Cari</label><input id="pQ" type="search" name="q" value="<?php echo e(request('q')); ?>" placeholder="Nama atau kelas"></div>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Nama</th><th>Kelas</th><th>No. WA yang bisa dihubungi</th><th>Akun</th></tr></thead>
                <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $petugas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php $wa = $sheet->waLink($p->no_telp); ?>
                    <tr>
                        <td class="name"><?php echo e($p->name); ?></td>
                        <td><?php echo e($p->kelas ?: '-'); ?></td>
                        <td class="nw"><?php if($wa): ?><a href="<?php echo e($wa); ?>" target="_blank" rel="noopener" class="more"><?php echo e($p->no_telp); ?></a><?php else: ?><span class="muted"><?php echo e($p->no_telp ?: 'Belum diisi'); ?></span><?php endif; ?></td>
                        <td><span class="badge <?php echo e($p->status === 'active' ? 'badge-ok' : 'badge-danger'); ?>"><?php echo e($p->status === 'active' ? 'Aktif' : 'Diblokir'); ?></span></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="4" class="empty-cell">Tidak ada petugas yang cocok.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($petugas->hasPages()): ?><?php if (isset($component)) { $__componentOriginal1667981b6c071c5a4f4cc9ecb2627444 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1667981b6c071c5a4f4cc9ecb2627444 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pager','data' => ['paginator' => $petugas]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pager'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['paginator' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($petugas)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1667981b6c071c5a4f4cc9ecb2627444)): ?>
<?php $attributes = $__attributesOriginal1667981b6c071c5a4f4cc9ecb2627444; ?>
<?php unset($__attributesOriginal1667981b6c071c5a4f4cc9ecb2627444); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1667981b6c071c5a4f4cc9ecb2627444)): ?>
<?php $component = $__componentOriginal1667981b6c071c5a4f4cc9ecb2627444; ?>
<?php unset($__componentOriginal1667981b6c071c5a4f4cc9ecb2627444); ?>
<?php endif; ?><?php endif; ?>
    </section>

    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Impor dari Excel</h2><p class="section-sub">Memperbarui nomor WA teller dari berkas absensi.</p></div></div>
        <form method="POST" action="<?php echo e(route('supervisor.petugas.impor')); ?>" enctype="multipart/form-data" class="card-body stack">
            <?php echo csrf_field(); ?>
            <div class="callout callout-info">
                Kolom yang dibaca: <b>Nama</b>, <b>Kelas</b>, <b>No. WA</b>. Petugas dicocokkan lewat nama (dan kelas). Baris yang tidak pasti dilewati, tidak ditebak.
            </div>
            <div class="field"><label for="pFile">File (.xlsx, .xls, .csv, maks. 2 MB)</label><input id="pFile" type="file" name="file" accept=".xlsx,.xls,.csv" required></div>
            <div class="actions">
                <button class="btn btn-primary btn-sm" type="submit"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'down']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'down']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Impor</button>
                <a class="btn btn-soft btn-sm" href="<?php echo e(route('supervisor.petugas.export', ['templat' => 1])); ?>">Unduh templat</a>
            </div>
        </form>
    </section>
</div>

<?php if(session('import_skipped') && count(session('import_skipped'))): ?>
<section class="card section">
    <div class="section-head"><div><h2 class="section-title">Baris yang Dilewati</h2><p class="section-sub">Perbaiki di Excel lalu impor ulang, atau ubah nomornya langsung di Manajemen Pengguna.</p></div></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Baris</th><th>Nama</th><th>Alasan</th></tr></thead>
            <tbody><?php $__currentLoopData = session('import_skipped'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td class="nw"><?php echo e($s['row']); ?></td><td><?php echo e($s['nama']); ?></td><td><?php echo e($s['alasan']); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.supervisor', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/supervisor/petugas.blade.php ENDPATH**/ ?>