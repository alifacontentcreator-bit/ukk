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
                <div class="section-title">Daftar Pengguna</div>
                <div class="section-sub">{{ $users->count() }} akun &middot; admin, supervisor, teller, dan nasabah</div>
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
                        <tr @class(['is-blocked' => $user->status === 'blocked'])>
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
                                <span class="badge {{ $user->status === 'active' ? 'badge-ok' : 'badge-danger' }}">
                                    {{ $user->status === 'active' ? 'Aktif' : 'Diblokir' }}
                                </span>
                            </td>
                            <td>
                                <div class="actions end">
                                    <a class="icon-btn" href="{{ route('admin.users.edit', $user) }}" title="Ubah {{ $user->name }}" aria-label="Ubah {{ $user->name }}"><x-icon name="pencil" /></a>
                                    @if($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}" onsubmit="return confirm('Anda yakin ingin {{ $user->status === 'active' ? 'memblokir' : 'membuka blokir' }} akun {{ $user->name }}?')">
                                            @csrf
                                            <button class="icon-btn {{ $user->status === 'active' ? 'is-danger' : '' }}" type="submit"
                                                    title="{{ $user->status === 'active' ? 'Blokir' : 'Buka blokir' }} {{ $user->name }}"
                                                    aria-label="{{ $user->status === 'active' ? 'Blokir' : 'Buka blokir' }} {{ $user->name }}">
                                                <x-icon :name="$user->status === 'active' ? 'lock' : 'unlock'" />
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Hapus permanen akun {{ $user->name }}? Tindakan ini tidak bisa dibatalkan. Akun yang memiliki riwayat transaksi atau laporan tidak dapat dihapus.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn is-danger" type="submit" title="Hapus {{ $user->name }}" aria-label="Hapus {{ $user->name }}"><x-icon name="trash" /></button>
                                        </form>
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
