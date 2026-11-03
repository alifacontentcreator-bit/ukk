@extends('layouts.teller')

@section('title', 'Reset PIN Nasabah')
@section('page-title', 'Reset PIN Nasabah')
@section('page-subtitle', 'Ajukan reset PIN setelah identitas nasabah diperiksa langsung. PIN baru direset setelah disetujui Supervisor.')

@section('content')
<div class="split-aside">
    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Ajukan Reset PIN</h2><p class="section-sub">Nasabah harus hadir. Periksa kartu identitas dan cocokkan wajahnya.</p></div></div>
        <form method="POST" action="{{ route('teller.reset-pin.store') }}" class="card-body stack">
            @csrf
            <div class="field"><label for="rNo">Nomor rekening nasabah</label><input id="rNo" type="text" name="account_number" value="{{ old('account_number') }}" placeholder="Nomor rekening" required></div>
            <div class="field"><label for="rDoc">Dokumen identitas yang diperiksa</label>
                <select id="rDoc" name="verification_doc" required>
                    <option value="">Pilih dokumen</option>
                    @foreach($dokumen as $key => $label)<option value="{{ $key }}" @selected(old('verification_doc') === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="field"><label for="rReason">Alasan reset PIN</label><textarea id="rReason" name="reason" rows="3" required placeholder="Contoh: Nasabah lupa PIN setelah lama tidak bertransaksi">{{ old('reason') }}</textarea><span class="sub">Minimal 10 karakter.</span></div>
            <label style="display:flex;gap:8px;align-items:flex-start;font-weight:500;font-size:11.5px"><input type="checkbox" name="identity_checked" value="1" style="width:auto;height:auto;margin-top:2px" @checked(old('identity_checked')) required><span>Saya sudah mencocokkan wajah dan identitas nasabah dengan dokumen yang dipilih.</span></label>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Kirim pengajuan reset PIN ini ke Supervisor?')">Ajukan ke Supervisor</button>
        </form>
    </section>

    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Pengajuan Saya</h2><p class="section-sub">Kedaluwarsa otomatis bila tidak diproses dalam 24 jam.</p></div></div>
        <form method="GET" action="{{ route('teller.reset-pin') }}" class="toolbar" data-auto-filter style="border-bottom:1px solid var(--line-2)">
            <div class="field"><label for="pStatus">Status</label>
                <select id="pStatus" name="status">
                    <option value="">Semua</option>
                    <option value="pending" @selected(request('status') === 'pending')>Menunggu</option>
                    <option value="approved" @selected(request('status') === 'approved')>Disetujui</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Ditolak</option>
                    <option value="expired" @selected(request('status') === 'expired')>Kedaluwarsa</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Dibatalkan</option>
                </select>
            </div>
            <div class="field" style="grid-column:span 2"><label for="pQ">Cari nasabah</label><input id="pQ" type="search" name="q" value="{{ request('q') }}" placeholder="Nama atau nomor rekening"></div>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Nasabah</th><th>Status</th><th class="num">Aksi</th></tr></thead>
                <tbody>
                @forelse($requests as $r)
                    <tr>
                        <td class="nw">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                        <td><div class="name">{{ $r->account->user->name ?? '-' }}</div><div class="sub">{{ $r->account->account_number ?? '-' }}</div></td>
                        <td>
                            <span class="badge {{ $r->status === 'approved' ? 'badge-ok' : ($r->status === 'rejected' ? 'badge-danger' : ($r->status === 'pending' && !$r->isExpired() ? 'badge-warn' : 'badge-soft')) }}">{{ $r->statusLabel() }}</span>
                            @if($r->status === 'rejected' && $r->review_note)<div class="sub" style="white-space:normal;max-width:220px">{{ $r->review_note }}</div>@endif
                        </td>
                        <td class="num">
                            @if($r->status === 'pending' && !$r->isExpired())
                                <form method="POST" action="{{ route('teller.reset-pin.cancel', $r->id) }}" onsubmit="return confirm('Batalkan pengajuan ini?')">@csrf<button type="submit" class="btn btn-danger btn-sm">Batalkan</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-cell">Belum ada pengajuan reset PIN.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())<x-pager :paginator="$requests" />@endif
    </section>
</div>
@endsection