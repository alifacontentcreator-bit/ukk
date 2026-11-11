{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman notifications, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.app')

@section('title', 'Notifikasi Transaksi')
@section('page-title', 'Notifikasi')
@section('page-subtitle', 'Pemberitahuan transaksi dan aktivitas rekening Anda.')

@section('content')
    <section class="card">
        <div class="section-head">
            <div>
                <div class="section-title">Notifikasi Transaksi Nasabah</div>
                <div class="section-sub">{{ $notifications->total() }} pemberitahuan.</div>
            </div>
            <span class="badge badge-outline">{{ $notifications->total() }}</span>
        </div>
        <div class="list" style="padding:0 12px">
            @forelse($notifications as $notification)
                <div class="list-row" style="align-items:flex-start">
                    <div style="min-width:0">
                        <div class="name">{{ $notification->title }}</div>
                        <div class="sub">{{ $notification->message }}</div>
                        <div class="sub" style="color:var(--faint)">{{ $notification->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                    @if(is_null($notification->read_at))
                        <span class="badge badge-ok">Baru</span>
                    @else
                        <span class="badge badge-outline">Dibaca</span>
                    @endif
                </div>
            @empty
                <p class="empty-inline">Belum ada notifikasi.</p>
            @endforelse
        </div>
        <x-pager :paginator="$notifications" />
    </section>
@endsection
