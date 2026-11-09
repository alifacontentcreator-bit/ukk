@extends('layouts.supervisor')

{{-- HALAMAN INI UNTUK: kotak masuk notifikasi Supervisor (ikon lonceng di kanan atas). --}}
@section('title', 'Notifikasi')
@section('page-title', 'Notifikasi')
@section('page-subtitle', 'Informasi dan pengajuan dari petugas, mis. pengajuan tukar shift.')

@section('content')
<section class="card section">
    <div class="section-head">
        <div>
            <h2 class="section-title">Kotak Masuk</h2>
            <p class="section-sub">{{ $jumlahBelumDibaca }} notifikasi belum dibaca &middot; {{ $notifikasi->total() }} total.</p>
        </div>
        <form method="POST" action="{{ route('supervisor.notifikasi.baca') }}">
            @csrf
            <button class="btn btn-soft btn-sm" type="submit">Tandai semua dibaca</button>
        </form>
    </div>

    <div class="list">
        @forelse($notifikasi as $pesan)
            <div class="list-row">
                <div>
                    <div class="name">{{ $pesan->title }} @if(!$pesan->read_at)<span class="badge badge-danger">Baru</span>@endif</div>
                    <div class="sub" style="white-space:normal">{{ $pesan->message }}</div>
                    <div class="sub">{{ $pesan->created_at?->translatedFormat('l, d F Y H:i') }}</div>
                </div>
                <div class="actions">
                    @if($pesan->type === 'shift_swap_request')
                        <a class="btn btn-primary btn-sm" href="{{ route('supervisor.tukar-shift') }}">Proses</a>
                    @endif
                </div>
            </div>
        @empty
            <p class="sub" style="padding:16px">Belum ada notifikasi.</p>
        @endforelse
    </div>
    @if($notifikasi->hasPages())<x-pager :paginator="$notifikasi" />@endif
</section>
@endsection
