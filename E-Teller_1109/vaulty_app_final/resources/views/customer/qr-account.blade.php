@extends('layouts.app')

@section('title', 'QR Data Nasabah')
@section('page-title', 'QR Data Nasabah')
@section('page-subtitle', 'Tampilkan QR identitas agar data nasabah dapat ditemukan dengan cepat di loket.')

@section('page-actions')
    <button class="btn btn-soft btn-sm no-print" type="button" onclick="window.print()"><x-icon name="printer" /> Cetak</button>
@endsection

@section('content')
    <section class="card" style="max-width:720px">
        <div class="section-head">
            <div>
                <div class="section-title">Identitas Nasabah</div>
                <div class="section-sub">Tunjukkan QR ini kepada Teller untuk membuka data nasabah.</div>
            </div>
            <span class="badge badge-outline">{{ $account->account_number }}</span>
        </div>
        <div class="card-body grid-2" style="align-items:center;gap:16px">
            <div style="border:1px solid var(--line);border-radius:var(--r-card);padding:10px;text-align:center;background:#fff">
                <div data-qr-value="{{ $scanUrl }}"
                     data-qr-label="QR identitas {{ $account->user->name }}"
                     style="width:190px;height:190px;margin:0 auto;display:flex;align-items:center;justify-content:center">
                    <span role="status">Menyiapkan QR...</span>
                </div>
                <p class="helper" style="margin-top:6px">QR identitas nasabah</p>
            </div>
            <div class="stack">
                <div class="panel">
                    <div class="panel-head"><span>Data Nasabah</span><x-icon name="id-card" /></div>
                    <div class="panel-body">
                        <div class="kv"><span class="k">Nama</span><span class="v">{{ $account->user->name }}</span></div>
                        <div class="kv"><span class="k">Nomor rekening</span><span class="v mono">{{ $account->account_number }}</span></div>
                        <div class="kv"><span class="k">Kelas</span><span class="v">{{ $account->user->kelas ?? '-' }}</span></div>
                    </div>
                </div>
                <div class="callout callout-info">QR ini hanya digunakan untuk mengenali nasabah dan membuka data rekening. QR tidak berisi nominal serta tidak dapat digunakan untuk meminta atau mengirim uang.</div>
            </div>
        </div>
        <div class="form-actions" style="padding:0 12px 12px;margin:0;border-top:0">
            <a class="btn btn-soft btn-sm no-print" href="{{ route('customer.dashboard') }}"><x-icon name="arrow-left" /> Kembali ke dasbor</a>
        </div>
    </section>
@endsection
