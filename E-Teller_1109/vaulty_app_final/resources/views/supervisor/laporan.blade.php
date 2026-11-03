
@extends('layouts.supervisor')

@section('title', 'Laporan & Persetujuan')
@section('page-title', 'Laporan & Persetujuan Kas')
@section('page-subtitle', 'Periksa kesesuaian uang fisik di laci Teller dengan catatan transaksi sistem.')

@section('content')

@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
@endphp

{{-- FILTER LAPORAN --}}
<section class="card" style="margin-bottom:20px">
    <form method="GET" action="{{ route('supervisor.laporan') }}"
          class="toolbar" data-auto-filter>

        <div class="field">
            <label for="lFrom">Dari tanggal</label>
            <input id="lFrom" type="date" name="from"
                   value="{{ request('from') }}">
        </div>

        <div class="field">
            <label for="lTo">Sampai tanggal</label>
            <input id="lTo" type="date" name="to"
                   value="{{ request('to') }}">
        </div>

        <div class="field">
            <label for="lTeller">Teller</label>
            <select id="lTeller" name="teller_id">
                <option value="">Semua Teller</option>
                @foreach($tellers as $t)
                    <option value="{{ $t->id }}"
                        @selected((string) request('teller_id') === (string) $t->id)>
                        {{ $t->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="lStatus">Status laporan</label>
            <select id="lStatus" name="status">
                <option value="">Semua status</option>
                <option value="submitted" @selected(request('status') === 'submitted')>
                    Menunggu validasi
                </option>
                <option value="approved" @selected(request('status') === 'approved')>
                    Disetujui
                </option>
                <option value="rejected" @selected(request('status') === 'rejected')>
                    Ditolak
                </option>
            </select>
        </div>

        <div class="field">
            <label for="lKas">Posisi kas</label>
            <select id="lKas" name="kas">
                <option value="">Semua posisi</option>
                <option value="pending" @selected(request('kas') === 'pending')>
                    Masih di laci Teller
                </option>
                <option value="approved" @selected(request('kas') === 'approved')>
                    Sudah di brankas
                </option>
            </select>
        </div>

        <div class="field">
            <a href="{{ route('supervisor.laporan') }}"
               class="btn btn-soft">
                Bersihkan filter
            </a>
        </div>
    </form>

    <div class="section-head"
         style="border-top:1px solid var(--line-2);border-bottom:0">

        <p class="section-sub" style="margin:0">
            {{ $counts['menunggu'] }} laporan menunggu validasi
            &middot;
            {{ $counts['siap_pindah'] }} kas siap dipindahkan ke brankas
        </p>
    </div>
</section>

{{-- DAFTAR LAPORAN --}}
<div class="stack">

    @forelse($reports as $report)

        @php
            /*
             * Total fisik hanya berasal dari seluruh pecahan
             * uang yang dihitung Teller.
             *
             * Saldo awal tidak ditambahkan lagi karena
             * pecahan sudah menghitung seluruh uang di laci.
             */

            $totalFisik =
                ((int) $report->pecahan_100k * 100000) +
                ((int) $report->pecahan_50k * 50000) +
                ((int) $report->pecahan_20k * 20000);

            $totalSistem =
                (float) $report->saldo_awal +
                (float) $report->expected_cash;

            $selisih = $totalFisik - $totalSistem;

            $klop = abs($selisih) < 0.01;
        @endphp

        <article class="card report-card">

            {{-- HEADER --}}
            <div class="report-head">
                <div>
                    <div class="name">
                        Teller: {{ $report->teller->name ?? 'Tidak diketahui' }}
                    </div>

                    <div class="sub">
                        Laporan tanggal
                        {{ \Carbon\Carbon::parse($report->report_date)
                            ->locale('id')
                            ->translatedFormat('d F Y') }}
                    </div>
                </div>

                @if($report->status === 'submitted')
                    <span class="badge badge-warn">
                        Menunggu validasi
                    </span>
                @elseif($report->status === 'approved')
                    <span class="badge badge-ok">
                        Disetujui
                    </span>
                @elseif($report->status === 'rejected')
                    <span class="badge badge-danger">
                        Ditolak
                    </span>
                @endif
            </div>

            {{-- PERBANDINGAN KAS --}}
            <div class="report-compare">

                {{-- SISTEM --}}
                <div class="report-col">
                    <h3>Catatan Sistem</h3>

                    <div class="kv-row">
                        <span>Saldo awal laci</span>
                        <b>{{ $rp($report->saldo_awal) }}</b>
                    </div>

                    <div class="kv-row">
                        <span>Setoran masuk</span>
                        <b class="is-ok">
                            + {{ $rp($report->deposit_total) }}
                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Penarikan keluar</span>
                        <b class="is-bad">
                            - {{ $rp($report->withdrawal_total) }}
                        </b>
                    </div>

                    <div class="kv-row kv-total">
                        <span>Total kas seharusnya</span>
                        <b>{{ $rp($totalSistem) }}</b>
                    </div>
                </div>

                {{-- FISIK --}}
                <div class="report-col">
                    <h3>Uang Fisik di Laci</h3>

                    <div class="kv-row">
                        <span>Uang Rp100.000</span>
                        <b>
                            {{ $report->pecahan_100k }} lembar
                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Nilai pecahan Rp100.000</span>
                        <b>
                            {{ $rp($report->pecahan_100k * 100000) }}
                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Uang Rp50.000</span>
                        <b>
                            {{ $report->pecahan_50k }} lembar
                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Nilai pecahan Rp50.000</span>
                        <b>
                            {{ $rp($report->pecahan_50k * 50000) }}
                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Uang Rp20.000</span>
                        <b>
                            {{ $report->pecahan_20k }} lembar
                        </b>
                    </div>

                    <div class="kv-row">
                        <span>Nilai pecahan Rp20.000</span>
                        <b>
                            {{ $rp($report->pecahan_20k * 20000) }}
                        </b>
                    </div>

                    <div class="kv-row kv-total">
                        <span>Total uang fisik</span>
                        <b>{{ $rp($totalFisik) }}</b>
                    </div>
                </div>

            </div>

            {{-- HASIL REKONSILIASI --}}
            <div class="report-foot">

                <div>
                    @if($klop)
                        <strong class="is-ok">
                            Kas sesuai, tidak ada selisih
                        </strong>
                    @else
                        <strong class="is-bad">
                            Selisih {{ $selisih > 0 ? '+' : '-' }}
                            {{ $rp(abs($selisih)) }}
                        </strong>

                        <small>
                            Laporan belum dapat disetujui karena
                            jumlah kas fisik dan sistem berbeda.
                        </small>
                    @endif
                </div>

                {{-- AKSI --}}
                <div class="actions">

                    @if($report->status === 'submitted')

                        <form
                            action="{{ route('supervisor.laporan.approve', $report->id) }}"
                            method="POST"
                            onsubmit="return confirm('Setujui laporan ini? Pastikan uang fisik sudah dihitung ulang.')">

                            @csrf

                            <button type="submit"
                                    class="btn btn-primary"
                                    @disabled(!$klop)>
                                Setujui laporan
                            </button>
                        </form>

                    @elseif($report->status === 'approved'
                        && $report->transfer_status === 'pending')

                        <form
                            action="{{ route('supervisor.laporan.transfer', $report->id) }}"
                            method="POST"
                            onsubmit="return confirm('Pastikan uang fisik sudah benar-benar diterima Supervisor sebelum mencatat pengembalian kas.')">

                            @csrf

                            <button type="submit" class="btn btn-primary">
                                Pindahkan kas ke brankas
                            </button>
                        </form>

                    @elseif($report->status === 'approved'
                        && $report->transfer_status === 'approved')

                        <span class="badge badge-ok">
                            Kas sudah tercatat di brankas
                        </span>

                    @elseif($report->status === 'rejected'
                        && $report->transfer_status !== 'approved')

                        <div style="width:100%;max-width:380px">

                            <span class="badge badge-danger">
                                Laporan ditolak
                            </span>

                            <form
                                action="{{ route('supervisor.laporan.reopen', $report->id) }}"
                                method="POST"
                                style="margin-top:12px"
                                onsubmit="return confirm('Buka kembali laporan ini?')">

                                @csrf

                                <div class="field">
                                    <label for="reopen_reason_{{ $report->id }}">
                                        Alasan membuka kembali laporan
                                    </label>

                                    <textarea
                                        id="reopen_reason_{{ $report->id }}"
                                        name="reason"
                                        rows="3"
                                        maxlength="500"
                                        required
                                        placeholder="Masukkan alasan pembukaan kembali laporan."></textarea>
                                </div>

                                <button type="submit"
                                        class="btn btn-primary"
                                        style="margin-top:10px">
                                    Buka Kembali Laporan
                                </button>
                            </form>
                        </div>

                    @else

                        <span class="badge badge-danger">
                            Laporan ditolak
                        </span>

                    @endif

                </div>
            </div>

            {{-- RIWAYAT PEMBUKAAN KEMBALI --}}
            @if($report->reopenLogs && $report->reopenLogs->count())

                <div style="border-top:1px solid var(--line-2);padding-top:12px;margin-top:12px">

                    <strong>Riwayat pembukaan kembali</strong>

                    @foreach($report->reopenLogs as $log)
                        <div class="sub" style="margin-top:8px">
                            {{ $log->reason }}
                            &middot;
                            {{ $log->reopened_at
                                ? \Carbon\Carbon::parse($log->reopened_at)
                                    ->format('d-m-Y H:i')
                                : '-' }}
                        </div>
                    @endforeach

                </div>

            @endif

        </article>

    @empty

        <div class="card" style="padding:48px;text-align:center">
            <div class="name">
                Belum ada laporan masuk
            </div>

            <p class="sub" style="margin-top:6px">
                Teller belum mengirim laporan penutupan kas.
            </p>
        </div>

    @endforelse

</div>

{{-- PAGINATION --}}
@if($reports->hasPages())
    <div class="section">
        <x-pager :paginator="$reports" />
    </div>
@endif

@endsection
