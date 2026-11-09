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
                <div class="section-sub">Perbarui identitas, hak akses, dan status pengguna.</div>
            </div>
            <span class="badge {{ $user->status === 'active' && !($user->account?->pin_blocked) ? 'badge-ok' : 'badge-danger' }}">{{ $user->status !== 'active' ? 'Diblokir' : ($user->account?->pin_blocked ? 'Blokir (PIN)' : 'Aktif') }}</span>
        </div>

        @if($user->account?->pin_blocked)
            <div class="card-body" style="padding-bottom:0">
                <div class="alert alert-warn" style="margin:0">
                    Rekening ini <strong>diblokir karena PIN salah 3 kali</strong>.
                    Buka blokir sekaligus reset PIN melalui menu
                    <a href="{{ route('admin.reset-pin') }}">Reset PIN</a> setelah
                    pengajuan nasabah disetujui. Mengubah status login di form ini
                    tidak membuka blokir PIN.
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.users.update', $user) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            {{-- Ditandai oleh JS saat admin memilih foto; dipakai controller untuk
                 memastikan berkas benar-benar sampai ke server. --}}
            <input type="hidden" name="photo_expected" id="photoExpected" value="">
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
                                <img id="photoPreview" class="et-avatar et-avatar-photo"
                                     style="width:40px;height:40px;{{ $user->photo ? '' : 'display:none' }}"
                                     src="{{ $user->photo ? asset('storage/' . $user->photo) : '' }}"
                                     alt="Foto {{ $user->name }}">
                                <span id="photoInitial" class="et-avatar"
                                      style="width:40px;height:40px;font-size:14px;{{ $user->photo ? 'display:none' : '' }}">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                <input id="inputPhoto" type="file" name="photo" accept="image/*" style="flex:1">
                            </div>
                            <p class="helper">Format gambar umum (JPG, PNG, WEBP), maksimal 5 MB. Kosongkan jika tidak ingin mengganti foto.</p>
                            <p class="form-error" id="photoClientError" hidden></p>
                            @error('photo')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="inputUsername">Nama pengguna</label>
                            <input id="inputUsername" type="text" name="username" value="{{ old('username', $user->username) }}" required>
                            @error('username')<p class="form-error">{{ $message }}</p>@enderror
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
                                <option value="blocked" @selected(old('status', $user->status) == 'blocked')>Nonaktif</option>
                            </select>
                            <p class="helper">Status login pengguna. Nasabah yang sudah lulus cukup dinonaktifkan (tidak dihapus) agar riwayat transaksi tetap tersimpan. Blokir karena PIN salah 3x dikelola di menu Reset PIN.</p>
                            @error('status')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="userEmail">Email terdaftar / pemulihan</label>
                            <input id="userEmail" type="email" name="email" value="{{ old('email', $user->email) }}">
                            <p id="emailHint" class="helper">Email dipakai untuk kode aktivasi nasabah atau pemulihan akun pegawai.</p>
                            @error('email')<p class="form-error">{{ $message }}</p>@enderror
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

        /*
         * Pratinjau foto profil.
         * Tanpa pratinjau, avatar di formulir tetap menampilkan foto lama
         * sehingga perubahan tidak terlihat sampai halaman disimpan.
         * Di sini berkas juga diperiksa lebih awal (jenis + ukuran) supaya
         * admin langsung tahu bila fotonya ditolak, bukan setelah submit.
         */
        function setupPhotoPreview() {
            const input = document.getElementById('inputPhoto');
            const preview = document.getElementById('photoPreview');
            const initial = document.getElementById('photoInitial');
            const errorBox = document.getElementById('photoClientError');
            const expected = document.getElementById('photoExpected');
            if (!input || !preview) return;

            const MAX_BYTES = 5 * 1024 * 1024;

            const showError = (message) => {
                if (!errorBox) return;
                errorBox.textContent = message;
                errorBox.hidden = message === '';
            };

            const markExpected = (chosen) => {
                if (expected) expected.value = chosen ? '1' : '';
            };

            input.addEventListener('change', () => {
                const file = input.files && input.files[0];

                if (!file) {
                    showError('');
                    markExpected(false);
                    return;
                }

                if (!file.type || !file.type.startsWith('image/')) {
                    showError('Berkas yang dipilih bukan gambar. Gunakan JPG, PNG, atau WEBP.');
                    input.value = '';
                    markExpected(false);
                    return;
                }

                if (file.size > MAX_BYTES) {
                    showError('Ukuran foto ' + (file.size / 1024 / 1024).toFixed(1) + ' MB melebihi batas 5 MB. Pilih foto yang lebih kecil.');
                    input.value = '';
                    markExpected(false);
                    return;
                }

                showError('');
                markExpected(true);
                preview.src = URL.createObjectURL(file);
                preview.style.display = '';
                if (initial) initial.style.display = 'none';
            });
        }

        document.addEventListener('DOMContentLoaded', setupPhotoPreview);
    </script>
@endpush
