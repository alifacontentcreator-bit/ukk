@extends('layouts.auth')

@section('title', 'Lupa sandi')
@section('head-icon', 'key')
@section('heading', 'Lupa sandi?')
@section('subheading', 'Pemulihan tersedia untuk akun Teller dan Supervisor.')
@section('desc-class', 'auth-desc--optional')

@if(isset($recovery) && in_array($recovery?->status, ['pending', 'sending'], true))
    @push('styles')
        <meta http-equiv="refresh" content="15">
    @endpush
@endif

@section('content')
    @if(session('recovery_submitted'))
        <div class="auth-alert is-success" role="status">
            <x-icon name="check" /><span>{{ session('recovery_submitted') }}</span>
        </div>
    @endif

    @if(isset($recovery) && $recovery && in_array($recovery->status, ['pending', 'sending'], true))
        <div class="auth-alert is-warn" role="status">
            <x-icon name="clock" />
            <span>Permintaan untuk <b>{{ $recovery->name }}</b> sedang menunggu persetujuan Administrator. Setelah disetujui, tautan membuat sandi baru dikirim ke email terdaftar.</span>
        </div>
        <p class="auth-info">
            <x-icon name="info" />
            <span>Halaman ini memeriksa status persetujuan setiap 15 detik.</span>
        </p>
    @elseif(isset($recovery) && $recovery && $recovery->status === 'email_failed')
        <div class="auth-alert is-error" role="alert">
            <x-icon name="alert" />
            <span>Email tautan reset belum terkirim dan sandi Anda belum berubah. Hubungi Administrator untuk mengirim ulang tautan.</span>
        </div>
    @elseif(isset($recovery) && $recovery && $recovery->status === 'link_sent')
        <div class="auth-alert is-success" role="status">
            <x-icon name="mail" />
            <span>Tautan membuat sandi baru sudah dikirim ke <b>{{ $recovery->email }}</b>. Periksa kotak masuk (Inbox) email tersebut. Bila belum terlihat, tunggu 1&ndash;2 menit lalu cek folder Promosi/Spam dan tandai &ldquo;Bukan spam&rdquo;. Tautan berlaku 30 menit dan hanya dapat dipakai sekali.</span>
        </div>
    @elseif(isset($recovery) && $recovery && $recovery->status === 'completed')
        <div class="auth-alert is-success" role="status">
            <x-icon name="check" />
            <span>Sandi terbaru sudah disimpan. Silakan masuk menggunakan sandi baru Anda.</span>
        </div>
        <a class="auth-submit" href="{{ route('login') }}">Ke halaman masuk</a>
    @else
        {{-- Alur 3 langkah: dekoratif; disembunyikan pada tinggi < 700px agar tidak ada gulir. --}}
        <ol class="auth-steps" aria-hidden="true">
            <li class="is-active"><span>1</span>Isi data</li>
            <li><span>2</span>Admin memeriksa</li>
            <li><span>3</span>Tautan dikirim ke email</li>
        </ol>

        <form class="auth-form" action="{{ route('password.recovery.request') }}" method="POST">
            @csrf
            <div class="field">
                <label for="role-teller" id="roleLabel">Jenis akun</label>
                <div class="seg" role="radiogroup" aria-labelledby="roleLabel">
                    <label class="seg-opt" for="role-teller">
                        <input id="role-teller" name="role" type="radio" value="teller"
                               @checked(old('role', 'teller') === 'teller') required>
                        <span>Teller</span>
                    </label>
                    <label class="seg-opt" for="role-supervisor">
                        <input id="role-supervisor" name="role" type="radio" value="supervisor"
                               @checked(old('role') === 'supervisor')>
                        <span>Supervisor</span>
                    </label>
                </div>
                @error('role')<p class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="name">Nama lengkap</label>
                <div class="field-lead">
                    <x-icon name="user" />
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required>
                </div>
                @error('name')<p class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="form-grid" id="tellerIdentity">
                <div class="field">
                    <label for="nis">NIS</label>
                    <div class="field-lead">
                        <x-icon name="id-card" />
                        <input id="nis" name="nis" type="text" value="{{ old('nis') }}">
                    </div>
                    @error('nis')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="kelas">Kelas</label>
                    <div class="field-lead">
                        <x-icon name="book" />
                        <input id="kelas" name="kelas" type="text" value="{{ old('kelas') }}" placeholder="Contoh: XII RPL 1">
                    </div>
                    @error('kelas')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="form-grid" id="supervisorIdentity" hidden>
                <div class="field">
                    <label for="nip">NIP</label>
                    <div class="field-lead">
                        <x-icon name="id-card" />
                        <input id="nip" name="nip" type="text" value="{{ old('nip') }}">
                    </div>
                    @error('nip')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="alamat">Alamat terdaftar</label>
                    <div class="field-lead">
                        <x-icon name="map-pin" />
                        <input id="alamat" name="alamat" type="text" value="{{ old('alamat') }}">
                    </div>
                    @error('alamat')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="field">
                <label for="email">Email terdaftar</label>
                <div class="field-lead">
                    <x-icon name="mail" />
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required placeholder="nama@sekolah.sch.id">
                </div>
                @error('email')<p class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="auth-info">
                <x-icon name="info" />
                <span>Data harus sama dengan yang tercatat di sistem. Administrator memeriksa permintaan sebelum tautan sandi baru dikirim ke email.</span>
            </div>

            <button class="auth-submit" type="submit">
                <span>Ajukan pemulihan sandi</span>
                <x-icon name="arrow" />
            </button>
        </form>
    @endif

    <div class="auth-back">
        <a class="auth-link" href="{{ route('login') }}">
            <x-icon name="arrow-left" />Kembali ke halaman masuk
        </a>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tellerFields = document.getElementById('tellerIdentity');
            const supervisorFields = document.getElementById('supervisorIdentity');
            const radios = document.querySelectorAll('input[name="role"]');
            if (!tellerFields || !supervisorFields || !radios.length) return;

            const tellerInputs = [document.getElementById('nis'), document.getElementById('kelas')];
            const supervisorInputs = [document.getElementById('nip'), document.getElementById('alamat')];

            function updateIdentityFields() {
                const checked = document.querySelector('input[name="role"]:checked');
                const isTeller = (checked ? checked.value : 'teller') === 'teller';
                tellerFields.hidden = !isTeller;
                supervisorFields.hidden = isTeller;
                tellerInputs.forEach((input) => { if (input) input.required = isTeller; });
                supervisorInputs.forEach((input) => { if (input) input.required = !isTeller; });
            }

            radios.forEach((radio) => radio.addEventListener('change', updateIdentityFields));
            updateIdentityFields();
        });
    </script>
@endpush
