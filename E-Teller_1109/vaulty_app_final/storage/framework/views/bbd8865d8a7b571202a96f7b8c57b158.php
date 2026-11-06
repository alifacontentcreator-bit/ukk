<?php $__env->startSection('title', 'Laporan & Persetujuan'); ?>
<?php $__env->startSection('page-title', 'Laporan & Persetujuan Kas'); ?>
<?php $__env->startSection('page-subtitle', 'Periksa kesesuaian uang fisik di laci Teller dengan catatan transaksi sistem.'); ?>

<?php $__env->startSection('content'); ?>

<?php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
?>


<section class="card" style="margin-bottom:20px">
    <form method="GET" action="<?php echo e(route('supervisor.laporan')); ?>"
          class="toolbar" data-auto-filter>

        <div class="field">
            <label for="lFrom">Dari tanggal</label>
            <input id="lFrom" type="date" name="from"
                   value="<?php echo e(request('from')); ?>">
        </div>

        <div class="field">
            <label for="lTo">Sampai tanggal</label>
            <input id="lTo" type="date" name="to"
                   value="<?php echo e(request('to')); ?>">
        </div>

        <div class="field">
            <label for="lTeller">Teller</label>
            <select id="lTeller" name="teller_id">
                <option value="">Semua Teller</option>
                <?php $__currentLoopData = $tellers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($t->id); ?>"
                        <?php if((string) request('teller_id') === (string) $t->id): echo 'selected'; endif; ?>>
                        <?php echo e($t->name); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        <div class="field">
            <label for="lStatus">Status laporan</label>
            <select id="lStatus" name="status">
                <option value="">Semua status</option>
                <option value="submitted" <?php if(request('status') === 'submitted'): echo 'selected'; endif; ?>>
                    Menunggu validasi
                </option>
                <option value="approved" <?php if(request('status') === 'approved'): echo 'selected'; endif; ?>>
                    Disetujui
                </option>
                <option value="rejected" <?php if(request('status') === 'rejected'): echo 'selected'; endif; ?>>
                    Ditolak
                </option>
            </select>
        </div>

        <div class="field">
            <label for="lKas">Posisi kas</label>
            <select id="lKas" name="kas">
                <option value="">Semua posisi</option>
                <option value="pending" <?php if(request('kas') === 'pending'): echo 'selected'; endif; ?>>
                    Masih di laci Teller
                </option>
                <option value="approved" <?php if(request('kas') === 'approved'): echo 'selected'; endif; ?>>
                    Sudah di brankas
                </option>
            </select>
        </div>

        <div class="field">
            <a href="<?php echo e(route('supervisor.laporan')); ?>"
               class="btn btn-soft">
                Bersihkan filter
            </a>
        </div>
    </form>

    <div class="section-head"
         style="border-top:1px solid var(--line-2);border-bottom:0">

        <p class="section-sub" style="margin:0">
            <?php echo e($counts['menunggu']); ?> laporan menunggu validasi
            &middot;
            <?php echo e($counts['siap_pindah']); ?> kas siap dipindahkan ke brankas
        </p>
    </div>
</section>


<div class="stack">

    <?php $__empty_1 = true; $__currentLoopData = $reports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

        <?php
            /*
             * Total fisik hanya berasal dari seluruh pecahan uang yang
             * dihitung Teller (Rp100.000, Rp50.000, Rp20.000, Rp5.000).
             *
             * Saldo awal tidak ditambahkan lagi karena pecahan sudah
             * menghitung seluruh uang di laci.
             */

            $totalFisik = $report->totalFisik();
            $totalSistem = $report->saldoAkhirSistem();
            $selisih = $report->selisihKas();

            $klop = abs($selisih) < 0.01;
        ?>

        <article class="card report-card">

            
            <div class="report-head">
                <div>
                    <div class="name">
                        Teller: <?php echo e($report->teller->name ?? 'Tidak diketahui'); ?>

                    </div>

                    <div class="sub">
                        Laporan tanggal
                        <?php echo e(\Carbon\Carbon::parse($report->report_date)
                            ->locale('id')
                            ->translatedFormat('d F Y')); ?>

                    </div>
                </div>

                <?php if($report->status === 'submitted'): ?>
                    <span class="badge badge-warn">
                        Menunggu validasi
                    </span>
                <?php elseif($report->status === 'approved'): ?>
                    <span class="badge badge-ok">
                        Disetujui
                    </span>
                <?php elseif($report->status === 'rejected'): ?>
                    <span class="badge badge-danger">
                        Ditolak
                    </span>
                <?php endif; ?>
            </div>

            
            <div class="report-compare">

                
                <div class="report-col">
                    <h3>Catatan Sistem</h3>

                    <div class="kv-row">
                        <span>Saldo awal laci</span>
                        <b><?php echo e($rp($report->saldo_awal)); ?></b>
                    </div>

                    <div class="kv-row">
                        <span>Setoran masuk</span>
                        <b class="is-ok">
                            + <?php echo e($rp($report->deposit_total)); ?>

                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Penarikan keluar</span>
                        <b class="is-bad">
                            - <?php echo e($rp($report->withdrawal_total)); ?>

                        </b>
                    </div>

                    <div class="kv-row kv-total">
                        <span>Total kas seharusnya</span>
                        <b><?php echo e($rp($totalSistem)); ?></b>
                    </div>
                </div>

                
                <div class="report-col">
                    <h3>Uang Fisik di Laci</h3>

                    <div class="kv-row">
                        <span>Uang Rp100.000</span>
                        <b>
                            <?php echo e($report->pecahan_100k); ?> lembar
                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Nilai pecahan Rp100.000</span>
                        <b>
                            <?php echo e($rp($report->pecahan_100k * 100000)); ?>

                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Uang Rp50.000</span>
                        <b>
                            <?php echo e($report->pecahan_50k); ?> lembar
                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Nilai pecahan Rp50.000</span>
                        <b>
                            <?php echo e($rp($report->pecahan_50k * 50000)); ?>

                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Uang Rp20.000</span>
                        <b>
                            <?php echo e($report->pecahan_20k); ?> lembar
                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Nilai pecahan Rp20.000</span>
                        <b>
                            <?php echo e($rp($report->pecahan_20k * 20000)); ?>

                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Uang Rp5.000</span>
                        <b>
                            <?php echo e($report->pecahan_5k); ?> lembar
                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Nilai pecahan Rp5.000</span>
                        <b>
                            <?php echo e($rp($report->pecahan_5k * 5000)); ?>

                        </b>
                    </div>

                    <div class="kv-row kv-total">
                        <span>Total uang fisik</span>
                        <b><?php echo e($rp($totalFisik)); ?></b>
                    </div>
                </div>

            </div>

            
            <?php if($report->status === 'rejected' && $report->rejected_reason): ?>
                <div style="border-top:1px solid var(--line-2);padding:12px 16px">
                    <strong class="is-bad">Laporan ditolak Supervisor</strong>
                    <p class="sub" style="margin:4px 0 0"><?php echo e($report->rejected_reason); ?></p>
                    <p class="sub" style="margin:2px 0 0;color:var(--faint)">
                        Ditolak
                        <?php echo e($report->rejected_at
                            ? \Carbon\Carbon::parse($report->rejected_at)->format('d/m/Y H:i')
                            : '-'); ?>

                        oleh <?php echo e($report->rejecter->name ?? 'Supervisor'); ?>

                    </p>
                </div>
            <?php endif; ?>

            
            <div class="report-foot">

                <div>
                    <?php if($klop): ?>
                        <strong class="is-ok">
                            Kas sesuai, tidak ada selisih
                        </strong>
                    <?php else: ?>
                        <strong class="is-bad">
                            Selisih <?php echo e($selisih > 0 ? '+' : '-'); ?>

                            <?php echo e($rp(abs($selisih))); ?>

                        </strong>

                        <small>
                            Laporan belum dapat disetujui karena
                            jumlah kas fisik dan sistem berbeda.
                        </small>
                    <?php endif; ?>
                </div>

                
                <div class="actions" style="gap:8px;flex-wrap:wrap">

                    <a class="btn btn-soft"
                       href="<?php echo e(route('supervisor.laporan.cetak-laporan', $report->id)); ?>"
                       target="_blank"
                       rel="noopener">
                        Cetak rekap
                    </a>

                    <?php if($report->status === 'submitted'): ?>

                        <form
                            action="<?php echo e(route('supervisor.laporan.approve', $report->id)); ?>"
                            method="POST"
                            onsubmit="return confirm('Setujui laporan ini? Pastikan uang fisik sudah dihitung ulang.')">

                            <?php echo csrf_field(); ?>

                            <button type="submit"
                                    class="btn btn-primary"
                                    <?php if(!$klop): echo 'disabled'; endif; ?>>
                                Setujui laporan
                            </button>
                        </form>

                        <button type="button"
                                class="btn btn-danger"
                                data-reject="<?php echo e(route('supervisor.laporan.reject', $report->id)); ?>"
                                data-reject-title="Tolak laporan <?php echo e($report->teller->name ?? 'Teller'); ?> tanggal <?php echo e(\Carbon\Carbon::parse($report->report_date)->format('d/m/Y')); ?>">
                            Tolak laporan
                        </button>

                    <?php elseif($report->status === 'approved'
                        && $report->transfer_status === 'pending'): ?>

                        <form
                            action="<?php echo e(route('supervisor.laporan.transfer', $report->id)); ?>"
                            method="POST"
                            onsubmit="return confirm('Pastikan uang fisik sudah benar-benar diterima Supervisor sebelum mencatat pengembalian kas.')">

                            <?php echo csrf_field(); ?>

                            <button type="submit" class="btn btn-primary">
                                Pindahkan kas ke brankas
                            </button>
                        </form>

                    <?php elseif($report->status === 'approved'
                        && $report->transfer_status === 'approved'): ?>

                        <span class="badge badge-ok">
                            Kas sudah tercatat di brankas
                        </span>

                    <?php elseif($report->status === 'rejected'
                        && $report->transfer_status !== 'approved'): ?>

                        <div style="width:100%;max-width:380px">

                            <span class="badge badge-danger">
                                Laporan ditolak
                            </span>

                            <form
                                action="<?php echo e(route('supervisor.laporan.reopen', $report->id)); ?>"
                                method="POST"
                                style="margin-top:12px"
                                onsubmit="return confirm('Buka kembali laporan ini?')">

                                <?php echo csrf_field(); ?>

                                <div class="field">
                                    <label for="reopen_reason_<?php echo e($report->id); ?>">
                                        Alasan membuka kembali laporan
                                    </label>

                                    <textarea
                                        id="reopen_reason_<?php echo e($report->id); ?>"
                                        name="reason"
                                        rows="3"
                                        maxlength="500"
                                        required
                                        placeholder="Masukkan alasan pembukaan kembali laporan."></textarea>
                                </div>

                                <button type="submit"
                                        class="btn btn-primary"
                                        style="margin-top:10px">
                                    Buka Kembali Laporan
                                </button>
                            </form>
                        </div>

                    <?php else: ?>

                        <span class="badge badge-danger">
                            Laporan ditolak
                        </span>

                    <?php endif; ?>

                </div>
            </div>

            
            <?php if($report->reopenLogs && $report->reopenLogs->count()): ?>

                <div style="border-top:1px solid var(--line-2);padding-top:12px;margin-top:12px">

                    <strong>Riwayat pembukaan kembali</strong>

                    <?php $__currentLoopData = $report->reopenLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="sub" style="margin-top:8px">
                            <?php echo e($log->reason); ?>

                            &middot;
                            <?php echo e($log->reopened_at
                                ? \Carbon\Carbon::parse($log->reopened_at)
                                    ->format('d-m-Y H:i')
                                : '-'); ?>

                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                </div>

            <?php endif; ?>

        </article>

    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>

        <div class="card" style="padding:48px;text-align:center">
            <div class="name">
                Belum ada laporan masuk
            </div>

            <p class="sub" style="margin-top:6px">
                Teller belum mengirim laporan penutupan kas.
            </p>
        </div>

    <?php endif; ?>

</div>


<?php if($reports->hasPages()): ?>
    <div class="section">
        <?php if (isset($component)) { $__componentOriginal1667981b6c071c5a4f4cc9ecb2627444 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1667981b6c071c5a4f4cc9ecb2627444 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pager','data' => ['paginator' => $reports]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pager'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['paginator' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reports)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1667981b6c071c5a4f4cc9ecb2627444)): ?>
<?php $attributes = $__attributesOriginal1667981b6c071c5a4f4cc9ecb2627444; ?>
<?php unset($__attributesOriginal1667981b6c071c5a4f4cc9ecb2627444); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1667981b6c071c5a4f4cc9ecb2627444)): ?>
<?php $component = $__componentOriginal1667981b6c071c5a4f4cc9ecb2627444; ?>
<?php unset($__componentOriginal1667981b6c071c5a4f4cc9ecb2627444); ?>
<?php endif; ?>
    </div>
<?php endif; ?>


<?php if (isset($component)) { $__componentOriginala5652acfc2cfff008a27b82b90f7e53f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala5652acfc2cfff008a27b82b90f7e53f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.reject-dialog','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('reject-dialog'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala5652acfc2cfff008a27b82b90f7e53f)): ?>
<?php $attributes = $__attributesOriginala5652acfc2cfff008a27b82b90f7e53f; ?>
<?php unset($__attributesOriginala5652acfc2cfff008a27b82b90f7e53f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala5652acfc2cfff008a27b82b90f7e53f)): ?>
<?php $component = $__componentOriginala5652acfc2cfff008a27b82b90f7e53f; ?>
<?php unset($__componentOriginala5652acfc2cfff008a27b82b90f7e53f); ?>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.supervisor', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/supervisor/laporan.blade.php ENDPATH**/ ?>