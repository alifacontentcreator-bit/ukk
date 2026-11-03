@extends('layouts.admin')

@section('title', 'Rincian Rekening')

@section('content')
    <div class="page-head">
        <div>
            <h1>Rincian Rekening</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><a href="{{ route('admin.rekening') }}">Master Rekening</a><i>/</i><b>{{ $account->account_number }}</b></div>
        </div>
        <div class="page-actions">
            <a class="btn btn-soft btn-sm" href="{{ route('admin.rekening.qr', $account) }}"><x-icon name="qr" /> Lihat QR</a>
        </div>
    </div>

    <section class="card" style="max-width:720px">
        <div class="section-head">
            <div>
                <div class="section-title">Detail Rekening</div>
                <div class="section-sub">{{ $account->user->name }} &middot; {{ $account->account_number }}</div>
            </div>
        </div>
        <div class="card-body stack">
            <div class="panel">
                <div class="panel-head"><span>Informasi Rekening</span><x-icon name="card" /></div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Nasabah</span><span class="v">{{ $account->user->name }}</span></div>
                    <div class="kv"><span class="k">Nomor rekening</span><span class="v mono">{{ $account->account_number }}</span></div>
                    <div class="kv"><span class="k">Saldo</span><span class="v">Rp {{ number_format($account->balance, 0, ',', '.') }}</span></div>
                    <div class="kv"><span class="k">Status PIN</span><span class="v">{{ $account->pin_is_default ? 'Nasabah perlu membuat PIN' : 'PIN sudah dibuat nasabah' }}</span></div>
                </div>
            </div>

            <div class="field">
                <label for="account_number_view">Nomor Rekening</label>
                <input id="account_number_view" class="mono" value="{{ $account->account_number }}" disabled>
                <p class="helper">Nomor rekening dibentuk otomatis oleh sistem, tidak dapat diubah manual.</p>
            </div>

            <div class="callout callout-warn">
                <b>Reset darurat PIN</b>
                <p style="margin-top:2px">Hanya untuk keadaan darurat; reset normal dilakukan teller dan disetujui supervisor. Tindakan ini tercatat pada log audit dan mewajibkan alasan minimal 10 karakter.</p>
                <form method="POST" action="{{ route('admin.rekening.reset-pin', $account) }}" style="margin-top:8px"
                      onsubmit="var r = prompt('Alasan reset darurat PIN (wajib, minimal 10 karakter):'); if (!(r && r.trim().length > 9)) { alert('Alasan wajib diisi, minimal 10 karakter.'); return false; } this.reason.value = r.trim(); return confirm('Minta nasabah membuat PIN baru melalui aplikasi? Tindakan ini tercatat.');">
                    @csrf
                    <input type="hidden" name="reason" value="">
                    <button type="submit" class="btn btn-danger btn-sm"><x-icon name="key" /> Reset Darurat PIN</button>
                </form>
            </div>
        </div>
        <div class="form-actions" style="padding:0 12px 12px;margin:0;border-top:0">
            <a class="btn btn-soft btn-sm" href="{{ route('admin.rekening') }}"><x-icon name="arrow-left" /> Kembali</a>
        </div>
    </section>
@endsection
