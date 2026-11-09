@extends('layouts.teller')

@section('title', 'Riwayat & Koreksi')
@section('page-title', 'Riwayat & Koreksi')
@section('page-subtitle', 'Lihat transaksi Anda dan ajukan koreksi bila ada kesalahan input. Koreksi disetujui Supervisor.')

@section('content')
<section class="card">
    <div class="section-head">
        <div><h2 class="section-title">Riwayat Transaksi Saya</h2><p class="section-sub">{{ \Carbon\Carbon::parse($tanggal)->locale('id')->translatedFormat('l, d F Y') }} &middot; {{ $riwayat->total() }} transaksi</p></div>
    </div>
    <form method="GET" action="{{ route('teller.koreksi') }}" class="toolbar" data-auto-filter style="border-bottom:1px solid var(--line-2)">
        <input type="hidden" name="kstatus" value="{{ request('kstatus') }}">
        <div class="field"><label for="tTanggal">Tanggal</label><input id="tTanggal" type="date" name="tanggal" value="{{ $tanggal }}"></div>
        <div class="field"><label for="tType">Jenis</label>
            <select id="tType" name="type">
                <option value="">Semua</option>
                <option value="deposit" @selected(request('type') === 'deposit')>Setoran</option>
                <option value="withdrawal" @selected(request('type') === 'withdrawal')>Penarikan</option>
            </select>
        </div>
        <div class="field" style="grid-column:span 2"><label for="tQ">Cari</label><input id="tQ" type="search" name="q" value="{{ request('q') }}" placeholder="Nama nasabah, no. rekening, atau kode transaksi"></div>
        <div class="field"><a href="{{ route('teller.koreksi') }}" class="btn btn-soft btn-sm">Hari ini</a></div>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Kode</th><th>Nasabah</th><th>Jenis</th><th class="num">Nominal</th><th>Status</th><th class="num">Aksi</th></tr></thead>
            <tbody>
            @forelse($riwayat as $t)
                <tr>
                    <td class="nw">{{ $t->created_at->format('H:i') }}</td>
                    <td class="nw">{{ $t->transaction_code }}</td>
                    <td><div class="name">{{ $t->account->user->name ?? '-' }}</div><div class="sub">{{ $t->account->account_number ?? '-' }}</div></td>
                    <td><span class="badge {{ $t->type === 'deposit' ? 'badge-ok' : 'badge-warn' }}">{{ $t->type === 'deposit' ? 'Setoran' : 'Penarikan' }}</span></td>
                    <td class="amount num">Rp {{ number_format($t->amount, 0, ',', '.') }}</td>
                    <td><span class="badge {{ $t->status === 'pending' ? 'badge-warn' : ($t->status === 'rejected' ? 'badge-danger' : 'badge-ok') }}">{{ ['pending' => 'Menunggu (lama)', 'approved' => 'Berhasil', 'rejected' => 'Ditolak', 'success' => 'Koreksi'][$t->status] ?? ucfirst($t->status) }}</span></td>
                    <td class="num">
                        <div class="actions end">
                            <a href="{{ route('teller.transaksi.cetak', $t->id) }}" class="btn btn-soft btn-sm">Slip</a>
                            @if(!$laporanTerkirim && in_array($t->status, ['approved', 'success'], true))
                                <a href="{{ route('teller.koreksi', ['tx' => $t->id]) }}#form-koreksi" class="btn btn-danger btn-sm">Koreksi</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-cell">Tidak ada transaksi pada tanggal dan filter ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($riwayat->hasPages())<x-pager :paginator="$riwayat" />@endif
</section>

<div class="section split-even">
    <section class="card" id="form-koreksi">
        <div class="section-head"><div><h2 class="section-title">Ajukan Koreksi</h2><p class="section-sub">Saldo nasabah baru berubah setelah Supervisor menyetujui.</p></div></div>
        @if($laporanTerkirim)
            <p class="sub" style="padding:16px 24px">Pengajuan koreksi ditutup karena laporan harian tanggal ini sudah pernah dikirim.</p>
        @else
        <form method="POST" action="{{ route('teller.koreksi.store') }}" class="card-body stack">
            @csrf
            <div class="field">
                <label for="cTx">Transaksi yang dikoreksi</label>
                <select id="cTx" name="transaction_id" required>
                    <option value="">Pilih transaksi pada tanggal riwayat</option>
                    @foreach($pilihan as $trx)
                        <option value="{{ $trx->id }}" @selected((string) $selectedTx === (string) $trx->id)>{{ $trx->created_at->format('H:i') }} &middot; {{ $trx->transaction_code }} &middot; {{ $trx->account->user->name ?? '-' }} (Rp {{ number_format($trx->amount, 0, ',', '.') }})</option>
                    @endforeach
                </select>
            </div>
            <div class="cols-2">
                <div class="field"><label for="cJenis">Sifat koreksi</label>
                    <select id="cJenis" name="jenis_koreksi" required>
                        <option value="kurang">Kurangi saldo nasabah</option>
                        <option value="tambah">Tambahkan saldo nasabah</option>
                    </select>
                </div>
                <div class="field"><label for="cAmount">Nominal selisih (Rp)</label><input id="cAmount" type="number" name="amount" min="1" step="1" placeholder="Misal: 9000" required><span class="helper" id="cAmountHint">Isi selisihnya. Contoh: input Rp 1.000, seharusnya Rp 10.000, selisih Rp 9.000.</span></div>
            </div>
            <div class="field"><label for="cKode">Kode koreksi (SOP)</label>
                <select id="cKode" name="sandi_koreksi" required>
                    <option value="">Pilih kode SOP</option>
                    <option value="K01">K01 - Kelebihan input nominal transaksi</option>
                    <option value="K02">K02 - Kekurangan input nominal transaksi</option>
                    <option value="K03">K03 - Salah pilih rekening nasabah</option>
                    <option value="K99">K99 - Kesalahan teknis lainnya</option>
                </select>
            </div>
            <div class="field"><label for="cReason">Catatan keterangan</label><textarea id="cReason" name="reason" rows="3" maxlength="255" required placeholder="Contoh: Seharusnya 10.000 tetapi terinput 100.000"></textarea></div>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Kirim pengajuan koreksi ini ke Supervisor?')">Ajukan koreksi ke Supervisor</button>
        </form>
        @endif
    </section>

    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Pengajuan Koreksi Saya</h2><p class="section-sub">Status persetujuan Supervisor.</p></div></div>
        <div style="padding:16px 24px 0">
            <div class="seg">
                @foreach([null => 'Semua', 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['kstatus' => $key, 'kpage' => null]) }}" class="{{ (string) request('kstatus') === (string) $key ? 'on' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="list" style="padding-top:8px">
            @forelse($corrections as $cor)
                <div class="list-row" style="align-items:flex-start">
                    <div style="min-width:0">
                        <div class="name">{{ $cor->transaction->account->user->name ?? '-' }} &middot; Rp {{ number_format($cor->corrected_amount, 0, ',', '.') }}</div>
                        <div class="sub">{{ $cor->transaction->transaction_code ?? '-' }} &middot; {{ $cor->type === 'withdrawal_correction' ? 'Kurangi' : 'Tambah' }} &middot; {{ $cor->correction_code }}</div>
                        <div class="sub">{{ $cor->reason }}</div>
                        @if($cor->rejected_reason)<div class="sub" style="color:var(--bad)">Alasan penolakan: {{ $cor->rejected_reason }}</div>@endif
                        @if($cor->status !== 'pending')<div class="sub">Diproses oleh: {{ $cor->supervisor->name ?? '-' }}</div>@endif
                    </div>
                    <span class="badge {{ $cor->status === 'approved' ? 'badge-ok' : ($cor->status === 'rejected' ? 'badge-danger' : 'badge-warn') }}">{{ ['approved' => 'Disetujui', 'rejected' => 'Ditolak', 'pending' => 'Menunggu'][$cor->status] ?? $cor->status }}</span>
                </div>
            @empty
                <p class="sub" style="padding:24px 0;text-align:center">Belum ada pengajuan koreksi.</p>
            @endforelse
        </div>
        @if($corrections->hasPages())<x-pager :paginator="$corrections" />@endif
    </section>
</div>
<script>
const correctionCode = document.getElementById('cKode');
const correctionType = document.getElementById('cJenis');
const correctionAmountHint = document.getElementById('cAmountHint');

function syncCorrectionForm() {
    const code = correctionCode?.value;
    const kurang = correctionType?.querySelector('option[value="kurang"]');
    const tambah = correctionType?.querySelector('option[value="tambah"]');

    if (kurang && tambah) {
        kurang.disabled = code === 'K02';
        tambah.disabled = code === 'K01';
        if (code === 'K01') correctionType.value = 'kurang';
        if (code === 'K02') correctionType.value = 'tambah';
    }

    const amount = document.getElementById('cAmount');
    const isAddition = correctionType?.value === 'tambah';
    correctionAmountHint.textContent = isAddition
        ? 'Isi nominal selisih yang perlu ditambahkan.'
        : 'Pengurangan tidak memiliki minimal, saldo tersisa wajib Rp 10.000.';
    amount.min = '1';
}

correctionCode?.addEventListener('change', syncCorrectionForm);
correctionType?.addEventListener('change', syncCorrectionForm);
syncCorrectionForm();
</script>
@endsection