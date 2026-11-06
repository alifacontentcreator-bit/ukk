

<?php $__env->startSection('title', 'Notifikasi Laporan'); ?>
<?php $__env->startSection('page-title', 'Notifikasi Laporan Harian'); ?>
<?php $__env->startSection('page-subtitle', 'Keputusan Supervisor atas laporan kas harian yang kamu kirim.'); ?>

<?php $__env->startSection('content'); ?>
    <section class="card">
        <div class="section-head">
            <div>
                <div class="section-title">Pemberitahuan Laporan Harian</div>
                <div class="section-sub"><?php echo e($notifications->total()); ?> pemberitahuan.</div>
            </div>
            <span class="badge badge-outline"><?php echo e($notifications->total()); ?></span>
        </div>

        <div class="list" style="padding:0 12px">
            <?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                    $report = $notification->dailyReport;
                    $ditolak = $notification->type === 'report_rejected';
                    $perluRevisi = $ditolak && $report && $report->status === 'rejected';
                    $baru = $unreadIds->contains($notification->id);
                ?>

                <div class="list-row" style="align-items:flex-start;gap:12px">
                    <div style="min-width:0">
                        <div class="name" style="display:flex;align-items:center;gap:6px">
                            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => ''.e($ditolak ? 'alert' : 'check').'','style' => 'width:13px;height:13px;color:'.e($ditolak ? 'var(--bad)' : 'var(--ok)').'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => ''.e($ditolak ? 'alert' : 'check').'','style' => 'width:13px;height:13px;color:'.e($ditolak ? 'var(--bad)' : 'var(--ok)').'']); ?>
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
                            <span><?php echo e($notification->title); ?></span>
                        </div>

                        <div class="sub"><?php echo e($notification->message); ?></div>

                        <div class="sub" style="color:var(--faint)">
                            <?php echo e($notification->created_at->format('d/m/Y H:i')); ?>

                            <?php if($report): ?>
                                &middot; laporan tanggal
                                <?php echo e(\Carbon\Carbon::parse($report->report_date)->locale('id')->translatedFormat('d F Y')); ?>

                            <?php endif; ?>
                        </div>

                        <?php if($perluRevisi): ?>
                            <form method="POST"
                                  action="<?php echo e(route('teller.laporan-harian.revisi', $report->id)); ?>"
                                  style="margin-top:8px"
                                  onsubmit="return confirm('Buka laporan ini untuk direvisi? Hitung ulang uang fisik kas akhir lalu kirim ulang ke Supervisor.')">
                                <?php echo csrf_field(); ?>
                                <button class="btn btn-primary btn-sm" type="submit">
                                    <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
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
<?php endif; ?> Revisi Laporan Harian
                                </button>
                            </form>
                        <?php elseif($ditolak && $report): ?>
                            <span class="badge badge-outline" style="margin-top:8px">Laporan sudah direvisi / dikirim ulang</span>
                        <?php elseif(!$ditolak && $report): ?>
                            <span class="badge badge-ok" style="margin-top:8px">Laporan sudah disetujui</span>
                        <?php endif; ?>
                    </div>

                    <?php if($baru): ?>
                        <span class="badge badge-ok">Baru</span>
                    <?php else: ?>
                        <span class="badge badge-outline">Dibaca</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="empty-inline">Belum ada notifikasi. Keputusan Supervisor atas laporan harian akan muncul di sini.</p>
            <?php endif; ?>
        </div>

        <?php if($notifications->hasPages()): ?>
            <div class="section" style="padding:0 12px 12px">
                <?php if (isset($component)) { $__componentOriginal1667981b6c071c5a4f4cc9ecb2627444 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1667981b6c071c5a4f4cc9ecb2627444 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pager','data' => ['paginator' => $notifications]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pager'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['paginator' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($notifications)]); ?>
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
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.teller', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/teller/notifikasi.blade.php ENDPATH**/ ?>