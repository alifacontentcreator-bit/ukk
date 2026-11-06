

<?php $__env->startSection('title', 'Absensi Jaga'); ?>
<?php $__env->startSection('page-title', 'Absensi Jaga'); ?>
<?php $__env->startSection('page-subtitle', 'Absen masuk dan pulang pada shift jaga Anda, lalu lihat jadwal dan riwayat kehadiran.'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $slots = $jadwalHariIni->isEmpty() ? collect([null]) : $jadwalHariIni->map(fn ($j) => $j->shift);
    $absenFor = fn ($shiftId) => $absenHariIni->first(fn ($a) => (int) $a->duty_shift_id === (int) $shiftId);
?>

<section class="card">
    <div class="section-head">
        <div><h2 class="section-title">Hari Ini</h2><p class="section-sub"><?php echo e(now()->locale('id')->translatedFormat('l, d F Y')); ?></p></div>
        <?php if($jadwalHariIni->isEmpty()): ?><span class="badge badge-soft">Tidak ada jadwal</span><?php endif; ?>
    </div>
    <div class="list">
        <?php $__currentLoopData = $slots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shift): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $a = $absenFor($shift?->id ?? 0); ?>
            <div class="list-row">
                <div>
                    <div class="name"><?php echo e($shift ? $shift->label() : 'Di luar jadwal'); ?></div>
                    <div class="sub">
                        <?php if(!$a): ?>
                            Belum absen
                        <?php elseif($a->status !== 'hadir'): ?>
                            Dicatat Supervisor: <?php echo e($a->statusLabel()); ?><?php if($a->note): ?> &middot; <?php echo e($a->note); ?><?php endif; ?>
                        <?php else: ?>
                            Masuk <?php echo e($a->check_in_at->format('H:i')); ?><?php if($a->isLate()): ?> <span class="badge badge-warn" style="margin-left:6px">Terlambat</span><?php endif; ?>
                            <?php if($a->check_out_at): ?> &middot; Pulang <?php echo e($a->check_out_at->format('H:i')); ?> <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="actions">
                    <?php if(!$a): ?>
                        <form method="POST" action="<?php echo e(route('teller.absensi.masuk')); ?>"><?php echo csrf_field(); ?><input type="hidden" name="duty_shift_id" value="<?php echo e($shift?->id); ?>"><button class="btn btn-primary btn-sm" type="submit"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'check']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>Absen Masuk</button></form>
                    <?php elseif($a->check_in_at && !$a->check_out_at): ?>
                        <form method="POST" action="<?php echo e(route('teller.absensi.pulang')); ?>" onsubmit="return confirm('Catat absen pulang sekarang?')"><?php echo csrf_field(); ?><input type="hidden" name="duty_shift_id" value="<?php echo e($shift?->id); ?>"><button class="btn btn-soft btn-sm" type="submit"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'logout']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'logout']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>Absen Pulang</button></form>
                    <?php elseif($a->check_out_at): ?>
                        <span class="badge badge-ok">Selesai</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</section>

<div class="section split-aside">
    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Jadwal 7 Hari ke Depan</h2><p class="section-sub">Diatur oleh Supervisor.</p></div></div>
        <div class="list">
            <?php $__empty_1 = true; $__currentLoopData = $jadwalMendatang; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $j): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="list-row"><div><div class="name"><?php echo e($j->duty_date->locale('id')->translatedFormat('l, d F')); ?></div><div class="sub"><?php echo e($j->shift?->label() ?? '-'); ?></div></div></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="sub" style="padding:24px 0;text-align:center">Belum ada jadwal jaga.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Riwayat Kehadiran</h2><p class="section-sub"><?php echo e($riwayat->total()); ?> catatan.</p></div></div>
        <form method="GET" action="<?php echo e(route('teller.absensi')); ?>" class="toolbar" data-auto-filter style="border-bottom:1px solid var(--line-2)">
            <div class="field"><label for="aFrom">Dari</label><input id="aFrom" type="date" name="from" value="<?php echo e(request('from')); ?>"></div>
            <div class="field"><label for="aTo">Sampai</label><input id="aTo" type="date" name="to" value="<?php echo e(request('to')); ?>"></div>
            <div class="field"><label for="aStatus">Status</label>
                <select id="aStatus" name="status">
                    <option value="">Semua</option>
                    <?php $__currentLoopData = \App\Models\DutyAttendance::STATUS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(request('status') === $k): echo 'selected'; endif; ?>><?php echo e($v); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Shift</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead>
                <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $riwayat; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td class="nw"><?php echo e($a->duty_date->format('d/m/Y')); ?></td>
                        <td><?php echo e($a->shift?->name ?? 'Di luar jadwal'); ?></td>
                        <td class="nw"><?php echo e($a->check_in_at?->format('H:i') ?? '-'); ?></td>
                        <td class="nw"><?php echo e($a->check_out_at?->format('H:i') ?? '-'); ?></td>
                        <td><span class="badge <?php echo e($a->status === 'hadir' ? ($a->isLate() ? 'badge-warn' : 'badge-ok') : ($a->status === 'alpa' ? 'badge-danger' : 'badge-soft')); ?>"><?php echo e($a->statusLabel()); ?><?php echo e($a->isLate() ? ' (terlambat)' : ''); ?></span></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="5" class="empty-cell">Belum ada catatan kehadiran.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($riwayat->hasPages()): ?><?php if (isset($component)) { $__componentOriginal1667981b6c071c5a4f4cc9ecb2627444 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1667981b6c071c5a4f4cc9ecb2627444 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pager','data' => ['paginator' => $riwayat]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pager'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['paginator' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($riwayat)]); ?>
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
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.teller', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/teller/absensi.blade.php ENDPATH**/ ?>