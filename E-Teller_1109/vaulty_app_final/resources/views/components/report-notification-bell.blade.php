@php
    use App\Models\ReportNotification;
    use Illuminate\Support\Facades\Schema;

    $bellUser = auth()->user();

    // Jumlah notifikasi belum dibaca (tabel diperiksa agar aman bila migrasi belum jalan).
    $bellUnread = 0;

    if ($bellUser && Schema::hasTable('report_notifications')) {
        $bellUnread = ReportNotification::unreadCountFor((int) $bellUser->id);
    }

    // Tujuan halaman notifikasi berbeda per peran.
    $bellUrl = match ($bellUser?->role) {
        'teller' => route('teller.notifikasi'),
        'supervisor' => route('supervisor.notifikasi'),
        'admin' => route('admin.notifikasi'),
        default => null,
    };
@endphp

@if($bellUrl)
    <a class="topbar-icon-btn"
       href="{{ $bellUrl }}"
       title="Notifikasi ({{ $bellUnread }} belum dibaca)"
       aria-label="Notifikasi, {{ $bellUnread }} belum dibaca">
        <x-icon name="bell" />
        @if($bellUnread > 0)
            <span class="dot">{{ $bellUnread > 9 ? '9+' : $bellUnread }}</span>
        @endif
    </a>
@endif

