{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman user menu, termasuk data dan aksi yang disediakan aplikasi. --}}
@php($menuUser = auth()->user())
<div class="et-user-wrap" data-user-menu>
    <button class="et-user" type="button" data-user-menu-toggle
            aria-haspopup="true" aria-expanded="false" aria-controls="userMenuPanel"
            title="{{ $menuUser->name }}">
        @if($menuUser->photo)
            <img class="et-avatar et-avatar-photo" src="{{ asset('storage/' . $menuUser->photo) }}" alt="Foto {{ $menuUser->name }}">
        @else
            <span class="et-avatar">{{ strtoupper(substr($menuUser->name ?? 'U', 0, 1)) }}</span>
        @endif
        <span class="user-meta">
            <strong>{{ $menuUser->name }}</strong>
            <span>@yield('role')</span>
        </span>
        <x-icon name="chevron-down" class="user-caret" />
    </button>

    <div class="et-user-menu" id="userMenuPanel" role="menu" data-user-menu-panel hidden>
        <a class="et-user-menu-item" role="menuitem" href="{{ route('profile.show') }}">
            <x-icon name="user" /><span>Profil Saya</span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="et-user-menu-item" type="submit" role="menuitem">
                <x-icon name="logout" /><span>Keluar</span>
            </button>
        </form>
    </div>
</div>
