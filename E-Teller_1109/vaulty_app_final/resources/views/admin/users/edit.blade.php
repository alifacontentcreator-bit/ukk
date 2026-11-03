@extends('layouts.admin')

@section('title', 'Ubah Pengguna')

@section('content')
    @php($selectedRole = old('role', $user->role))

    <div class="page-head">
        <div>
            <h1>Ubah Data Pengguna</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><a href="{{ route('admin.users') }}">Manajemen Pengguna</a><i>/</i><b>{{ $user->name }}</b></div>
        </div>
    </div>

    <section class="card" style="max-width:820px">
        <div class="section-head">
            <div>
                <div class="section-title">Formulir Ubah Pengguna</div>
                <div class="section-sub">Perbarui informasi, hak akses, atau sandi pengguna.</div>
            </div>
            <span class="badge {{ $user->status === 'active' ? 'badge-ok' : 'badge-danger' }}">{{ $user->status === 'active' ? 'Aktif' : 'Diblokir' }}</span>
        </div>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="card-body stack">
                <div>
                    <span class="section-label">Identitas pengguna</span>
                    <div class="stack">
                        <div class="field">
                            <label for="inputName">Nama lengkap</label>
                            <input id="inputName" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                            @error('name')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="inputPhoto">Foto profil</label>
                            <div class="person" style="gap:10px">
                                @if($user->photo)
                                    <img class="et-avatar et-avatar-photo" style="width:40px;height:40px" src="{{ asset('storage/' . $user->photo) }}" alt="Foto {{ $user->name }}">
                                @else
                                    <span class="et-avatar" style="width:40px;height:40px;font-size:14px">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                @endif
                                <input id="inputPhoto" type="file" name="photo" accept="image/*" style="flex:1">
                            </div>
                            <p class="helper">Format gambar umum, maksimal 5 MB. Kosongkan jika tidak ingin mengganti foto.</p>
                            @error('photo')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="inputUsername">Nama pengguna</label>
                                <input id="inputUsername" type="text" name="username" value="{{ old('username', $user->username) }}" required>
                                @error('username')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="field">
                                <label for="inputPassword">Sandi baru (opsional)</label>
                                <div class="field-icon">
                                    <input id="inputPassword" type="password" name="password" placeholder="Kosongkan jika tidak diubah" autocomplete="new-password">
                                    <button class="toggle-pass" type="button" data-toggle-pass="inputPassword" aria-label="Tampilkan sandi" aria-pressed="false">
                                        <x-icon name="eye" class="ico-show" />
                                        <x-icon name="eye-off" class="ico-off" />
                                    </button>
                                </div>
                                @error('password')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <span class="section-label">Peran dan status</span>
                    <div class="form-grid">
                        <div class="field">
                            <label for="roleSelect">Peran / hak akses</label>
                            <select id="roleSelect" name="role" required onchange="toggleCustomerFields()">
                                <option value="customer" @selected($selectedRole == 'customer')>Nasabah</option>
                                <option value="teller" @selected($selectedRole == 'teller')>Teller</option>
                                <option value="supervisor" @selected($selectedRole == 'supervisor')>Supervisor</option>
                                <option value="admin" @selected($selectedRole == 'admin')>Admin</option>
                            </select>
                            @error('role')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="statusSelect">Status akun</label>
                            <select id="statusSelect" name="status" required>
                                <option value="active" @selected(old('status', $user->status) == 'active')>Aktif</option>
                                <option value="blocked" @selected(old('status', $user->status) == 'blocked')>Diblokir</option>
                            </select>
                            @error('status')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="userEmail">Email terdaftar / pemulihan</label>
                            <input id="userEmail" type="email" name="email" value="{{ old('email', $user->email) }}">
                            <p id="emailHint" class="helper">Email dipakai untuk kode aktivasi nasabah atau pemulihan akun pegawai.</p>
                            @error('email')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="userPhone">No. telepon / WhatsApp</label>
                            <input id="userPhone" type="text" name="no_telp" value="{{ old('no_telp', $user->no_telp) }}" maxlength="20" placeholder="08xxxxxxxxxx">
                            @error('no_telp')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div id="supervisorNipField" class="field" @if($selectedRole !== 'supervisor') hidden @endif>
                    <label for="supervisorNip">NIP Supervisor</label>
                    <input id="supervisorNip" type="text" name="nip" value="{{ old('nip', $user->nip) }}" maxlength="30">
                    @error('nip')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div id="supervisorAddressField" class="field" @if($selectedRole !== 'supervisor') hidden @endif>
                    <label for="supervisorAddress">Alamat Supervisor</label>
                    <input id="supervisorAddress" type="text" name="alamat" value="{{ old('alamat', $user->alamat) }}" maxlength="255">
                    @error('alamat')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div id="tellerRecoveryFields" class="form-grid" @if($selectedRole !== 'teller') hidden @endif>
                    <div class="field">
                        <label for="tellerNis">NIS Teller</label>
                        <input id="tellerNis" type="text" name="nis" value="{{ old('nis', $user->nis) }}" maxlength="5" inputmode="numeric" pattern="[0-9]{5}" placeholder="5 angka">
                        @error('nis')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="tellerClass">Kelas Teller</label>
                        <input id="tellerClass" type="text" name="kelas" value="{{ old('kelas', $user->kelas) }}" maxlength="30">
                        @error('kelas')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div id="customerFields" class="stack" @if($selectedRole !== 'customer') hidden @endif>
                    <span class="section-label">Informasi akademik nasabah</span>
                    <div class="form-grid">
                        <div class="field">
                            <label for="customerNis">NIS</label>
                            <input id="customerNis" type="text" name="nis" value="{{ old('nis', $user->nis) }}" maxlength="5" inputmode="numeric" pattern="[0-9]{5}" placeholder="5 angka">
                            @error('nis')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="customerKelas">Kelas</label>
                            <input id="customerKelas" type="text" name="kelas" value="{{ old('kelas', $user->kelas) }}">
                        </div>
                        <div class="field">
                            <label for="customerJurusan">Jurusan</label>
                            <input id="customerJurusan" type="text" name="jurusan" value="{{ old('jurusan', $user->jurusan) }}">
                        </div>
                    </div>
                </div>

            </div>
            <div class="form-actions" style="padding:0 12px 12px;margin:0;border-top:0">
                <a class="btn btn-soft btn-sm" href="{{ route('admin.users') }}">Batal</a>
                <button class="btn btn-primary btn-sm" type="submit"><x-icon name="save" /> Simpan Perubahan</button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script>
        function toggleCustomerFields() {
            const role = document.getElementById('roleSelect');
            if (!role) return;

            const value = role.value;
            const customerFields = document.getElementById('customerFields');
            const supervisorNipField = document.getElementById('supervisorNipField');
            const supervisorNip = document.getElementById('supervisorNip');
            const supervisorAddressField = document.getElementById('supervisorAddressField');
            const supervisorAddress = document.getElementById('supervisorAddress');
            const tellerFields = document.getElementById('tellerRecoveryFields');
            const tellerNis = document.getElementById('tellerNis');
            const tellerClass = document.getElementById('tellerClass');
            const email = document.getElementById('userEmail');
            const emailHint = document.getElementById('emailHint');

            const needsEmail = ['customer', 'teller', 'supervisor'].includes(value);
            const isTeller = value === 'teller';
            const isSupervisor = value === 'supervisor';

            supervisorNipField.hidden = !isSupervisor;
            supervisorAddressField.hidden = !isSupervisor;
            supervisorNip.required = isSupervisor;
            supervisorNip.disabled = !isSupervisor;
            supervisorAddress.required = isSupervisor;
            supervisorAddress.disabled = !isSupervisor;

            tellerFields.hidden = !isTeller;
            tellerNis.required = isTeller;
            tellerNis.disabled = !isTeller;
            tellerClass.required = isTeller;
            tellerClass.disabled = !isTeller;

            customerFields.hidden = value !== 'customer';
            customerFields.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = value !== 'customer';
            });

            email.required = needsEmail;
            if (emailHint) {
                emailHint.textContent = value === 'customer'
                    ? 'Kode aktivasi nasabah akan dikirim ke alamat email terdaftar ini.'
                    : 'Email wajib untuk pemulihan akun Teller dan Supervisor.';
            }
        }

        document.addEventListener('DOMContentLoaded', toggleCustomerFields);
    </script>
@endpush
