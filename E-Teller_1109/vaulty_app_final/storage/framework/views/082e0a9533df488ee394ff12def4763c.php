

<?php $__env->startSection('title', 'Jadwal Jaga'); ?>
<?php $__env->startSection('page-title', 'Jadwal Jaga'); ?>
<?php $__env->startSection('page-subtitle', 'Atur jadwal jaga per hari (Senin–Jumat). Cukup diatur sekali, berlaku setiap minggu.'); ?>

<?php $__env->startSection('content'); ?>
<?php $aktif = $shifts->where('is_active', true); ?>

<div class="stack">
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Jadwal Jaga Mingguan</h2>
                <p class="section-sub">
                    Pilih shift lalu centang nama petugas pada hari yang diinginkan.
                    Jadwal ini berlaku setiap minggu (bisa dipakai untuk sebulan/tahunan),
                    jadi tidak perlu diisi ulang tiap tanggal.
                </p>
            </div>
        </div>

        <?php if($aktif->isEmpty()): ?>
            <p class="sub" style="padding:0 16px 16px">
                Buat shift terlebih dahulu pada kartu "Shift Jaga" di bawah sebelum menyusun jadwal.
            </p>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:110px">Hari</th>
                        <th>Petugas Jaga</th>
                        <th style="width:330px">Tambah Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $hari; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dayNum => $dayName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $items = ($jadwal->get($dayNum) ?? collect())
                                ->sortBy(fn ($j) => ($j->shift?->start_time ?? '') . '|' . ($j->user?->name ?? ''));
                        ?>
                        <tr>
                            <td class="name"><?php echo e($dayName); ?></td>
                            <td>
                                <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $j): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <form method="POST" action="<?php echo e(route('supervisor.jadwal.destroy', $j->id)); ?>"
                                          style="display:inline-block;margin:0 4px 4px 0"
                                          onsubmit="return confirm('Hapus jadwal <?php echo e($j->user->name ?? ''); ?> pada hari <?php echo e($dayName); ?>?')">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <span class="badge badge-soft">
                                            <?php echo e($j->shift?->label() ?? '-'); ?>

                                            &middot; <?php echo e($j->user->name ?? '-'); ?><?php if($j->user?->kelas): ?> (<?php echo e($j->user->kelas); ?>)<?php endif; ?>
                                            <button type="submit" aria-label="Hapus jadwal"
                                                    style="border:0;background:transparent;color:inherit;padding:0 0 0 4px;font-size:14px;line-height:1;cursor:pointer">&times;</button>
                                        </span>
                                    </form>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <span class="sub">Belum ada petugas pada hari ini.</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($aktif->isNotEmpty()): ?>
                                    <form method="POST" action="<?php echo e(route('supervisor.jadwal.store')); ?>" class="stack" style="gap:6px">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="hari" value="<?php echo e($dayNum); ?>">
                                        <select name="duty_shift_id" aria-label="Shift hari <?php echo e($dayName); ?>" required>
                                            <?php $__currentLoopData = $aktif; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($s->id); ?>"><?php echo e($s->label()); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                        <div style="max-height:104px;overflow:auto;border:1px solid var(--line-3);border-radius:8px;padding:6px 10px;display:grid;gap:4px">
                                            <?php $__empty_1 = true; $__currentLoopData = $tellers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                                <label style="display:flex;gap:8px;align-items:center;font-weight:500;font-size:12px">
                                                    <input type="checkbox" name="petugas[]" value="<?php echo e($t->id); ?>" style="width:auto;height:auto">
                                                    <span><?php echo e($t->name); ?> <span class="sub" style="margin:0"><?php echo e($t->kelas); ?></span></span>
                                                </label>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                                <span class="sub" style="margin:0">Belum ada petugas aktif.</span>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn btn-primary btn-sm" type="submit">Simpan <?php echo e($dayName); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Shift Jaga</h2>
                <p class="section-sub">Jam shift sesuai kebijakan sekolah.</p>
            </div>
        </div>
            <div class="list">
                <?php $__empty_1 = true; $__currentLoopData = $shifts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="list-row">
                        <div><div class="name"><?php echo e($s->name); ?></div><div class="sub"><?php echo e(substr($s->start_time, 0, 5)); ?>&ndash;<?php echo e(substr($s->end_time, 0, 5)); ?></div></div>
                        <div class="actions">
                            <form method="POST" action="<?php echo e(route('supervisor.shift.toggle', $s->id)); ?>"><?php echo csrf_field(); ?><button class="badge <?php echo e($s->is_active ? 'badge-ok' : 'badge-soft'); ?>" type="submit" style="border:0;cursor:pointer"><?php echo e($s->is_active ? 'Aktif' : 'Nonaktif'); ?></button></form>
                            <form method="POST" action="<?php echo e(route('supervisor.shift.destroy', $s->id)); ?>" onsubmit="return confirm('Hapus shift ini?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-danger btn-sm" type="submit">Hapus</button></form>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="sub" style="padding:16px 0">Belum ada shift.</p>
                <?php endif; ?>
            </div>
            <form method="POST" action="<?php echo e(route('supervisor.shift.store')); ?>" class="card-body stack" style="border-top:1px solid var(--line-2)">
                <?php echo csrf_field(); ?>
                <div class="field"><label for="sName">Nama shift</label><input id="sName" type="text" name="name" maxlength="60" placeholder="Contoh: Shift 1 (Istirahat 1)" required></div>
                <div class="cols-2">
                    <div class="field"><label for="sStart">Mulai</label><input id="sStart" type="time" name="start_time" required></div>
                    <div class="field"><label for="sEnd">Selesai</label><input id="sEnd" type="time" name="end_time" required></div>
                </div>
                <button class="btn btn-soft" type="submit">Tambah shift</button>
            </form>
        </section>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.supervisor', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/supervisor/jadwal.blade.php ENDPATH**/ ?>