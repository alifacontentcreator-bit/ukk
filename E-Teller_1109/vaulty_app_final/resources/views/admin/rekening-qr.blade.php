@extends('layouts.admin')

@section('title', 'QR Rekening Nasabah')

@section('content')
    <div class="page-head">
        <div>
            <h1>QR Rekening Nasabah</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><a href="{{ route('admin.rekening') }}">Master Rekening</a><i>/</i><b>QR</b></div>
        </div>
        <div class="page-actions">
            <button class="btn btn-primary btn-sm" type="button" onclick="window.print()"><x-icon name="printer" /> Cetak QR</button>
            <a class="btn btn-soft btn-sm" href="{{ route('admin.rekening') }}"><x-icon name="arrow-left" /> Kembali</a>
        </div>
    </div>

    <p class="helper" style="margin:-4px 0 12px">Satu QR identitas untuk setiap nasabah. QR dipindai untuk membuka data rekening tanpa memasukkan nama pengguna dan sandi.</p>

    <section class="card" style="max-width:720px">
        <div class="grid-2" style="padding:14px;align-items:center;gap:16px">
            <div style="border:1px solid var(--line);border-radius:var(--r-card);padding:10px;text-align:center;background:#fff">
                <div data-qr-value="{{ $scanUrl }}"
                     data-qr-label="QR rekening {{ $account->account_number }}"
                     style="width:190px;height:190px;margin:0 auto;display:flex;align-items:center;justify-content:center">
                    <span role="status">Menyiapkan QR...</span>
                </div>
                <p class="helper" style="margin-top:6px">QR identitas nasabah</p>
            </div>
            <div class="stack">
                <div class="panel">
                    <div class="panel-head"><span>Data Nasabah</span><x-icon name="id-card" /></div>
                    <div class="panel-body">
                        <div class="kv"><span class="k">Nasabah</span><span class="v">{{ $account->user->name ?? '-' }}</span></div>
                        <div class="kv"><span class="k">No. rekening</span><span class="v mono">{{ $account->account_number }}</span></div>
                        <div class="kv"><span class="k">NIS</span><span class="v">{{ $account->user->nis ?? '-' }}</span></div>
                        <div class="kv"><span class="k">Status</span><span class="v"><span class="badge badge-ok">Aktif</span></span></div>
                    </div>
                </div>
                <div class="callout callout-info">QR ini unik untuk setiap nasabah dan hanya dipakai mengenali nasabah serta membuka datanya. QR tidak memuat nominal dan tidak dapat dipakai meminta atau mengirim uang.</div>
            </div>
        </div>
    </section>
@endsection
