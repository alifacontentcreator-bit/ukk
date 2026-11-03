@extends('layouts.teller')

@section('title', 'Transaksi Teller')
@section('page-title', 'Transaksi Kasir (Setor/Tarik)')
@section('page-subtitle', 'Pastikan identitas dan foto siswa sesuai dengan orang yang ada di depan Anda.')

@section('page-actions')
    <a class="btn btn-soft btn-sm" href="{{ route('teller.dashboard') }}"><x-icon name="arrow-left" /> Dasbor</a>
@endsection

@section('content')
    @if(session('customer_transaction_approval_id'))
        <div id="customerApprovalStatus"
             data-status-url="{{ route('teller.transaksi.approval-status', session('customer_transaction_approval_id')) }}"
             class="alert alert-warn" role="status" aria-live="polite">
            <x-icon name="clock" />
            <div>
                <p class="name">Menunggu otorisasi nasabah di aplikasi</p>
                <p class="sub">Jangan serahkan atau terima uang sebelum nasabah menyetujui transaksi di aplikasi.</p>
            </div>
        </div>
    @endif

    @if($account_number && !$nasabah)
        <div class="alert alert-danger">
            <x-icon name="alert" />
            <div>
                <p class="name">Nasabah tidak ditemukan</p>
                <p class="sub">Nomor rekening <b>{{ $account_number }}</b> tidak terdaftar di sistem. Silakan periksa kembali.</p>
            </div>
        </div>
    @endif

    <div class="split-aside">
        <section>
            @if($nasabah)
                <div class="panel">
                    <div class="panel-head"><span>Profil Nasabah</span><x-icon name="id-card" /></div>
                    <div class="panel-body" style="text-align:center;padding:12px">
                        @if($nasabah->user->photo)
                            <img class="et-avatar et-avatar-photo" style="width:72px;height:72px;margin:0 auto;border-radius:50%"
                                 src="{{ asset('storage/' . $nasabah->user->photo) }}" alt="Foto {{ $nasabah->user->name }}">
                        @else
                            <span class="et-avatar" style="width:72px;height:72px;font-size:24px;margin:0 auto">{{ strtoupper(substr($nasabah->user->name ?? 'N', 0, 1)) }}</span>
                        @endif
                        <div class="name" style="margin-top:8px;font-size:13px">{{ $nasabah->user->name }}</div>
                        <div class="sub">{{ $nasabah->user->kelas ?? 'Kelas belum diisi' }}</div>
                    </div>
                    <div class="panel-body" style="border-top:1px solid var(--line-2);padding:8px 12px">
                        <div class="kv"><span class="k">No. rekening</span><span class="v mono">{{ $nasabah->account_number }}</span></div>
                        <div class="kv"><span class="k">Saldo terkini</span><span class="v">Rp {{ number_format($nasabah->balance, 0, ',', '.') }}</span></div>
                        <div class="kv"><span class="k">Jenis tabungan</span><span class="v">{{ $nasabah->product_type === 'tabungan_karyawan' ? 'Guru/Karyawan' : 'Siswa' }}</span></div>
                    </div>
                </div>
            @else
                <div class="card" style="display:grid;place-items:center;text-align:center;padding:20px 14px;border-style:dashed">
                    <span class="empty">
                        <x-icon name="id-card" />
                        <strong>Belum ada nasabah dipilih</strong>
                        <span>Masukkan nomor rekening atau pindai QR untuk melihat profil siswa.</span>
                    </span>
                </div>
            @endif
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <div class="section-title">Formulir Transaksi</div>
                    <div class="section-sub">Transaksi dicatat setelah nasabah menyetujui dengan PIN di aplikasi.</div>
                </div>
                @if($nasabah)
                    <span class="badge badge-ok">Terverifikasi</span>
                @endif
            </div>

            <form method="POST" action="{{ route('teller.transaksi.store') }}">
                @csrf
                <div class="card-body stack">
                    <div class="field">
                        <label for="account_number">Nomor rekening nasabah</label>
                        <input id="account_number" type="text" name="account_number"
                               value="{{ old('account_number', $nasabah->account_number ?? $account_number) }}"
                               class="mono" style="font-size:14px;letter-spacing:.04em"
                               placeholder="Ketik nomor rekening lalu tekan Enter atau Lanjut..."
                               {{ $nasabah ? 'readonly' : 'required' }}>

                        @if(!$nasabah)
                            <div class="actions" style="margin-top:6px">
                                <button type="button" id="lanjutRekening" class="btn btn-primary btn-sm"><x-icon name="arrow" /> Lanjut</button>
                                <button type="button" id="scanQrCamera" class="btn btn-soft btn-sm"><x-icon name="qr" /> Scan QR Data Nasabah</button>
                            </div>
                            <p class="helper">Pilih scanner USB/Bluetooth atau kamera internal pada dialog untuk membaca QR identitas.</p>
                        @endif
                    </div>

                    @if($nasabah)
                        <div class="panel">
                            <div class="panel-head"><span id="slip_title">{{ $type === 'withdrawal' ? 'Slip Penarikan' : 'Slip Setoran' }}</span><x-icon name="receipt" /></div>
                            <div class="panel-body">
                                <div class="kv"><span class="k">Nama pemilik</span><span class="v">{{ $nasabah->user->name }}</span></div>
                                <div class="kv"><span class="k">Kelas</span><span class="v">{{ $nasabah->user->kelas ?? '-' }}</span></div>
                                <div class="kv"><span class="k">Tanggal</span><span class="v">{{ now()->translatedFormat('d F Y') }}</span></div>
                                <div class="kv">
                                    <span class="k" id="untuk_dari_label">Setoran Untuk</span>
                                    <span class="v">{{ $nasabah->product_type === 'tabungan_karyawan' ? 'Tabungan Guru/Karyawan' : 'Tabungan Siswa' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <span class="section-label" style="margin-bottom:0">Jenis transaksi</span>
                            <div class="choice-group">
                                <label class="choice">
                                    <input type="radio" name="type" value="deposit" {{ old('type', $type) === 'deposit' ? 'checked' : '' }}>
                                    <span>Setor tunai</span>
                                </label>
                                <label class="choice">
                                    <input type="radio" name="type" value="withdrawal" {{ old('type', $type) === 'withdrawal' ? 'checked' : '' }}>
                                    <span>Tarik tunai</span>
                                </label>
                            </div>
                        </div>

                        <div class="field">
                            <label for="amount_display">Nominal uang (Rp)</label>
                            <div class="field-prefix">
                                <span>Rp</span>
                                <input type="text" id="amount_display"
                                       value="{{ old('amount') ? number_format(old('amount'), 0, ',', '.') : '' }}"
                                       inputmode="numeric" autocomplete="off"
                                       style="font-size:15px;font-weight:700;letter-spacing:.02em"
                                       placeholder="1.000" required>
                            </div>
                            <input type="hidden" name="amount" id="amount" value="{{ old('amount') }}">
                            <p class="helper">Minimal transaksi Rp 1.000. Sisa saldo di rekening minimal Rp 10.000.</p>
                        </div>

                        <div class="callout">
                            <span class="section-label" style="margin-bottom:2px">Terbilang</span>
                            <p id="terbilang_text" style="font-style:italic;font-weight:600">-</p>
                        </div>

                        <div class="field">
                            <label for="description">Keterangan (opsional)</label>
                            <input id="description" type="text" name="description" value="{{ old('description') }}"
                                   placeholder="Contoh: pembayaran kas / uang saku harian">
                        </div>
                    @endif

                </div>

                <div class="form-actions" style="padding:0 12px 12px;margin:0;border-top:0">
                    <a class="btn btn-soft btn-sm" href="{{ route('teller.transaksi.create') }}">Batal</a>
                    @if($nasabah)
                        <button type="submit" class="btn btn-primary btn-sm"
                                onclick="return confirm('Kirim permintaan ke aplikasi nasabah? Transaksi dan perubahan saldo baru dilakukan setelah nasabah memasukkan PIN.')">
                            <x-icon name="save" /> Simpan Transaksi
                        </button>
                    @endif
                </div>
            </form>
        </section>
    </div>

    <dialog id="qrScannerDialog" class="et-dialog">
        <div class="card-body stack" style="gap:8px">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                <h3 style="margin:0;font-size:13px;font-weight:700">Pindai QR Data Nasabah</h3>
                <button type="button" id="closeQrScanner" class="icon-btn is-plain" aria-label="Tutup pemindai"><x-icon name="x" /></button>
            </div>
            <div class="field">
                <label for="qrDeviceSelect">Perangkat pemindai</label>
                <select id="qrDeviceSelect">
                    <option value="hid">Scanner USB</option>
                    <option value="bluetooth">Scanner Bluetooth</option>
                    <option value="camera">Kamera internal / USB</option>
                </select>
            </div>
            <div id="qrScannerInputWrap" class="field">
                <label for="qrScannerInput">Hasil scanner</label>
                <input id="qrScannerInput" type="text" autocomplete="off" spellcheck="false"
                       placeholder="Fokuskan kolom, lalu scan QR dengan scanner">
            </div>
            <div id="qrCameraInputWrap" class="field" hidden>
                <label for="qrCameraSelect">Pilih kamera</label>
                <select id="qrCameraSelect" aria-label="Pilih kamera"></select>
            </div>
            <video id="qrCameraVideo" hidden style="width:100%;border-radius:8px;background:#000;aspect-ratio:16/9;object-fit:cover" autoplay playsinline muted></video>
            <p id="qrScannerMessage" class="helper" role="status" style="margin:0">Scan QR dengan scanner. Hasil scan harus diakhiri Enter.</p>
        </div>
    </dialog>

<!-- ================================================= -->

<!-- SCRIPT NOMOR REKENING -->

<!-- ================================================= -->

<script>
document.addEventListener('DOMContentLoaded', function() {

    // Update judul slip & label "Setoran Untuk / Ditarik Dari" saat jenis transaksi diganti
    const radios = document.querySelectorAll('input[name="type"]');
    const slipTitle = document.getElementById('slip_title');
    const untukDariLabel = document.getElementById('untuk_dari_label');

    radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (!slipTitle || !untukDariLabel) return;

            if (this.value === 'withdrawal') {
                slipTitle.textContent = 'Slip Penarikan';
                untukDariLabel.textContent = 'Ditarik Dari';
            } else {
                slipTitle.textContent = 'Slip Setoran';
                untukDariLabel.textContent = 'Setoran Untuk';
            }
        });
    });

});

document.addEventListener('DOMContentLoaded', function() {

    const accInput = document.querySelector('input[name="account_number"]');
    const approvalStatus = document.getElementById('customerApprovalStatus');

    if (approvalStatus) {
        const statusUrl = approvalStatus.dataset.statusUrl;
        const pollApproval = async () => {
            try {
                const response = await fetch(statusUrl, {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error('Status permintaan tidak dapat diperiksa.');
                const result = await response.json();

                if (result.status === 'approved') {
                    approvalStatus.className = 'alert alert-success';
                    approvalStatus.replaceChildren();
                    const title = document.createElement('p');
                    title.className = 't-bold';
                    title.textContent = 'Transaksi disetujui nasabah dan berhasil diproses.';
                    const reference = document.createElement('p');
                    reference.className = 'helper';
                    reference.textContent = 'Referensi: ' + result.reference_number;
                    const printLink = document.createElement('a');
                    printLink.className = 'more';
                    printLink.style.display = 'inline-block';
                    printLink.style.marginTop = '4px';
                    printLink.href = result.slip_url;
                    printLink.textContent = 'Cetak slip';
                    approvalStatus.append(title, reference, printLink);
                    return true;
                }

                if (result.status !== 'pending') {
                    approvalStatus.className = 'alert alert-danger';
                    approvalStatus.innerHTML = '<p class="t-bold">Permintaan transaksi tidak disetujui atau sudah kedaluwarsa.</p><p class="helper">Tidak ada perubahan saldo. Silakan buat permintaan baru jika diperlukan.</p>';
                    return true;
                }
            } catch (error) {
                console.error(error);
            }

            return false;
        };

        const statusTimer = window.setInterval(async () => {
            if (await pollApproval()) window.clearInterval(statusTimer);
        }, 4000);
    }

    async function lanjutkanRekening() {
        let accountNumber = accInput.value.trim();
        if (!accountNumber) {
            accInput.focus();
            return;
        }

        try {
            if (/^https?:\/\//i.test(accountNumber)) {
                const qrUrl = new URL(accountNumber);
                const tokenMatch = qrUrl.pathname.match(/\/scan-rekening\/([^/]+)\/?$/);
                if (!tokenMatch) throw new Error('QR ini bukan QR identitas rekening.');

                const response = await fetch("{{ route('teller.qr-identitas.resolve', ['token' => '__TOKEN__']) }}".replace('__TOKEN__', encodeURIComponent(tokenMatch[1])), {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error('QR tidak cocok dengan rekening nasabah.');
                accountNumber = (await response.json()).account_number;
            }

            window.location.href = "{{ route('teller.transaksi.create') }}?account_number=" + encodeURIComponent(accountNumber);
        } catch (error) {
            alert(error.message || 'QR tidak dapat dibaca.');
        }
    }

    if (accInput && !accInput.readOnly) {

        // Enter di kolom rekening dan tombol "Lanjut" menjalankan pencarian yang sama.
        accInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                lanjutkanRekening();
            }
        });

        const lanjutButton = document.getElementById('lanjutRekening');
        if (lanjutButton) lanjutButton.addEventListener('click', lanjutkanRekening);

    }

});
</script>

<!-- ================================================= -->

<!-- SCRIPT FORMAT NOMINAL RUPIAH -->

<!-- ================================================= -->

<script>
document.addEventListener('DOMContentLoaded', function() {

    const amountDisplay = document.getElementById('amount_display');
    const amountHidden = document.getElementById('amount');

    if (!amountDisplay || !amountHidden) {
        return;
    }


    // Fungsi untuk memberi titik pada nominal
    function formatRupiah(value) {

        // Hapus semua karakter selain angka
        let angka = value.replace(/\D/g, '');

        // Jika kosong
        if (angka === '') {
            return '';
        }

        // Tambahkan titik setiap 3 digit
        return angka.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }


    // Fungsi angka ke terbilang (Bahasa Indonesia)
    function terbilang(n) {

        n = Math.floor(Math.abs(n));

        if (n === 0) {
            return 'nol';
        }

        const satuan = [
            '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh',
            'sebelas', 'dua belas', 'tiga belas', 'empat belas', 'lima belas', 'enam belas', 'tujuh belas',
            'delapan belas', 'sembilan belas'
        ];

        function convert(num) {

            if (num < 20) {
                return satuan[num];
            }

            if (num < 100) {
                const sisa = num % 10;
                return (satuan[Math.floor(num / 10)] + ' puluh' + (sisa !== 0 ? ' ' + satuan[sisa] : '')).trim();
            }

            if (num < 200) {
                return ('seratus' + (num - 100 !== 0 ? ' ' + convert(num - 100) : '')).trim();
            }

            if (num < 1000) {
                const sisa = num % 100;
                return (satuan[Math.floor(num / 100)] + ' ratus' + (sisa !== 0 ? ' ' + convert(sisa) : '')).trim();
            }

            if (num < 2000) {
                return ('seribu' + (num - 1000 !== 0 ? ' ' + convert(num - 1000) : '')).trim();
            }

            if (num < 1000000) {
                const sisa = num % 1000;
                return (convert(Math.floor(num / 1000)) + ' ribu' + (sisa !== 0 ? ' ' + convert(sisa) : '')).trim();
            }

            if (num < 1000000000) {
                const sisa = num % 1000000;
                return (convert(Math.floor(num / 1000000)) + ' juta' + (sisa !== 0 ? ' ' + convert(sisa) : '')).trim();
            }

            if (num < 1000000000000) {
                const sisa = num % 1000000000;
                return (convert(Math.floor(num / 1000000000)) + ' miliar' + (sisa !== 0 ? ' ' + convert(sisa) : '')).trim();
            }

            return 'angka terlalu besar';
        }

        return convert(n);
    }


    // Update teks terbilang di bawah nominal
    function updateTerbilang() {

        const el = document.getElementById('terbilang_text');

        if (!el) {
            return;
        }

        const angka = parseInt(amountHidden.value || '0', 10);

        if (!angka) {
            el.textContent = '-';
            return;
        }

        const kata = terbilang(angka);
        el.textContent = kata.charAt(0).toUpperCase() + kata.slice(1) + ' rupiah';
    }


    // Saat Teller mengetik
    amountDisplay.addEventListener('input', function() {

        // Ambil angka murni
        let angka = this.value.replace(/\D/g, '');

        // Tampilkan dengan format Rupiah
        this.value = formatRupiah(angka);

        // Simpan angka murni untuk Laravel
        amountHidden.value = angka;

        // Perbarui terbilang
        updateTerbilang();

    });


    // Sebelum form dikirim
    amountDisplay.closest('form').addEventListener('submit', function() {

        // Pastikan Laravel menerima angka tanpa titik
        amountHidden.value = amountDisplay.value.replace(/\D/g, '');

    });


    // Terbilang awal (kalau ada nilai lama / old())
    updateTerbilang();

});
</script>

<!-- ================================================= -->

<!-- SCRIPT KAMERA SCAN QR (tombol Tutup sudah diperbaiki) -->

<!-- ================================================= -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const openButton = document.getElementById('scanQrCamera');
    const dialog = document.getElementById('qrScannerDialog');
    const video = document.getElementById('qrCameraVideo');
    const deviceSelect = document.getElementById('qrDeviceSelect');
    const cameraSelect = document.getElementById('qrCameraSelect');
    const cameraInputWrap = document.getElementById('qrCameraInputWrap');
    const scannerInputWrap = document.getElementById('qrScannerInputWrap');
    const scannerInput = document.getElementById('qrScannerInput');
    const message = document.getElementById('qrScannerMessage');
    const closeButton = document.getElementById('closeQrScanner');
    const accountInput = document.querySelector('input[name="account_number"]');
    const resolveUrl = @json(route('teller.qr-identitas.resolve', ['token' => '__TOKEN__']));
    const lookupUrl = @json(route('teller.transaksi.create'));
    const defaultDevice = @json($qrDevice);
    let qrReader = null;
    let processingQr = false;

    if (!openButton || !dialog) return;

    function stopCamera() {
        try {
            if (qrReader) qrReader.stop();
        } catch (error) {
            console.error('Gagal menghentikan pemindai kamera.', error);
        }
        qrReader = null;
        processingQr = false;
        video.srcObject?.getTracks().forEach(track => track.stop());
        video.srcObject = null;
    }

    function closeDialog() {
        stopCamera();
        if (dialog.open) dialog.close();
    }

    function showDeviceMode() {
        const useCamera = deviceSelect.value === 'camera';
        cameraInputWrap.hidden = !useCamera;
        scannerInputWrap.hidden = useCamera;
        video.hidden = !useCamera;

        if (useCamera) {
            scannerInput.blur();
        } else {
            stopCamera();
            scannerInput.focus();
        }
    }

    async function useQrValue(value) {
        let accountNumber = value.trim();
        if (!accountNumber) return false;

        try {
            const url = new URL(accountNumber);
            const tokenMatch = url.pathname.match(/\/scan-rekening\/([^/]+)\/?$/);
            if (!tokenMatch) throw new Error('QR bukan QR identitas rekening.');

            message.textContent = 'QR terbaca, mencari data nasabah...';
            const response = await fetch(resolveUrl.replace('__TOKEN__', encodeURIComponent(tokenMatch[1])), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('QR tidak cocok dengan rekening nasabah.');
            accountNumber = (await response.json()).account_number;
        } catch (error) {
            if (!(error instanceof TypeError) || /^https?:\/\//i.test(accountNumber)) {
                message.textContent = error.message || 'QR tidak dapat dibaca.';
                return false;
            }
        }

        closeDialog();
        accountInput.value = accountNumber;
        window.location.href = lookupUrl + '?account_number=' + encodeURIComponent(accountNumber);
        return true;
    }

    async function startCamera() {
        stopCamera();
        message.textContent = 'Mengaktifkan kamera...';
        try {
            qrReader = await window.startBankMiniQrReader(video, cameraSelect.value, async rawValue => {
                if (processingQr) return;
                processingQr = true;
                const resolved = await useQrValue(rawValue);
                if (!resolved) processingQr = false;
            }, error => {
                console.error('Pemindai QR gagal membaca kamera.', error);
                message.textContent = 'Kamera aktif, arahkan ulang ke QR identitas nasabah.';
            });
            message.textContent = 'Arahkan kamera ke QR identitas nasabah.';
        } catch (error) {
            stopCamera();
            console.error('Gagal membuka kamera pemindai QR.', error);
            message.textContent = 'Kamera tidak dapat dibuka. Pastikan halaman dibuka melalui HTTPS, izin kamera aktif, dan kamera tidak sedang digunakan aplikasi lain.';
        }
    }

    async function prepareCamera() {
        showDeviceMode();
        if (!navigator.mediaDevices?.getUserMedia || !window.startBankMiniQrReader) {
            message.textContent = 'Kamera tidak didukung atau halaman belum aman. Buka melalui HTTPS dan izinkan akses kamera.';
            return;
        }

        try {
            message.textContent = 'Meminta izin kamera...';
            const permissionStream = await navigator.mediaDevices.getUserMedia({ video: true });
            permissionStream.getTracks().forEach(track => track.stop());
            const cameras = (await navigator.mediaDevices.enumerateDevices()).filter(device => device.kind === 'videoinput');
            const selectedCamera = cameraSelect.value;
            cameraSelect.replaceChildren(...cameras.map((camera, index) => {
                const option = document.createElement('option');
                option.value = camera.deviceId;
                option.textContent = camera.label || `Kamera ${index + 1}`;
                return option;
            }));

            if (cameras.some(camera => camera.deviceId === selectedCamera)) {
                cameraSelect.value = selectedCamera;
            }
            cameraInputWrap.hidden = cameras.length < 2;

            if (!cameras.length) {
                video.hidden = true;
                message.textContent = 'Kamera tidak terdeteksi. Periksa izin kamera atau sambungkan perangkat kamera.';
                return;
            }

            await startCamera();
        } catch (error) {
            stopCamera();
            console.error('Gagal meminta izin atau daftar kamera.', error);
            message.textContent = 'Akses kamera gagal. Izinkan kamera untuk situs ini dan muat ulang halaman.';
        }
    }

    openButton.addEventListener('click', async function () {
        dialog.showModal();
        deviceSelect.value = defaultDevice;
        scannerInput.value = '';
        if (deviceSelect.value === 'camera') {
            await prepareCamera();
        } else {
            showDeviceMode();
            message.textContent = 'Scan QR dengan scanner. Hasil scan harus diakhiri Enter.';
        }
    });

    deviceSelect.addEventListener('change', async function () {
        if (deviceSelect.value === 'camera') {
            await prepareCamera();
        } else {
            showDeviceMode();
            message.textContent = 'Scan QR dengan scanner. Hasil scan harus diakhiri Enter.';
        }
    });

    cameraSelect.addEventListener('change', startCamera);

    scannerInput.addEventListener('keydown', async function (event) {
        if (event.key !== 'Enter' || processingQr) return;
        event.preventDefault();
        processingQr = true;
        const resolved = await useQrValue(scannerInput.value);
        if (!resolved) {
            processingQr = false;
            scannerInput.select();
        }
    });

    closeButton.addEventListener('click', closeDialog);
    dialog.addEventListener('close', stopCamera);
});
</script>
@endsection
