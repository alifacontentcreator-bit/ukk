

<?php $__env->startSection('title', 'Laporan Harian'); ?>
<?php $__env->startSection('page-title', 'Laporan Kas Harian'); ?>
<?php $__env->startSection('page-subtitle', 'Catat kas akhir dan kirim laporan transaksi harian kepada Supervisor.'); ?>

<?php $__env->startSection('page-actions'); ?>
    <form method="GET" action="<?php echo e(route('teller.laporan')); ?>" class="actions no-print">
        <div class="field">
            <label for="laporan_tanggal">Tanggal laporan</label>
            <select id="laporan_tanggal" name="tanggal" onchange="this.form.submit()">
                <option value="<?php echo e(today()->toDateString()); ?>" <?php if($tanggal === today()->toDateString()): echo 'selected'; endif; ?>><?php echo e(today()->format('d/m/Y')); ?> (hari ini)</option>
                <?php $__currentLoopData = $editableReports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draft): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if(\Carbon\Carbon::parse($draft->report_date)->toDateString() !== today()->toDateString()): ?>
                        <option value="<?php echo e(\Carbon\Carbon::parse($draft->report_date)->toDateString()); ?>" <?php if($tanggal === \Carbon\Carbon::parse($draft->report_date)->toDateString()): echo 'selected'; endif; ?>><?php echo e(\Carbon\Carbon::parse($draft->report_date)->format('d/m/Y')); ?> (<?php echo e($draft->status === 'rejected' ? 'ditolak, perlu direvisi' : 'perlu diperbaiki'); ?>)</option>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
    </form>
    <a class="btn btn-soft btn-sm no-print" href="<?php echo e(route('teller.koreksi')); ?>"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'history']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'history']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Riwayat transaksi</a>
    <?php if($reportHariIni): ?>
        <a class="btn btn-soft btn-sm no-print" href="<?php echo e(route('teller.laporan.cetak', $reportHariIni->id)); ?>" target="_blank" rel="noopener"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'printer']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'printer']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Cetak Rekap</a>
    <?php else: ?>
        <button class="btn btn-soft btn-sm no-print" type="button" onclick="window.print()"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'printer']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'printer']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Cetak</button>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $kasAwal = (float) ($reportHariIni->saldo_awal ?? 0);
    $pengembalian = (float) ($pengembalian ?? 0);
    $mutasiBersih = (float) $setoran - (float) $penarikan - $pengembalian;
    $kasAkhirSistem = $kasAwal + $mutasiBersih;
?>

    <?php if($unreadReportCount > 0): ?>
        <div class="alert alert-danger section" role="status">
            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'alert']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'alert']); ?>
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
                Ada <?php echo e($unreadReportCount); ?> notifikasi baru dari Supervisor tentang laporan harian.
                <a href="<?php echo e(route('teller.notifikasi')); ?>">Lihat notifikasi</a>
            </span>
        </div>
    <?php endif; ?>

    <?php if($tanggal === today()->toDateString() || in_array($reportHariIni?->status, ['draft', 'rejected'], true)): ?>
        <section class="panel no-print">
            <div class="panel-head"><span>Status Laporan Harian</span><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
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
<?php endif; ?></div>
            <div class="panel-body" style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:center">
                <div>
                    <?php if(!$reportHariIni && $tanggal === today()->toDateString()): ?>
                        <div class="name" style="font-size:13px">Belum dibuat</div>
                        <p class="helper">Buat laporan untuk mulai merekap kas harian.</p>
                    <?php elseif($reportHariIni->status == 'draft'): ?>
                        <div class="name" style="font-size:13px;color:var(--warn)">Draf tersimpan</div>
                        <p class="helper">Periksa kas akhir fisik, lalu kirim laporan kepada Supervisor.</p>
                    <?php elseif($reportHariIni->status == 'rejected'): ?>
                        <div class="name" style="font-size:13px;color:var(--bad)">Laporan ditolak Supervisor</div>
                        <p class="helper"><?php echo e($reportHariIni->rejected_reason ?: 'Laporan perlu direvisi agar kas fisik sesuai dengan kas sistem.'); ?></p>
                    <?php elseif($reportHariIni->status == 'submitted'): ?>
                        <div class="name" style="font-size:13px">Menunggu verifikasi</div>
                        <p class="helper">Laporan sudah dikirim dan menunggu pemeriksaan Supervisor.</p>
                    <?php elseif($reportHariIni->status == 'approved'): ?>
                        <div class="name" style="font-size:13px;color:var(--ok)">Laporan disetujui</div>
                        <p class="helper">Laporan kas harian Anda telah selesai diperiksa.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if(!$reportHariIni): ?>
                        <form method="POST" action="<?php echo e(route('teller.laporan-harian.draft')); ?>">
                            <?php echo csrf_field(); ?>
                            <button class="btn btn-primary btn-sm" type="submit"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'save']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'save']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Buat Laporan Harian</button>
                        </form>
                    <?php elseif($reportHariIni->status == 'rejected'): ?>
                        <form method="POST" action="<?php echo e(route('teller.laporan-harian.revisi', $reportHariIni->id)); ?>"
                              onsubmit="return confirm('Buka laporan ini untuk direvisi? Hitung ulang uang fisik kas akhir lalu kirim ulang ke Supervisor.')">
                            <?php echo csrf_field(); ?>
                            <button class="btn btn-primary btn-sm" type="submit"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'refresh']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'refresh']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Revisi Laporan Harian</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($reportHariIni && $reportHariIni->status == 'draft'): ?>
                <form method="POST" action="<?php echo e(route('teller.laporan-harian.kirim')); ?>"
                      onsubmit="return confirm('Pastikan jumlah uang fisik kas akhir sudah dihitung dengan benar.')">
                    <?php echo csrf_field(); ?>
                                        <input type="hidden" name="tanggal" value="<?php echo e($tanggal); ?>">
                    <div class="card-body" style="border-top:1px solid var(--line-2)">
                        <span class="section-label">Rincian lembaran uang fisik kas akhir</span>
                        <div class="form-grid-3" style="grid-template-columns:repeat(4,minmax(0,1fr))">
                            <?php $__currentLoopData = \App\Models\DailyReport::PECAHAN_FISIK; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pecahan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="field">
                                    <label for="<?php echo e($pecahan['kolom']); ?>">Rp <?php echo e(number_format($pecahan['nominal'], 0, ',', '.')); ?></label>
                                    <input id="<?php echo e($pecahan['kolom']); ?>" type="number" name="<?php echo e($pecahan['kolom']); ?>"
                                           value="<?php echo e(old($pecahan['kolom'], $reportHariIni->{$pecahan['kolom']} ?? 0)); ?>"
                                           min="0" required>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>

                        <span class="section-label" style="margin-top:14px">Rincian lembaran saldo awal (opsional)</span>
                        <p class="helper" style="margin:0 0 8px">Hitung lembaran modal yang kamu terima. Bila dikosongkan, cetakan memakai total modal sistem.</p>
                        <div class="form-grid-3" style="grid-template-columns:repeat(4,minmax(0,1fr))">
                            <?php $__currentLoopData = \App\Models\DailyReport::PECAHAN_AWAL; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pecahan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="field">
                                    <label for="<?php echo e($pecahan['kolom']); ?>">Rp <?php echo e(number_format($pecahan['nominal'], 0, ',', '.')); ?></label>
                                    <input id="<?php echo e($pecahan['kolom']); ?>" type="number" name="<?php echo e($pecahan['kolom']); ?>"
                                           value="<?php echo e(old($pecahan['kolom'], $reportHariIni->{$pecahan['kolom']} ?? 0)); ?>"
                                           min="0">
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                    <div class="form-actions" style="padding:0 12px 12px;margin:0;border-top:0">
                        <button class="btn btn-primary btn-sm" type="submit"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'send']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'send']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Kirim Laporan ke Supervisor</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>


    <div class="grid-4 section">
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Kas Awal</span>
                <span class="stat-icon"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'vault']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'vault']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span>
            </div>
            <div>
                <div class="stat-value">Rp <?php echo e(number_format($kasAwal, 0, ',', '.')); ?></div>
                <div class="stat-note">Modal dari Supervisor</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Total Setoran</span>
                <span class="stat-icon"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
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
<?php endif; ?></span>
            </div>
            <div>
                <div class="stat-value" style="color:var(--ok)">+ Rp <?php echo e(number_format($setoran, 0, ',', '.')); ?></div>
                <div class="stat-note">Uang masuk dari nasabah</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Total Penarikan</span>
                <span class="stat-icon"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'up']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'up']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span>
            </div>
            <div>
                <div class="stat-value" style="color:var(--warn)">&minus; Rp <?php echo e(number_format($penarikan, 0, ',', '.')); ?></div>
                <div class="stat-note">Uang keluar untuk nasabah</div>
            </div>
        </div>
        <div class="card stat stat-hi">
            <div class="stat-top">
                <span class="stat-label">Kas Akhir Sistem</span>
                <span class="stat-icon"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'coins']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'coins']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span>
            </div>
            <div>
                <div class="stat-value">Rp <?php echo e(number_format($kasAkhirSistem, 0, ',', '.')); ?></div>
                <div class="stat-note">Kas awal + setoran &minus; penarikan &minus; pengembalian</div>
            </div>
        </div>
    </div>


    <section class="card section">
        <div class="section-head">
            <div>
                <div class="section-title">Rincian Transaksi Teller</div>
                <div class="section-sub"><?php echo e($summary['transaction_count']); ?> transaksi pada tanggal ini.</div>
            </div>
            <span class="badge badge-outline"><?php echo e(\Carbon\Carbon::parse($tanggal)->format('d/m/Y')); ?></span>
        </div>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Waktu</th>
                        <th>No. referensi</th>
                        <th>Nasabah</th>
                        <th>Jenis</th>
                        <th class="num">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $trx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="nw muted"><?php echo e($index + 1); ?></td>
                            <td class="nw"><?php echo e($trx->created_at->format('H:i')); ?></td>
                            <td class="nw mono"><?php echo e($trx->transaction_code); ?></td>
                            <td><?php echo e($trx->account->user->name ?? 'Anonim'); ?></td>
                            <td><span class="badge <?php echo e($trx->type === 'deposit' ? 'badge-ok' : 'badge-warn'); ?>"><?php echo e($trx->type === 'deposit' ? 'Setoran' : 'Penarikan'); ?></span></td>
                            <td class="amount" style="color:<?php echo e($trx->type === 'deposit' ? 'var(--ok)' : 'var(--warn)'); ?>">
                                <?php echo e($trx->type === 'deposit' ? '+' : '−'); ?> Rp <?php echo e(number_format($trx->amount, 0, ',', '.')); ?>

                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="6" class="empty-cell">Tidak ada transaksi pada tanggal ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.teller', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/teller/laporan-harian.blade.php ENDPATH**/ ?>