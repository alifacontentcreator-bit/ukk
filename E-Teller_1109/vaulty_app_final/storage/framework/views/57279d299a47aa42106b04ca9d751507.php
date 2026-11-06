

<?php $__env->startSection('title', 'Manajemen Pengguna'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-head">
        <div>
            <h1>Manajemen Pengguna</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><b>Manajemen Pengguna</b></div>
        </div>
        <div class="page-actions">
            <a class="btn btn-primary btn-sm" href="<?php echo e(route('admin.users.create')); ?>"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'plus']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'plus']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Tambah Pengguna</a>
        </div>
    </div>

    <form class="toolbar-inline" method="GET" action="<?php echo e(route('admin.users')); ?>" data-auto-filter>
        <div class="field">
            <label for="f-id">ID</label>
            <input id="f-id" type="number" name="id" min="1" value="<?php echo e($filters['id'] ?? ''); ?>" placeholder="mis. 10">
        </div>
        <div class="field">
            <label for="f-nis">NIS / NIP</label>
            <input id="f-nis" type="search" name="nis" value="<?php echo e($filters['nis'] ?? ''); ?>" placeholder="Nomor induk">
        </div>
        <div class="field">
            <label for="f-name">Nama</label>
            <input id="f-name" type="search" name="name" value="<?php echo e($filters['name'] ?? ''); ?>" placeholder="Nama pengguna">
        </div>
        <div class="field">
            <label for="f-role">Peran</label>
            <select id="f-role" name="role">
                <option value="">Semua peran</option>
                <option value="customer" <?php if(($filters['role'] ?? '') === 'customer'): echo 'selected'; endif; ?>>Nasabah</option>
                <option value="teller" <?php if(($filters['role'] ?? '') === 'teller'): echo 'selected'; endif; ?>>Teller</option>
                <option value="supervisor" <?php if(($filters['role'] ?? '') === 'supervisor'): echo 'selected'; endif; ?>>Supervisor</option>
                <option value="admin" <?php if(($filters['role'] ?? '') === 'admin'): echo 'selected'; endif; ?>>Admin</option>
            </select>
        </div>
        <a class="btn btn-soft btn-sm" href="<?php echo e(route('admin.users')); ?>"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
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
<?php endif; ?> Reset</a>
    </form>

    <section class="card section">
        <div class="section-head">
            <div>
                <div class="section-title">Impor siswa baru</div>
                <div class="section-sub">Akun yang dibuat menunggu aktivasi dan riwayatnya tetap tersimpan saat siswa dinonaktifkan.</div>
            </div>
            <a class="btn btn-soft btn-sm" href="<?php echo e(route('admin.users.template-import-siswa')); ?>"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'download']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'download']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Template Excel</a>
        </div>
        <form method="POST" action="<?php echo e(route('admin.users.import-siswa')); ?>" enctype="multipart/form-data" class="toolbar">
            <?php echo csrf_field(); ?>
            <div class="field" style="flex:1 1 280px;max-width:none">
                <label for="studentImportFile">File Excel atau CSV</label>
                <input id="studentImportFile" type="file" name="file" accept=".xlsx,.xls,.csv" required>
                <?php $__errorArgs = ['file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="form-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'upload']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'upload']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Impor siswa</button>
        </form>
        <?php if(session('student_import_result')): ?>
            <?php ($importResult = session('student_import_result')); ?>
            <div class="card-body">
                <p class="helper">Berhasil dibuat: <?php echo e($importResult['created']); ?>. Dilewati: <?php echo e(count($importResult['skipped'])); ?>.</p>
                <?php if($importResult['skipped']): ?>
                    <ul class="helper">
                        <?php $__currentLoopData = $importResult['skipped']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $skipped): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li>Baris <?php echo e($skipped['row']); ?> (<?php echo e($skipped['name']); ?>): <?php echo e($skipped['reason']); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card section">
        <div class="section-head">
            <div>
                <div class="section-title">Daftar Pengguna</div>
                <div class="section-sub"><?php echo e($users->count()); ?> akun &middot; admin, supervisor, teller, dan nasabah</div>
            </div>
            <span class="badge badge-outline"><?php echo e($users->count()); ?> akun</span>
        </div>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kelas</th>
                        <th>NIS / NIP</th>
                        <th>Nama</th>
                        <th>Peran</th>
                        <th>Status</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-blocked' => $user->status === 'blocked']); ?>">
                            <td class="nw muted"><?php echo e(str_pad($user->id, 3, '0', STR_PAD_LEFT)); ?></td>
                            <td class="nw"><?php echo e($user->kelas ?: '-'); ?></td>
                            <td class="nw muted"><?php echo e($user->nis ?? $user->nip ?? '-'); ?></td>
                            <td>
                                <div class="person">
                                    <?php if($user->photo): ?>
                                        <img class="et-avatar et-avatar-photo" src="<?php echo e(asset('storage/' . $user->photo)); ?>" alt="Foto <?php echo e($user->name); ?>">
                                    <?php else: ?>
                                        <span class="et-avatar"><?php echo e(strtoupper(substr($user->name, 0, 1))); ?></span>
                                    <?php endif; ?>
                                    <span>
                                        <span class="name"><?php echo e($user->name); ?></span>
                                        <span class="sub">
                                            <?php echo e($user->role === 'customer' ? '@' . $user->username : ($user->email ?: '@' . $user->username)); ?>

                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo e(match($user->role) { 'admin' => 'badge-soft', 'teller' => 'badge-ok', 'supervisor' => 'badge-soft', default => 'badge-outline' }); ?>">
                                    <?php echo e($user->role === 'customer' ? 'Nasabah' : ucfirst($user->role)); ?>

                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo e($user->status === 'active' ? 'badge-ok' : 'badge-danger'); ?>">
                                    <?php echo e($user->status === 'active' ? 'Aktif' : 'Diblokir'); ?>

                                </span>
                            </td>
                            <td>
                                <div class="actions end">
                                    <a class="icon-btn" href="<?php echo e(route('admin.users.edit', $user)); ?>" title="Ubah <?php echo e($user->name); ?>" aria-label="Ubah <?php echo e($user->name); ?>"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'pencil']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'pencil']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></a>
                                    <?php if($user->id !== auth()->id()): ?>
                                        <form method="POST" action="<?php echo e(route('admin.users.toggle-status', $user)); ?>" onsubmit="return confirm('Anda yakin ingin <?php echo e($user->status === 'active' ? 'memblokir' : 'membuka blokir'); ?> akun <?php echo e($user->name); ?>?')">
                                            <?php echo csrf_field(); ?>
                                            <button class="icon-btn <?php echo e($user->status === 'active' ? 'is-danger' : ''); ?>" type="submit"
                                                    title="<?php echo e($user->status === 'active' ? 'Blokir' : 'Buka blokir'); ?> <?php echo e($user->name); ?>"
                                                    aria-label="<?php echo e($user->status === 'active' ? 'Blokir' : 'Buka blokir'); ?> <?php echo e($user->name); ?>">
                                                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $user->status === 'active' ? 'lock' : 'unlock']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user->status === 'active' ? 'lock' : 'unlock')]); ?>
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
                                            </button>
                                        </form>
                                        <form method="POST" action="<?php echo e(route('admin.users.destroy', $user)); ?>" onsubmit="return confirm('Hapus permanen akun <?php echo e($user->name); ?>? Tindakan ini tidak bisa dibatalkan. Akun yang memiliki riwayat transaksi atau laporan tidak dapat dihapus.')">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button class="icon-btn is-danger" type="submit" title="Hapus <?php echo e($user->name); ?>" aria-label="Hapus <?php echo e($user->name); ?>"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'trash']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'trash']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted" style="font-size:11px">akun Anda</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="7" class="empty-cell">Tidak ada pengguna yang cocok dengan filter.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/admin/users/index.blade.php ENDPATH**/ ?>