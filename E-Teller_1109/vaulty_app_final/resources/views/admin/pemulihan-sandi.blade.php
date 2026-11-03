@extends('layouts.admin')

@section('title', 'Permintaan Atur Ulang Sandi')

@section('content')
    <div class="page-head">
        <div>
            <h1>Permintaan Atur Ulang Sandi</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><b>Atur Ulang Sandi</b></div>
        </div>
    </div>

    @if($errors->has('recovery'))
        <div class="alert alert-danger"><x-icon name="alert" /><span>{{ $errors->first('recovery') }}</span></div>
    @endif

    <section class="card">
        <div class="section-head">
            <div>
                <div class="section-title">Permintaan Teller dan Supervisor</div>
                <div class="section-sub">Cocokkan data identitas sebelum mereset sandi dan mengirim sandi awal ke email pengguna.</div>
            </div>
            <span class="badge badge-outline">{{ $requests->count() }} permintaan</span>
        </div>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Peran</th>
                        <th>Data pencocokan</th>
                        <th>Email terdaftar</th>
                        <th>Diminta</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $request)
                        <tr>
                            <td>
                                <span class="name">{{ $request->name }}</span>
                                @if($request->status === 'email_failed')
                                    <span class="sub" style="color:var(--bad)">Pengiriman email gagal</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $request->role === 'teller' ? 'badge-ok' : 'badge-soft' }}">{{ $request->role === 'teller' ? 'Teller' : 'Supervisor' }}</span></td>
                            <td>
                                @if($request->role === 'teller')
                                    <span class="name">NIS {{ $request->nis ?: '-' }} &middot; Kelas {{ $request->kelas ?: '-' }}</span>
                                @else
                                    <span class="name">NIP {{ $request->nip ?: '-' }}</span>
                                    <span class="sub">{{ $request->alamat ?: 'Alamat belum diisi' }}</span>
                                @endif
                            </td>
                            <td class="nw muted">{{ $request->email }}</td>
                            <td class="nw muted">{{ \Carbon\Carbon::parse($request->created_at)->format('d/m/Y H:i') }}</td>
                            <td>
                                <div class="actions end">
                                    <form method="POST" action="{{ route('admin.pemulihan-sandi.approve', $request->id) }}" onsubmit="return confirm('{{ $request->status === 'email_failed' ? 'Kirim ulang tautan reset ke email ini?' : 'Setujui permintaan dan kirim tautan reset ke email ini?' }}')">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <x-icon name="send" />
                                            {{ $request->status === 'email_failed' ? 'Kirim ulang' : 'Setujui &amp; kirim' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-cell">Belum ada permintaan. Pastikan email dan data identitas Teller/Supervisor sudah terisi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
