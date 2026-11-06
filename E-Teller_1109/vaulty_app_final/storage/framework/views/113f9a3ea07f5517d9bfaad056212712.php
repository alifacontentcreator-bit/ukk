

<?php $__env->startSection('title', 'Absensi Petugas'); ?>
<?php $__env->startSection('page-title', 'Absensi Petugas'); ?>
<?php $__env->startSection('page-subtitle', 'Pantau kehadiran teller pada shift jaga. Petugas yang tidak hadir dapat ditandai izin, sakit, atau alpa.'); ?>
<?php $__env->startSection('page-actions'); ?>
    <a class="btn btn-soft btn-sm" href="<?php echo e(route('supervisor.absensi.export', request()->query())); ?>"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
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
<div class="grid-4">
<?php $__currentLoopData = [['Hadir', $ringkasan['hadir'], 'Sesuai filter', 'check'], ['Terlambat', $ringkasan['terlambat'], 'Dari yang hadir', 'clock'], ['Izin / Sakit', $ringkasan['izin'] + $ringkasan['sakit'], 'Ditandai Supervisor', 'file'], ['Alpa', $ringkasan['alpa'], 'Tanpa keterangan', 'x']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $note, $icon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="card stat">
        <div class="stat-top"><span class="stat-label"><?php echo e($label); ?></span><span class="stat-icon"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon)]); ?>
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
        <div><div class="stat-value"><?php echo e($value); ?></div><div class="stat-note"><?php echo e($note); ?></div></div>
    </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<?php if($belumAbsen->count()): ?>
<section class="card section">
    <div class="section-head"><div><h2 class="section-title">Terjadwal Hari Ini, Belum Absen</h2><p class="section-sub">Tandai bila petugas tidak hadir.</p></div><span class="badge badge-warn"><?php echo e($belumAbsen->count()); ?> petugas</span></div>
    <div class="list">
        <?php $__currentLoopData = $belumAbsen; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $j): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="list-row">
                <div><div class="name"><?php echo e($j->user->name ?? '-'); ?></div><div class="sub"><?php echo e($j->user->kelas ?? '-'); ?> &middot; <?php echo e($j->shift?->label() ?? '-'); ?></div></div>
                <button type="button" class="btn btn-soft btn-sm" data-mark data-user="<?php echo e($j->user_id); ?>" data-date="<?php echo e($j->duty_date->toDateString()); ?>" data-shift="<?php echo e($j->duty_shift_id); ?>" data-name="<?php echo e($j->user->name ?? ''); ?>">Tandai tidak hadir</button>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</section>
<?php endif; ?>

<section class="card section">
    <form method="GET" action="<?php echo e(route('supervisor.absensi')); ?>" class="toolbar" data-auto-filter>
        <div class="field"><label for="sFrom">Dari tanggal</label><input id="sFrom" type="date" name="from" value="<?php echo e($from); ?>"></div>
        <div class="field"><label for="sTo">Sampai tanggal</label><input id="sTo" type="date" name="to" value="<?php echo e($to); ?>"></div>
        <div class="field"><label for="sTeller">Petugas</label>
            <select id="sTeller" name="teller_id">
                <option value="">Semua petugas</option>
                <?php $__currentLoopData = $tellers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($t->id); ?>" <?php if((string) request('teller_id') === (string) $t->id): echo 'selected'; endif; ?>><?php echo e($t->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div class="field"><label for="sStatus">Status</label>
            <select id="sStatus" name="status">
                <option value="">Semua</option>
                <?php $__currentLoopData = \App\Models\DutyAttendance::STATUS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(request('status') === $k): echo 'selected'; endif; ?>><?php echo e($v); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div class="field"><a href="<?php echo e(route('supervisor.absensi')); ?>" class="btn btn-soft btn-sm">Hari ini</a></div>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tanggal</th><th>Petugas</th><th>Shift</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Keterangan</th></tr></thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $absensi; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td class="nw"><?php echo e($a->duty_date->format('d/m/Y')); ?></td>
                    <td><div class="name"><?php echo e($a->user->name ?? '-'); ?></div><div class="sub"><?php echo e($a->user->kelas ?? '-'); ?></div></td>
                    <td><?php echo e($a->shift?->label() ?? 'Di luar jadwal'); ?></td>
                    <td class="nw"><?php echo e($a->check_in_at?->format('H:i') ?? '-'); ?></td>
                    <td class="nw"><?php echo e($a->check_out_at?->format('H:i') ?? '-'); ?></td>
                    <td><span class="badge <?php echo e($a->status === 'hadir' ? ($a->isLate() ? 'badge-warn' : 'badge-ok') : ($a->status === 'alpa' ? 'badge-danger' : 'badge-soft')); ?>"><?php echo e($a->statusLabel()); ?><?php echo e($a->isLate() ? ' (terlambat)' : ''); ?></span></td>
                    <td class="sub" style="min-width:160px;white-space:normal"><?php echo e($a->note ?: '-'); ?><?php if($a->marker): ?><div>oleh <?php echo e($a->marker->name); ?></div><?php endif; ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="7" class="empty-cell">Tidak ada catatan kehadiran pada rentang ini.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($absensi->hasPages()): ?><?php if (isset($component)) { $__componentOriginal1667981b6c071c5a4f4cc9ecb2627444 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1667981b6c071c5a4f4cc9ecb2627444 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pager','data' => ['paginator' => $absensi]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pager'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['paginator' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($absensi)]); ?>
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

<dialog class="et-dialog" id="markDialog">
    <form method="POST" action="<?php echo e(route('supervisor.absensi.tandai')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="user_id"><input type="hidden" name="duty_date"><input type="hidden" name="duty_shift_id">
        <div><h3>Tandai tidak hadir</h3><p data-mark-name></p></div>
        <div class="field"><label for="mStatus">Status</label>
            <select id="mStatus" name="status"><option value="izin">Izin</option><option value="sakit">Sakit</option><option value="alpa">Alpa</option></select>
        </div>
        <div class="field"><label for="mNote">Keterangan (opsional)</label><input id="mNote" type="text" name="note" maxlength="255" placeholder="Contoh: surat izin dari wali kelas"></div>
        <div class="actions end"><button type="button" class="btn btn-soft" data-mark-close>Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
    </form>
</dialog>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
    var dialog = document.getElementById('markDialog');
    if (!dialog) return;
    var form = dialog.querySelector('form');
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-mark]');
        if (b) {
            form.user_id.value = b.dataset.user;
            form.duty_date.value = b.dataset.date;
            form.duty_shift_id.value = b.dataset.shift || '';
            form.status.value = 'izin';
            form.note.value = '';
            dialog.querySelector('[data-mark-name]').textContent = b.dataset.name;
            dialog.showModal();
            return;
        }
        if (e.target.closest('[data-mark-close]') || e.target === dialog) dialog.close();
    });
})();
</script>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.supervisor', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/supervisor/absensi.blade.php ENDPATH**/ ?>