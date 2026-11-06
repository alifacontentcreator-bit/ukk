@php
    $bellUser = auth()->user();

    // Notifikasi hanya relevan untuk Teller. Tabel diperiksa lebih dahulu
    // agar halaman tetap tampil walau migrasi belum dijalankan.
    $bellUnread = 0;

    if ($bellUser
        && $bellUser->role === 'teller'
        && \Illuminate\Support\Facades\Schema::hasTable('report_notifications')) {
        $bellUnread = \App\Models\ReportNotification::unreadCountFor((int) $bellUser->id);
    }
@endphp

@if($bellUser && $bellUser->role === 'teller')
    <a class="topbar-icon-btn"
       href="{{ route('teller.notifikasi') }}"
       title="Notifikasi laporan ({{ $bellUnread }} belum dibaca)"
       aria-label="Notifikasi laporan, {{ $bellUnread }} belum dibaca">
        <x-icon name="bell" />
        @if($bellUnread > 0)
            <span class="dot">{{ $bellUnread > 9 ? '9+' : $bellUnread }}</span>
        @endif
    </a>
@endif
