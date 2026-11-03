@extends($layout)

@section('title', 'Profil Saya')
@section('page-title', 'Profil Saya')

@section('content')
@php
    // layouts.admin menyembunyikan page-head bawaan shell, jadi kepala halaman
    // digambar di dalam konten untuk peran Administrator.
    $headInContent = $user->role === 'admin';
    $openEmailPanel = old('_profile_section') === 'email';
    $openPasswordPanel = old('_profile_section') === 'sandi';
    $initial = mb_strtoupper(mb_substr(trim((string) $user->name) !== '' ? $user->name : 'P', 0, 1));
@endphp

@if($headInContent)
    <div class="page-head">
        <div>
            <h1>Profil Saya</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><b>Profil Saya</b></div>
        </div>
    </div>
@endif

<section class="card profile-card">
    {{-- FOTO PROFIL: hanya tampil. Tidak ada unggah/hapus foto di halaman ini. --}}
    <div class="card-body profile-photo-row">
        <div class="profile-photo">
            @if($user->photo)
                <img src="{{ asset('storage/' . $user->photo) }}" alt="Foto profil {{ $user->name }}">
            @else
                {{ $initial }}
            @endif
        </div>
        <div class="profile-photo-info">
            <div class="profile-photo-title">Foto Profil</div>
            <p class="helper">Foto profil hanya dapat diubah oleh Administrator.</p>
            @if($user->role === 'admin')
                <a class="profile-link" href="{{ route('admin.users.edit', $user) }}">
                    <x-icon name="pencil" /> Ubah foto di Manajemen Pengguna
                </a>
            @endif
        </div>
    </div>

    {{-- DATA AKUN: hanya nama lengkap yang bisa diubah (staf). --}}
    <form id="profileAccountForm" class="profile-section" method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="section-label">Data akun</div>
            <div class="profile-grid">
                <div class="profile-field">
                    <label>Nama pengguna</label>
                    <div class="profile-value"><x-icon name="lock" /><span>{{ $user->username ?: '-' }}</span></div>
                </div>

                @if($user->role === 'supervisor')
                    <div class="profile-field">
                        <label>NIP</label>
                        <div class="profile-value"><x-icon name="lock" /><span>{{ $user->nip ?: '-' }}</span></div>
                    </div>
                @elseif(in_array($user->role, ['customer', 'teller'], true))
                    <div class="profile-field">
                        <label>NIS</label>
                        <div class="profile-value"><x-icon name="lock" /><span>{{ $user->nis ?: '-' }}</span></div>
                    </div>
                @endif

                <div class="profile-field">
                    <label>Peran</label>
                    <div class="profile-value"><x-icon name="lock" /><span class="badge badge-soft">{{ $roleLabel }}</span></div>
                </div>

                @if(!empty($user->kelas))
                    <div class="profile-field">
                        <label>Kelas</label>
                        <div class="profile-value"><x-icon name="lock" /><span>{{ $user->kelas }}</span></div>
                    </div>
                @endif

                <div class="profile-field">
                    <label for="profileName">Nama lengkap</label>
                    @if($canEditName)
                        <input id="profileName" type="text" name="name" value="{{ old('name', $user->name) }}" maxlength="100" required autocomplete="name">
                        <p class="helper">Maksimal 100 karakter.</p>
                    @else
                        <div class="profile-value"><x-icon name="lock" /><span>{{ $user->name }}</span></div>
                        <p class="helper">Nama resmi dari sekolah, hanya dapat diubah Administrator.</p>
                    @endif
                </div>
            </div>
        </div>
    </form>

    {{-- EMAIL PEMULIHAN: hanya untuk peran yang memakainya (teller & supervisor). --}}
    @if($usesRecoveryEmail)
        <section class="profile-section">
            <div class="card-body">
                <div class="profile-section-head">
                    <div>
                        <div class="profile-title">Email</div>
                        <p class="helper">Dipakai untuk pemulihan sandi.</p>
                    </div>
                    <button class="btn btn-soft btn-sm" type="button" data-profile-toggle="emailPanel"
                            aria-controls="emailPanel" aria-expanded="{{ $openEmailPanel ? 'true' : 'false' }}">
                        <x-icon name="pencil" /> Edit Email
                    </button>
                </div>

                <div class="profile-field" style="margin-top:8px">
                    <div class="profile-value"><x-icon name="mail" /><span>{{ $user->email ?: 'Belum diisi' }}</span></div>
                </div>

                <div class="profile-panel" id="emailPanel" @if(!$openEmailPanel) hidden @endif>
                    <form method="POST" action="{{ route('profile.email') }}" class="stack">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_profile_section" value="email">
                        <div class="profile-grid">
                            <div class="field">
                                <label for="profileEmail">Email baru</label>
                                <input id="profileEmail" type="email" name="email"
                                       value="{{ $openEmailPanel ? old('email') : $user->email }}"
                                       maxlength="150" required autocomplete="email">
                            </div>
                            <div class="field">
                                <label for="profileEmailPassword">Sandi saat ini</label>
                                <div class="field-icon">
                                    <input id="profileEmailPassword" type="password" name="current_password"
                                           required autocomplete="current-password">
                                    <button class="toggle-pass" type="button" data-toggle-pass="profileEmailPassword"
                                            aria-label="Tampilkan sandi" aria-pressed="false">
                                        <x-icon name="eye" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p class="helper">Wajib mengisi sandi saat ini untuk mengubah email.</p>
                        <div class="profile-panel-actions">
                            <button class="btn btn-soft btn-sm" type="button" data-profile-toggle="emailPanel">Batal</button>
                            <button class="btn btn-primary btn-sm" type="submit"><x-icon name="save" /> Simpan Email</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    @endif

    {{-- SANDI: alur sendiri, tombol simpan sendiri. PIN nasabah tidak disentuh. --}}
    <section class="profile-section">
        <div class="card-body">
            <div class="profile-section-head">
                <div>
                    <div class="profile-title">Sandi</div>
                    <p class="helper">Minimal 8 karakter dan berbeda dari sandi lama.</p>
                </div>
                <button class="btn btn-soft btn-sm" type="button" data-profile-toggle="passwordPanel"
                        aria-controls="passwordPanel" aria-expanded="{{ $openPasswordPanel ? 'true' : 'false' }}">
                    <x-icon name="key" /> Ubah Sandi
                </button>
            </div>

            <div class="profile-panel" id="passwordPanel" @if(!$openPasswordPanel) hidden @endif>
                <form method="POST" action="{{ route('profile.password') }}" class="stack">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_profile_section" value="sandi">
                    <div class="field">
                        <label for="profileCurrentPassword">Sandi saat ini</label>
                        <div class="field-icon">
                            <input id="profileCurrentPassword" type="password" name="current_password"
                                   required autocomplete="current-password">
                            <button class="toggle-pass" type="button" data-toggle-pass="profileCurrentPassword"
                                    aria-label="Tampilkan sandi" aria-pressed="false"><x-icon name="eye" /></button>
                        </div>
                    </div>
                    <div class="profile-grid">
                        <div class="field">
                            <label for="profileNewPassword">Sandi baru</label>
                            <div class="field-icon">
                                <input id="profileNewPassword" type="password" name="password"
                                       minlength="8" required autocomplete="new-password">
                                <button class="toggle-pass" type="button" data-toggle-pass="profileNewPassword"
                                        aria-label="Tampilkan sandi" aria-pressed="false"><x-icon name="eye" /></button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="profileConfirmPassword">Konfirmasi sandi baru</label>
                            <div class="field-icon">
                                <input id="profileConfirmPassword" type="password" name="password_confirmation"
                                       minlength="8" required autocomplete="new-password">
                                <button class="toggle-pass" type="button" data-toggle-pass="profileConfirmPassword"
                                        aria-label="Tampilkan sandi" aria-pressed="false"><x-icon name="eye" /></button>
                            </div>
                        </div>
                    </div>
                    <p class="helper">Setelah sandi berubah, sesi login lain pada akun ini akan diakhiri.</p>
                    <div class="profile-panel-actions">
                        <button class="btn btn-primary btn-sm" type="submit"><x-icon name="save" /> Simpan Sandi</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    {{-- BILAH BAWAH KARTU: Batal + Simpan (khusus perubahan nama). --}}
    <div class="profile-foot">
        <a class="btn btn-soft btn-sm" href="{{ route('profile.show') }}">Batal</a>
        @if($canEditName)
            <button class="btn btn-primary btn-sm" type="submit" form="profileAccountForm">
                <x-icon name="save" /> Simpan
            </button>
        @endif
    </div>
</section>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-profile-toggle]').forEach((btn) => {
            const panel = document.getElementById(btn.dataset.profileToggle || '');
            if (!panel) return;

            btn.addEventListener('click', () => {
                const willOpen = panel.hidden;
                panel.hidden = !willOpen;

                document.querySelectorAll('[data-profile-toggle="' + btn.dataset.profileToggle + '"]')
                    .forEach((other) => other.setAttribute('aria-expanded', willOpen ? 'true' : 'false'));

                if (!willOpen) return;

                const input = panel.querySelector('input');
                if (input) input.focus();
            });
        });
    </script>
@endpush

