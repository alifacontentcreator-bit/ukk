@extends('layouts.teller')

@section('title', 'Notifikasi Teller')
@section('page-title', 'Notifikasi Teller')
@section('page-subtitle', 'Keputusan Supervisor atas laporan harian dan pengajuan koreksi.')

@section('content')
    <section class="card">
        <div class="section-head">
            <div>
                <div class="section-title">Pemberitahuan</div>
                <div class="section-sub">{{ $notifications->total() }} pemberitahuan.</div>
            </div>
            <span class="badge badge-outline">{{ $notifications->total() }}</span>
        </div>

        <div class="list" style="padding:0 12px">
            @forelse($notifications as $notification)
                @php
                    $report = $notification->dailyReport;
                    $ditolak = in_array($notification->type, ['report_rejected', 'correction_rejected'], true);
                    $penolakanLaporan = $notification->type === 'report_rejected';
                    $perluRevisi = $penolakanLaporan && $report && $report->status === 'rejected';
                    $baru = $unreadIds->contains($notification->id);
                @endphp

                <div class="list-row" style="align-items:flex-start;gap:12px">
                    <div style="min-width:0">
                        <div class="name" style="display:flex;align-items:center;gap:6px">
                            <x-icon name="{{ $ditolak ? 'alert' : 'check' }}"
                                    style="width:13px;height:13px;color:{{ $ditolak ? 'var(--bad)' : 'var(--ok)' }}" />
                            <span>{{ $notification->title }}</span>
                        </div>

                        <div class="sub">{{ $notification->message }}</div>

                        <div class="sub" style="color:var(--faint)">
                            {{ $notification->created_at->format('d/m/Y H:i') }}
                            @if($report)
                                &middot; laporan tanggal
                                {{ \Carbon\Carbon::parse($report->report_date)->locale('id')->translatedFormat('d F Y') }}
                            @elseif(str_starts_with($notification->type, 'correction_'))
                                &middot; koreksi transaksi
                            @endif
                        </div>

                        @if($perluRevisi)
                            <form method="POST"
                                  action="{{ route('teller.laporan-harian.revisi', $report->id) }}"
                                  style="margin-top:8px"
                                  onsubmit="return confirm('Buka laporan ini untuk direvisi? Hitung ulang uang fisik kas akhir lalu kirim ulang ke Supervisor.')">
                                @csrf
                                <button class="btn btn-primary btn-sm" type="submit">
                                    <x-icon name="refresh" /> Revisi Laporan Harian
                                </button>
                            </form>
                        @elseif($penolakanLaporan && $report)
                            <span class="badge badge-outline" style="margin-top:8px">Laporan sudah direvisi / dikirim ulang</span>
                        @elseif($notification->type === 'correction_approved')
                            <span class="badge badge-ok" style="margin-top:8px">Koreksi disetujui dan saldo sudah disesuaikan</span>
                        @elseif($notification->type === 'correction_rejected')
                            <span class="badge badge-danger" style="margin-top:8px">Koreksi ditolak</span>
                        @elseif(!$ditolak && $report)
                            <span class="badge badge-ok" style="margin-top:8px">Laporan sudah disetujui</span>
                        @endif
                    </div>

                    @if($baru)
                        <span class="badge badge-ok">Baru</span>
                    @else
                        <span class="badge badge-outline">Dibaca</span>
                    @endif
                </div>
            @empty
                <p class="empty-inline">Belum ada notifikasi dari Supervisor.</p>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="section" style="padding:0 12px 12px">
                <x-pager :paginator="$notifications" />
            </div>
        @endif
    </section>
@endsection
