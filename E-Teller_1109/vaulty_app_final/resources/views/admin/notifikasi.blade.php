@extends('layouts.admin')

{{-- HALAMAN INI UNTUK: kotak masuk notifikasi Admin (ikon lonceng di kanan atas). --}}
@section('title', 'Notifikasi')

@section('content')
<div class="page-head">
    <div>
        <h1>Notifikasi</h1>
        <div class="crumb"><span>Administrator</span><i>/</i><b>Notifikasi</b></div>
    </div>
</div>

<section class="card section">
    <div class="section-head">
        <div>
            <h2 class="section-title">Kotak Masuk</h2>
            <p class="section-sub">{{ $jumlahBelumDibaca }} notifikasi belum dibaca &middot; {{ $notifikasi->total() }} total.</p>
        </div>
        <form method="POST" action="{{ route('admin.notifikasi.baca') }}">
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
            </div>
        @empty
            <p class="sub" style="padding:16px">Belum ada notifikasi.</p>
        @endforelse
    </div>
    @if($notifikasi->hasPages())<x-pager :paginator="$notifikasi" />@endif
</section>
@endsection
