{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman create, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.admin')

@section('title', 'Tambah Pengguna')

@section('content')
    <div class="page-head">
        <div>
            <h1>Tambah Pengguna Baru</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><a href="{{ route('admin.users') }}">Manajemen Pengguna</a><i>/</i><b>Tambah</b></div>
        </div>
    </div>

    <section class="card" style="max-width:820px">
        <div class="section-head">
            <div>
                <div class="section-title">Formulir Tambah Pengguna</div>
                <div class="section-sub">Administrator membuat akun Nasabah, Teller, Supervisor, atau Administrator.</div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="card-body stack">
                <div>
                    <span class="section-label">Identitas pengguna</span>
                    <div class="stack">
                        <div class="field">
                            <label for="inputName">Nama lengkap</label>
                            <input id="inputName" type="text" name="name" value="{{ old('name') }}" required placeholder="Contoh: Alifa Putri">
                            @error('name')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="inputEmail">Email terdaftar / pemulihan</label>
                            <input id="inputEmail" type="email" name="email" value="{{ old('email') }}" placeholder="email@sekolah.sch.id">
                            <p id="emailRequiredNote" class="helper" hidden>Email wajib untuk aktivasi nasabah dan pemulihan akun pegawai.</p>
                            @error('email')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="inputPhoto">Foto profil / wajah</label>
                            <input id="inputPhoto" type="file" name="photo" accept="image/*">
                            @error('photo')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div>
                    <span class="section-label">Peran dan akses</span>
                    <div class="field">
                        <label for="roleSelect">Peran / hak akses</label>
                        <select id="roleSelect" name="role" required onchange="toggleFields()">
                            <option value="">Pilih peran...</option>
                            <option value="customer" @selected(old('role') == 'customer')>Nasabah Bank</option>
                            <option value="teller" @selected(old('role') == 'teller')>Teller</option>
                            <option value="supervisor" @selected(old('role') == 'supervisor')>Supervisor</option>
                            <option value="admin" @selected(old('role') == 'admin')>Admin</option>
                        </select>
                        @error('role')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div id="pegawaiFields" class="form-grid">
                    <div class="field">
                        <label for="inputUsername">Nama pengguna untuk masuk</label>
                        <input id="inputUsername" type="text" name="username" value="{{ old('username') }}" autocomplete="off">
                        @error('username')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="inputPassword">Sandi awal</label>
                        <div class="field-icon">
                            <input id="inputPassword" type="password" name="password" autocomplete="new-password">
                            <button class="toggle-pass" type="button" data-toggle-pass="inputPassword" aria-label="Tampilkan sandi" aria-pressed="false">
                                <x-icon name="eye" class="ico-show" />
                                <x-icon name="eye-off" class="ico-off" />
                            </button>
                        </div>
                        @error('password')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div id="supervisorNipField" class="field" hidden>
                    <label for="inputSupervisorNip">NIP Supervisor</label>
                    <input id="inputSupervisorNip" type="text" name="nip" value="{{ old('nip') }}" maxlength="30" placeholder="Masukkan NIP supervisor">
                    @error('nip')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div id="supervisorAddressField" class="field" hidden>
                    <label for="inputSupervisorAddress">Alamat Supervisor</label>
                    <input id="inputSupervisorAddress" type="text" name="alamat" value="{{ old('alamat') }}" maxlength="255" placeholder="Masukkan alamat yang terdaftar">
                    @error('alamat')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div id="tellerRecoveryFields" class="form-grid" hidden>
                    <div class="field">
                        <label for="inputTellerNis">NIS Teller</label>
                        <input id="inputTellerNis" type="text" name="nis" value="{{ old('nis') }}" maxlength="5" inputmode="numeric" pattern="[0-9]{5}" placeholder="NIS 5 angka">
                        @error('nis')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="inputTellerClass">Kelas Teller</label>
                        <input id="inputTellerClass" type="text" name="kelas" value="{{ old('kelas') }}" maxlength="30" placeholder="Contoh: XII RPL 1">
                        @error('kelas')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div id="customerFields" class="stack" hidden>
                    <span class="section-label">Data nasabah</span>
                    <div class="field">
                        <label for="tipeNasabahSelect">Kategori nasabah</label>
                        <select id="tipeNasabahSelect" name="tipe_nasabah" onchange="toggleTipeNasabah()">
                            <option value="siswa">Tabungan Siswa</option>
                            <option value="guru">Tabungan Guru / Karyawan</option>
                        </select>
                    </div>

                    <div id="formSiswa" class="form-grid">
                        <div class="field">
                            <label for="inputNis">NIS (Nomor Induk Siswa)</label>
                            <input id="inputNis" type="text" name="nis" value="{{ old('nis') }}" maxlength="5" inputmode="numeric" pattern="[0-9]{5}" placeholder="5 angka; menjadi nama pengguna">
                            @error('nis')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="inputKodeKelas">Pilih kelas &amp; jurusan</label>
                            <select id="inputKodeKelas" name="kode_kelas">
                                <option value="">-- Pilih Kelas Lengkap --</option>
                                <optgroup label="Jurusan RPL">
                                    <option value="XII RPL1">XII RPL 1</option> <option value="XII RPL2">XII RPL 2</option>
                                    <option value="XI RPL1">XI RPL 1</option>   <option value="XI RPL2">XI RPL 2</option>
                                    <option value="X RPL1">X RPL 1</option>     <option value="X RPL2">X RPL 2</option>
                                </optgroup>
                                <optgroup label="Jurusan TKJ">
                                    <option value="XII TKJ1">XII TKJ 1</option> <option value="XII TKJ2">XII TKJ 2</option>
                                    <option value="XI TKJ1">XI TKJ 1</option>   <option value="XI TKJ2">XI TKJ 2</option>
                                    <option value="X TKJ1">X TKJ 1</option>     <option value="X TKJ2">X TKJ 2</option>
                                </optgroup>
                                <optgroup label="Jurusan DKV">
                                    <option value="XII DKV1">XII DKV 1</option> <option value="XII DKV2">XII DKV 2</option>
                                    <option value="XI DKV1">XI DKV 1</option>   <option value="XI DKV2">XI DKV 2</option>
                                    <option value="X DKV1">X DKV 1</option>     <option value="X DKV2">X DKV 2</option>
                                </optgroup>
                                <optgroup label="Jurusan Akuntansi (AK)">
                                    <option value="XII AK1">XII AK 1</option> <option value="XII AK2">XII AK 2</option>
                                    <option value="XII AK3">XII AK 3</option> <option value="XII AK4">XII AK 4</option>
                                    <option value="XI AK1">XI AK 1</option>   <option value="XI AK2">XI AK 2</option>
                                    <option value="XI AK3">XI AK 3</option>
                                    <option value="X AK1">X AK 1</option>     <option value="X AK2">X AK 2</option>
                                    <option value="X AK3">X AK 3</option>
                                </optgroup>
                                <optgroup label="Jurusan LPS">
                                    <option value="XII LPS">XII LPS</option>
                                    <option value="XI LPS1">XI LPS 1</option> <option value="XI LPS2">XI LPS 2</option>
                                    <option value="X LPS1">X LPS 1</option>   <option value="X LPS2">X LPS 2</option>
                                </optgroup>
                                <optgroup label="Jurusan MP">
                                    <option value="XII MP1">XII MP 1</option> <option value="XII MP2">XII MP 2</option>
                                    <option value="XI MP1">XI MP 1</option>   <option value="XI MP2">XI MP 2</option>
                                    <option value="X MP1">X MP 1</option>     <option value="X MP2">X MP 2</option>
                                </optgroup>
                                <optgroup label="Jurusan BR">
                                    <option value="XII BR1">XII BR 1</option> <option value="XII BR2">XII BR 2</option>
                                    <option value="XI BR1">XI BR 1</option>   <option value="XI BR2">XI BR 2</option>
                                    <option value="X BR1">X BR 1</option>     <option value="X BR2">X BR 2</option>
                                </optgroup>
                                <optgroup label="Jurusan BD">
                                    <option value="XII BD">XII BD</option>
                                    <option value="XI BD">XI BD</option>
                                    <option value="X BD">X BD</option>
                                </optgroup>

                            </select>
                        </div>
                        <div class="callout callout-info" style="grid-column:1/-1">
                            Nomor presensi pada nomor rekening dibuat <b>otomatis</b> oleh sistem (urut pendaftaran di kelas &amp; angkatan yang sama), tidak perlu dipilih manual.
                        </div>
                    </div>

                    <div id="formGuru" class="form-grid" hidden>
                        <div class="field">
                            <label for="inputNip">NIP / NIK (nomor induk)</label>
                            <input id="inputNip" type="text" name="nip" value="{{ old('nip') }}" placeholder="Menjadi nama pengguna">
                            @error('nip')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="inputJabatan">Jabatan pegawai</label>
                            <select id="inputJabatan" name="jabatan">
                                <option value="GRU">Guru Sekolah (GRU)</option>
                                <option value="KAR">Karyawan / Staf (KAR)</option>
                            </select>
                        </div>
                    </div>

                    <div class="callout">
                        <b>Otomatisasi sistem:</b>
                        <ol>
                            <li>Sandi awal otomatis: <b>[Nama Depan]123</b> (contoh: Budi menjadi budi123).</li>
                            <li>Nasabah membuat PIN transaksi sendiri saat pertama kali masuk aplikasi.</li>
                            <li>Format rekening siswa: <b>[JURUSAN]-[ANGKATAN]-[KELAS]-[PRESENSI]</b> (contoh: RPL-2024-01-01).</li>
                            <li>Format rekening guru/karyawan: <b>[GRU/KAR]-[NIP]</b>.</li>
                            <li>Saldo awal mutlak <b>Rp 0</b>.</li>
                        </ol>
                    </div>

                </div>

            </div>
            <div class="form-actions" style="padding:0 12px 12px;margin:0;border-top:0">
                <a class="btn btn-soft btn-sm" href="{{ route('admin.users') }}">Batal</a>
                <button class="btn btn-primary btn-sm" type="submit"><x-icon name="save" /> Simpan Pengguna</button>
            </div>

        </form>
    </section>
@endsection

@push('scripts')
    <script>
        //Fungsi ini membaca role yang dipilih Administrator.
        function toggleFields() {
            const role = document.getElementById('roleSelect');
            if (!role) return;

            const value = role.value;
            const customerFields = document.getElementById('customerFields');
            const pegawaiFields = document.getElementById('pegawaiFields');
            const inUser = document.getElementById('inputUsername');
            const inPass = document.getElementById('inputPassword');
            const supervisorNipField = document.getElementById('supervisorNipField');
            const supervisorNip = document.getElementById('inputSupervisorNip');
            const supervisorAddressField = document.getElementById('supervisorAddressField');
            const supervisorAddress = document.getElementById('inputSupervisorAddress');
            const tellerFields = document.getElementById('tellerRecoveryFields');
            const tellerNis = document.getElementById('inputTellerNis');
            const tellerClass = document.getElementById('inputTellerClass');
            const email = document.getElementById('inputEmail');
            const emailRequiredNote = document.getElementById('emailRequiredNote');
            //Mengecek apakah role yang dipilih termasuk nasabah, Teller, atau Supervisor.

            const needsEmail = ['customer', 'teller', 'supervisor'].includes(value);
            const isSupervisor = value === 'supervisor';
            const isTeller = value === 'teller';
            const isEmployee = value !== '' && value !== 'customer';

            email.required = needsEmail;
            emailRequiredNote.hidden = !needsEmail;

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

            pegawaiFields.hidden = !isEmployee;
            inUser.required = isEmployee;
            inPass.required = isEmployee;

            if (value === 'customer') {
                toggleTipeNasabah();
            } else {
                document.getElementById('inputNis').required = false;
                document.getElementById('inputKodeKelas').required = false;
                document.getElementById('inputNip').required = false;
            }
        }

        function toggleTipeNasabah() {
            const tipe = document.getElementById('tipeNasabahSelect').value;
            const formSiswa = document.getElementById('formSiswa');
            const formGuru = document.getElementById('formGuru');
            const inNis = document.getElementById('inputNis');
            const inKodeKelas = document.getElementById('inputKodeKelas');
            const inNip = document.getElementById('inputNip');
            const siswaFields = formSiswa.querySelectorAll('input, select, textarea');
            const guruFields = formGuru.querySelectorAll('input, select, textarea');

            const isSiswa = tipe === 'siswa';
            formSiswa.hidden = !isSiswa;
            formGuru.hidden = isSiswa;
            siswaFields.forEach((field) => { field.disabled = !isSiswa; });
            guruFields.forEach((field) => { field.disabled = isSiswa; });
            inNis.required = isSiswa;
            inKodeKelas.required = isSiswa;
            inNip.required = !isSiswa;
        }

        document.addEventListener('DOMContentLoaded', toggleFields);
    </script>
@endpush
