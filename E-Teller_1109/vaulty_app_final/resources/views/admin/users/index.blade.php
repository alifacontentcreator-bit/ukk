@extends('layouts.admin')

@section('title', 'Manajemen Pengguna')

@section('content')
    <div class="page-head">
        <div>
            <h1>Manajemen Pengguna</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><b>Manajemen Pengguna</b></div>
        </div>
        <div class="page-actions">
            <a class="btn btn-primary btn-sm" href="{{ route('admin.users.create') }}"><x-icon name="plus" /> Tambah Pengguna</a>
        </div>
    </div>

    <form class="toolbar-inline" method="GET" action="{{ route('admin.users') }}" data-auto-filter>
        <div class="field">
            <label for="f-id">ID</label>
            <input id="f-id" type="number" name="id" min="1" value="{{ $filters['id'] ?? '' }}" placeholder="mis. 10">
        </div>
        <div class="field">
            <label for="f-nis">NIS / NIP</label>
            <input id="f-nis" type="search" name="nis" value="{{ $filters['nis'] ?? '' }}" placeholder="Nomor induk">
        </div>
        <div class="field">
            <label for="f-name">Nama</label>
            <input id="f-name" type="search" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Nama pengguna">
        </div>
        <div class="field">
            <label for="f-role">Peran</label>
            <select id="f-role" name="role">
                <option value="">Semua peran</option>
                <option value="customer" @selected(($filters['role'] ?? '') === 'customer')>Nasabah</option>
                <option value="teller" @selected(($filters['role'] ?? '') === 'teller')>Teller</option>
                <option value="supervisor" @selected(($filters['role'] ?? '') === 'supervisor')>Supervisor</option>
                <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Admin</option>
            </select>
        </div>
        <a class="btn btn-soft btn-sm" href="{{ route('admin.users') }}"><x-icon name="refresh" /> Reset</a>
    </form>

    <section class="card section">
        <div class="section-head">
            <div>
                <div class="section-title">Impor siswa baru</div>
                <div class="section-sub">Akun yang dibuat menunggu aktivasi dan riwayatnya tetap tersimpan saat siswa dinonaktifkan.</div>
            </div>
            <a class="btn btn-soft btn-sm" href="{{ route('admin.users.template-import-siswa') }}"><x-icon name="download" /> Template Excel</a>
        </div>
        <form method="POST" action="{{ route('admin.users.import-siswa') }}" enctype="multipart/form-data" class="toolbar">
            @csrf
            <div class="field" style="flex:1 1 280px;max-width:none">
                <label for="studentImportFile">File Excel atau CSV</label>
                <input id="studentImportFile" type="file" name="file" accept=".xlsx,.xls,.csv" required>
                @error('file')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><x-icon name="upload" /> Impor siswa</button>
        </form>
        @if(session('student_import_result'))
            @php($importResult = session('student_import_result'))
            <div class="card-body">
                <p class="helper">Berhasil dibuat: {{ $importResult['created'] }}. Dilewati: {{ count($importResult['skipped']) }}.</p>
                @if($importResult['skipped'])
                    <ul class="helper">
                        @foreach($importResult['skipped'] as $skipped)
                            <li>Baris {{ $skipped['row'] }} ({{ $skipped['name'] }}): {{ $skipped['reason'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </section>

    <section class="card section">
        <div class="section-head">
            <div>
                <div class="section-title">Daftar Pengguna</div>
                <div class="section-sub">{{ $users->count() }} akun &middot; admin, supervisor, teller, dan nasabah. Akun nasabah tidak dapat dihapus permanen &mdash; nonaktifkan saja agar riwayat transaksinya tetap tersimpan.</div>
            </div>
            <span class="badge badge-outline">{{ $users->count() }} akun</span>
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
                    @forelse($users as $user)
                        @php($pinBlocked = (bool) $user->account?->pin_blocked)
                        @php($labelAksi = $user->status === 'active'
                            ? ($user->role === 'customer' ? 'Nonaktifkan' : 'Blokir')
                            : ($user->role === 'customer' ? 'Aktifkan kembali' : 'Buka blokir'))
                        <tr @class(['is-blocked' => $user->status !== 'active' || $pinBlocked])>
                            <td class="nw muted">{{ str_pad($user->id, 3, '0', STR_PAD_LEFT) }}</td>
                            <td class="nw">{{ $user->kelas ?: '-' }}</td>
                            <td class="nw muted">{{ $user->nis ?? $user->nip ?? '-' }}</td>
                            <td>
                                <div class="person">
                                    @if($user->photo)
                                        <img class="et-avatar et-avatar-photo" src="{{ asset('storage/' . $user->photo) }}" alt="Foto {{ $user->name }}">
                                    @else
                                        <span class="et-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                    @endif
                                    <span>
                                        <span class="name">{{ $user->name }}</span>
                                        <span class="sub">
                                            {{ $user->role === 'customer' ? '@' . $user->username : ($user->email ?: '@' . $user->username) }}
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ match($user->role) { 'admin' => 'badge-soft', 'teller' => 'badge-ok', 'supervisor' => 'badge-soft', default => 'badge-outline' } }}">
                                    {{ $user->role === 'customer' ? 'Nasabah' : ucfirst($user->role) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $user->status === 'active' && !$pinBlocked ? 'badge-ok' : 'badge-danger' }}">
                                    {{ $user->status !== 'active' ? 'Nonaktif' : ($pinBlocked ? 'Blokir (PIN)' : 'Aktif') }}
                                </span>
                            </td>
                            <td>
                                <div class="actions end">
                                    <a class="icon-btn" href="{{ route('admin.users.edit', $user) }}" title="Ubah {{ $user->name }}" aria-label="Ubah {{ $user->name }}"><x-icon name="pencil" /></a>
                                    @if($user->id !== auth()->id())
                                        @if($pinBlocked && $user->status === 'active')
                                            <a class="icon-btn" href="{{ route('admin.reset-pin') }}" title="Diblokir karena PIN salah 3x. Setujui pengajuan buka blokir + reset PIN di menu Reset PIN." aria-label="Diblokir karena PIN salah 3x. Buka menu Reset PIN."><x-icon name="lock" /></a>
                                        @else
                                            <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}" onsubmit="return confirm('Anda yakin ingin {{ strtolower($labelAksi) }} akun {{ $user->name }}?')">
                                                @csrf
                                                <button class="icon-btn {{ $user->status === 'active' ? 'is-danger' : '' }}" type="submit"
                                                        title="{{ $labelAksi }} {{ $user->name }}"
                                                        aria-label="{{ $labelAksi }} {{ $user->name }}">
                                                    <x-icon :name="$user->status === 'active' ? 'lock' : 'unlock'" />
                                                </button>
                                            </form>
                                        @endif
                                        @if($user->role !== 'customer')
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Hapus permanen akun {{ $user->name }}? Tindakan ini tidak bisa dibatalkan. Akun yang memiliki riwayat transaksi atau laporan tidak dapat dihapus.')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="icon-btn is-danger" type="submit" title="Hapus {{ $user->name }}" aria-label="Hapus {{ $user->name }}"><x-icon name="trash" /></button>
                                            </form>
                                        @endif
                                    @else
                                        <span class="muted" style="font-size:11px">akun Anda</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-cell">Tidak ada pengguna yang cocok dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

@endsection
