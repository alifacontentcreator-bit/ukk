{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman konfigurasi, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.admin')

@section('title', 'Konfigurasi Sistem')

@section('content')
    <div class="page-head">
        <div>
            <h1>Konfigurasi Sistem</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><b>Konfigurasi Sistem</b></div>
        </div>
    </div>

    <section class="card">
        <div class="section-head">
            <div>
                <div class="section-title">QR Identitas Nasabah</div>
                <div class="section-sub">Satu QR identitas per nasabah untuk mengenali nasabah dan membuka data rekening di loket Teller.</div>
            </div>
            <span class="badge badge-outline">{{ $nasabahList->count() }} nasabah</span>
        </div>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nasabah</th>
                        <th>No. Rekening</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($nasabahList as $rek)
                        <tr>
                            <td>
                                <div class="person">
                                    <span class="et-avatar">{{ strtoupper(substr($rek->user->name ?? 'U', 0, 1)) }}</span>
                                    <span>
                                        <span class="name">{{ $rek->user->name ?? '-' }}</span>
                                        <span class="sub">{{ $rek->user->nis ?? 'Nasabah' }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="nw mono">{{ $rek->account_number }}</td>
                            <td>
                                <div class="actions end">
                                    <a class="icon-btn" href="{{ route('admin.rekening.qr', $rek) }}" title="Lihat / cetak QR {{ $rek->user->name ?? '' }}" aria-label="Lihat atau cetak QR {{ $rek->user->name ?? '' }}"><x-icon name="qr" /></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="empty-cell">Belum ada nasabah/rekening.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <form class="card section" action="{{ route('admin.konfigurasi.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="section-head">
            <div>
                <div class="section-title">Parameter Sistem &amp; Perangkat Pemindai QR</div>
                <div class="section-sub">Batas saldo mengendap, tahun angkatan berjalan, dan pengaturan pemindai QR di loket Teller.</div>
            </div>
            <span class="stat-icon"><x-icon name="qr" /></span>
        </div>

        <div class="card-body">
            <span class="section-label">Parameter sistem</span>
            <div class="form-grid">
                <div class="field">
                    <label for="min_balance">Batas saldo mengendap minimum</label>
                    <div class="field-prefix">
                        <span>Rp</span>
                        <input id="min_balance" type="number" name="min_balance" min="10000" value="{{ $settings['min_balance'] ?? 10000 }}">
                    </div>
                    <p class="helper">Penarikan tunai wajib menyisakan saldo minimal sebesar ini.</p>
                </div>
                <div class="field">
                    <label for="intake_year">Tahun angkatan kelas X (berjalan)</label>
                    <input id="intake_year" type="number" name="intake_year_kelas_x" min="2000" value="{{ $settings['intake_year_kelas_x'] ?? date('Y') }}">
                    <p class="helper">Diperbarui tiap tahun ajaran baru untuk menghitung angkatan pada nomor rekening.</p>
                </div>
            </div>

            <span class="section-label" style="margin-top:14px">Perangkat pemindai QR</span>
            <div class="form-grid">
                <div class="field">
                    <label for="qr_enabled">Status layanan QR</label>
                    <select id="qr_enabled" name="qr_enabled">
                        <option value="1" @selected(($settings['qr_enabled'] ?? '1') == '1')>Aktif</option>
                        <option value="0" @selected(($settings['qr_enabled'] ?? '1') == '0')>Nonaktif</option>
                    </select>
                    <p class="helper">QR hanya untuk mengenali nasabah, bukan untuk meminta atau mengirim uang.</p>
                </div>
                <div class="field">
                    <label for="qr_device">Mode QR nasabah</label>
                    <select id="qr_device" name="qr_device">
                        <option value="hid" @selected(($settings['qr_device'] ?? 'hid') === 'hid')>Scanner USB</option>
                        <option value="bluetooth" @selected(($settings['qr_device'] ?? 'hid') === 'bluetooth')>Scanner Bluetooth</option>
                        <option value="camera" @selected(($settings['qr_device'] ?? 'hid') === 'camera')>Kamera internal / USB</option>
                    </select>
                    <p class="helper">Perangkat yang dipakai membaca QR identitas nasabah di loket.</p>
                </div>
                <div class="field">
                    <label for="qr_mode">Penggunaan QR</label>
                    <select id="qr_mode" name="qr_mode">
                        <option value="scanner_manual" @selected(($settings['qr_mode'] ?? 'scanner_manual') === 'scanner_manual')>QR data nasabah (pindai atau input manual)</option>
                        <option value="scanner_only" @selected(($settings['qr_mode'] ?? 'scanner_manual') === 'scanner_only')>QR data nasabah (hanya pindai)</option>
                        <option value="manual_only" @selected(($settings['qr_mode'] ?? 'scanner_manual') === 'manual_only')>Input manual</option>
                    </select>
                    <p class="helper">Menentukan cara Teller membuka data nasabah.</p>
                </div>
                <div class="switch-row" style="align-self:end">
                    <div>
                        <span class="name">Identitas rekening sebagai cadangan</span>
                        <span class="sub">Jika QR tidak dapat dipindai, nasabah menunjukkan nomor rekening.</span>
                    </div>
                    <div>
                        <input type="hidden" name="qr_manual_fallback" value="0">
                        <input type="checkbox" name="qr_manual_fallback" value="1" aria-label="Identitas rekening sebagai cadangan"
                               @checked(($settings['qr_manual_fallback'] ?? '1') == '1')>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body" style="border-top:1px solid var(--line-2)">
            <span class="section-label">Pengiriman sandi reset (SMTP)</span>
            <p class="helper" style="margin:-2px 0 8px">Masukkan pengaturan SMTP milik alamat pengirim. Sandi SMTP disimpan terenkripsi dan tidak ditampilkan kembali. Jangan memasukkan sandi email Teller atau Supervisor di sini.</p>

            <div class="form-grid">
                <div class="field">
                    <label for="otpSmtpEnabled">Layanan email sandi reset</label>
                    <select id="otpSmtpEnabled" name="otp_smtp_enabled">
                        <option value="0" @selected(($settings['otp_smtp_enabled'] ?? '0') === '0')>Nonaktif</option>
                        <option value="1" @selected(($settings['otp_smtp_enabled'] ?? '0') === '1')>Aktif</option>
                    </select>
                </div>
                <div class="field">
                    <label for="otpSmtpHost">Host SMTP</label>
                    <input id="otpSmtpHost" type="text" name="otp_smtp_host" value="{{ $settings['otp_smtp_host'] ?? '' }}" placeholder="smtp.gmail.com">
                </div>
                <div class="field">
                    <label for="otpSmtpPort">Port SMTP</label>
                    <input id="otpSmtpPort" type="number" name="otp_smtp_port" min="1" max="65535" value="{{ $settings['otp_smtp_port'] ?? '587' }}">
                </div>
                <div class="field">
                    <label for="otpSmtpScheme">Enkripsi sambungan</label>
                    <select id="otpSmtpScheme" name="otp_smtp_scheme">
                        <option value="starttls" @selected(($settings['otp_smtp_scheme'] ?? 'starttls') === 'starttls')>STARTTLS (umumnya port 587)</option>
                        <option value="smtps" @selected(($settings['otp_smtp_scheme'] ?? 'starttls') === 'smtps')>SMTPS (umumnya port 465)</option>
                    </select>
                </div>
                <div class="field">
                    <label for="otpSmtpUsername">Nama pengguna SMTP</label>
                    <input id="otpSmtpUsername" type="text" name="otp_smtp_username" value="{{ $settings['otp_smtp_username'] ?? '' }}" autocomplete="off">
                </div>
                <div class="field">
                    <label for="otpSmtpPassword">Sandi aplikasi SMTP</label>
                    <input id="otpSmtpPassword" type="password" name="otp_smtp_password" value="" autocomplete="new-password"
                           placeholder="{{ !empty($settings['otp_smtp_password_saved']) ? 'Sudah tersimpan; kosongkan agar tidak diubah' : 'Masukkan sandi aplikasi SMTP' }}">
                </div>
                <div class="field">
                    <label for="otpSmtpFromAddress">Alamat email pengirim</label>
                    <input id="otpSmtpFromAddress" type="email" name="otp_smtp_from_address" value="{{ $settings['otp_smtp_from_address'] ?? '' }}" placeholder="nama@sekolah.sch.id">
                </div>
                <div class="field">
                    <label for="otpSmtpFromName">Nama pengirim</label>
                    <input id="otpSmtpFromName" type="text" name="otp_smtp_from_name" value="{{ $settings['otp_smtp_from_name'] ?? config('app.name') . ' SMK Negeri 1 Bantul' }}">
                </div>
            </div>

            <div class="form-grid" style="margin-top:12px;align-items:end">
                <div class="field">
                    <label for="smtpTestEmail">Kirim email uji ke</label>
                    <input id="smtpTestEmail" type="email" name="test_email" value="{{ old('test_email') }}" placeholder="Alamat yang dapat Anda periksa">
                    @error('test_email')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="field" style="justify-content:flex-end">
                    <button class="btn btn-soft btn-sm" type="submit" name="action" value="test_email" style="align-self:flex-start"><x-icon name="mail" /> Kirim Email Uji</button>
                </div>
            </div>
            @error('smtp')<p class="form-error" style="margin-top:6px">{{ $message }}</p>@enderror
            <p class="helper" style="margin-top:8px">Untuk Gmail, gunakan sandi aplikasi Google, bukan sandi login email biasa. Pastikan akun SMTP mengizinkan pengiriman melalui aplikasi.</p>
        </div>

        <div class="form-actions" style="padding:10px 12px;margin:0">
            <button class="btn btn-primary btn-sm" type="submit"><x-icon name="save" /> Simpan Konfigurasi</button>
        </div>

    </form>

    {{-- Ringkasan konfigurasi yang sedang dipakai sistem --}}
    <section class="card section">
        <div class="section-head">
            <div>
                <div class="section-title">Ringkasan Konfigurasi</div>
                <div class="section-sub">Parameter QR yang sedang digunakan sistem.</div>
            </div>
        </div>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Nilai</th>
                        <th class="num">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Layanan QR</td>
                        <td class="muted">{{ ($settings['qr_enabled'] ?? '1') == '1' ? 'Aktif' : 'Nonaktif' }}</td>
                        <td class="num">
                            <span class="badge {{ ($settings['qr_enabled'] ?? '1') == '1' ? 'badge-ok' : 'badge-outline' }}">
                                {{ ($settings['qr_enabled'] ?? '1') == '1' ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>Mode QR nasabah</td>
                        <td class="muted">{{ ['hid' => 'Scanner USB', 'bluetooth' => 'Scanner Bluetooth', 'camera' => 'Kamera internal / USB'][$settings['qr_device'] ?? 'hid'] ?? 'Scanner USB' }}</td>
                        <td class="num"><span class="badge badge-soft">Terpasang</span></td>
                    </tr>
                    <tr>
                        <td>Penggunaan QR</td>
                        <td class="muted">
                            @switch($settings['qr_mode'] ?? 'scanner_manual')
                                @case('scanner_only')
                                    QR data nasabah (hanya pindai)
                                    @break
                                @case('manual_only')
                                    Input manual
                                    @break
                                @default
                                    QR data nasabah (pindai atau input manual)
                            @endswitch
                        </td>
                        <td class="num"><span class="badge badge-ok">Aktif</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection
