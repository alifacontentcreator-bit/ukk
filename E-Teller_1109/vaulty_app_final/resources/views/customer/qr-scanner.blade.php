@extends('layouts.app')

@section('title', 'Pindai QR Rekening')
@section('page-title', 'Pindai QR Rekening')
@section('page-subtitle', 'Arahkan kamera ke QR identitas yang dibuat Administrator.')

@section('page-actions')
    <a class="btn btn-soft btn-sm no-print" href="{{ route('customer.dashboard') }}"><x-icon name="arrow-left" /> Kembali</a>
@endsection

@section('content')
    <section class="card" style="max-width:620px">
        <div class="section-head">
            <div>
                <div class="section-title">Pemindai QR</div>
                <div class="section-sub">Kamera hanya aktif selama halaman ini terbuka.</div>
            </div>
        </div>
        <div class="card-body stack">
            <div id="reader" style="width:100%;max-width:480px;margin:0 auto;border-radius:var(--r-card);overflow:hidden;border:1px solid var(--line);background:#000"></div>
            <div id="scan-status" class="alert alert-info" style="margin:0">Meminta akses kamera...</div>
        </div>
    </section>

    <script>
        (async function () {
            const status = document.getElementById('scan-status');
            const reader = document.getElementById('reader');
            const brand = @json(config('app.name'));

            if (!('BarcodeDetector' in window)) {
                status.textContent = 'Peramban ini belum mendukung pemindaian QR otomatis. Gunakan kamera ponsel untuk memindai QR dan membuka tautan ' + brand + '.';
                return;
            }

            try {
                const detector = new BarcodeDetector({ formats: ['qr_code'] });
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } });
                const video = document.createElement('video');
                video.autoplay = true;
                video.playsInline = true;
                video.muted = true;
                video.srcObject = stream;
                video.style.width = '100%';
                video.style.display = 'block';
                reader.innerHTML = '';
                reader.appendChild(video);
                status.textContent = 'Kamera aktif. Arahkan ke QR identitas rekening.';
                await video.play();

                const scan = async () => {
                    try {
                        const codes = await detector.detect(video);
                        if (codes.length && codes[0].rawValue) {
                            stream.getTracks().forEach((track) => track.stop());
                            const value = codes[0].rawValue;
                            if (value.startsWith(window.location.origin + '/nasabah/scan-rekening/')) {
                                window.location.href = value;
                            } else {
                                status.textContent = 'QR terbaca, tetapi bukan QR rekening ' + brand + '.';
                            }
                            return;
                        }
                    } catch (e) { /* kerangka QR belum terdeteksi, lanjut memindai */ }
                    requestAnimationFrame(scan);
                };
                requestAnimationFrame(scan);
            } catch (e) {
                status.textContent = 'Kamera tidak dapat digunakan. Pastikan izin kamera diberikan pada peramban.';
            }
        })();
    </script>
@endsection
