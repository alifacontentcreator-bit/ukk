import '@fontsource/public-sans/400.css';
import '@fontsource/public-sans/500.css';
import '@fontsource/public-sans/600.css';
import '@fontsource/public-sans/700.css';
import { BrowserMultiFormatReader, BrowserQRCodeSvgWriter } from '@zxing/browser';

window.startBankMiniQrReader = async function (video, deviceId, onResult, onError) {
    const reader = new BrowserMultiFormatReader();
    const controls = await reader.decodeFromVideoDevice(deviceId || undefined, video, (result, error) => {
        if (result) {
            onResult(result.getText());
            return;
        }

        if (error && error.name !== 'NotFoundException') {
            onError(error);
        }
    });

    return {
        stop() {
            controls.stop();
            video.srcObject?.getTracks().forEach(track => track.stop());
            reader.reset();
        },
    };
};

/* ---------- Jam realtime (WIB) ---------- */
function startClock() {
    const box = document.querySelector('[data-et-clock]');
    if (!box) return;

    const dateEl = box.querySelector('[data-clock-date]');
    const timeEl = box.querySelector('[data-clock-time]');
    const dateFmt = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta' });
    const timeFmt = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Jakarta' });

    const tick = () => {
        const now = new Date();
        if (dateEl) dateEl.textContent = dateFmt.format(now);
        if (timeEl) timeEl.textContent = timeFmt.format(now).replace(/\./g, ':') + ' WIB';
    };

    tick();
    setInterval(tick, 1000);
}

/* ---------- Dialog penolakan (dipakai halaman validasi supervisor) ---------- */
function initRejectDialog() {
    const dialog = document.getElementById('etRejectDialog');
    if (!dialog) return;

    const form = dialog.querySelector('form');
    const title = dialog.querySelector('[data-reject-title]');
    const reason = dialog.querySelector('textarea');

    document.addEventListener('click', (event) => {
        const open = event.target.closest('[data-reject]');
        if (open) {
            form.action = open.dataset.reject;
            if (title) title.textContent = open.dataset.rejectTitle || 'Tolak pengajuan';
            reason.value = '';
            dialog.showModal();
            reason.focus();
            return;
        }

        if (event.target.closest('[data-reject-close]') || event.target === dialog) {
            dialog.close();
        }
    });
}

/* ---------- Filter otomatis: <form data-auto-filter> ----------
   Halaman dimuat ulang setiap filter dikirim, sehingga kursor hilang dan
   pengguna tidak bisa lanjut mengetik. Karena itu kolom yang sedang diketik
   dan posisi kursornya disimpan sebelum kirim, lalu dipulihkan setelah muat. */
const AUTO_FILTER_KEY = 'et-auto-filter-focus';

function saveFilterFocus(form) {
    const el = document.activeElement;
    if (!el || !form.contains(el) || !el.name) return;
    if (!['text', 'search', 'number'].includes(el.type)) return;

    let start = null;
    let end = null;
    try { start = el.selectionStart; end = el.selectionEnd; } catch (e) { /* type=number tidak punya selection */ }

    try {
        sessionStorage.setItem(AUTO_FILTER_KEY, JSON.stringify({
            path: location.pathname,
            name: el.name,
            start,
            end,
            time: Date.now(),
        }));
    } catch (e) { /* penyimpanan browser dimatikan: abaikan */ }
}

function restoreFilterFocus() {
    let saved = null;
    try {
        saved = JSON.parse(sessionStorage.getItem(AUTO_FILTER_KEY) || 'null');
        sessionStorage.removeItem(AUTO_FILTER_KEY);
    } catch (e) { return; }

    if (!saved || saved.path !== location.pathname || Date.now() - saved.time > 5000) return;

    const form = document.querySelector('form[data-auto-filter]');
    const el = form && form.querySelector('[name="' + saved.name + '"]');
    if (!el) return;

    el.focus({ preventScroll: true });
    try {
        const len = el.value.length;
        const start = Math.min(saved.start ?? len, len);
        const end = Math.min(saved.end ?? len, len);
        el.setSelectionRange(start, end);
    } catch (e) { /* type=number: kursor otomatis di akhir */ }
}

function initAutoFilter() {
    restoreFilterFocus();

    document.querySelectorAll('form[data-auto-filter]').forEach((form) => {
        const submit = () => (form.requestSubmit ? form.requestSubmit() : form.submit());
        let timer;

        // Simpan kolom aktif tepat sebelum halaman dimuat ulang (termasuk saat tekan Enter).
        form.addEventListener('submit', () => {
            window.clearTimeout(timer);
            saveFilterFocus(form);
        });

        form.querySelectorAll('select, input[type="date"], input[type="checkbox"], input[type="radio"]').forEach((el) => {
            el.addEventListener('change', submit);
        });

        // Kolom teks: tunggu pengguna berhenti mengetik sejenak, baru kirim.
        form.querySelectorAll('input[type="search"], input[type="text"], input[type="number"]').forEach((el) => {
            el.addEventListener('input', () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(submit, 700);
            });
        });
    });
}

/* ---------- Cegah klik ganda pada tombol kirim ---------- */
function isLogoutForm(form) {
    try {
        return /\/logout\/?$/.test(new URL(form.action, window.location.href).pathname);
    } catch (e) {
        return false;
    }
}

function preventDoubleSubmit() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        // Konfirmasi keluar untuk semua role, supaya tidak keluar karena salah klik.
        // Dicek paling awal: kalau dibatalkan, tombol tidak ikut dinonaktifkan.
        if (isLogoutForm(form) && !window.confirm('Anda yakin untuk keluar dari halaman ini?')) {
            event.preventDefault();
            return;
        }

        if (event.defaultPrevented || form.hasAttribute('data-allow-resubmit') || form.hasAttribute('data-auto-filter')) return;

        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
            window.setTimeout(() => { btn.disabled = true; }, 0);
        });
    });

    // Tombol "kembali" di browser memulihkan halaman dari cache: aktifkan lagi.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            document.querySelectorAll('button[type="submit"]:disabled').forEach((btn) => { btn.disabled = false; });
        }
    });
}

/* ---------- Sidebar: lipat (desktop) / laci geser (mobile) ---------- */
const SIDEBAR_KEY = 'et-sidebar-mini';

function isMobileView() {
    return window.matchMedia('(max-width: 1000px)').matches;
}

function initSidebar() {
    const shell = document.querySelector('.app-shell');
    if (!shell) return;

    const toggle = document.querySelector('[data-sidebar-toggle]');
    const scrims = document.querySelectorAll('[data-sidebar-close]');

    // Pulihkan pilihan lipat sebelumnya. localStorage bisa dilarang browser
    // (mode privat / kebijakan situs), jadi selalu dibungkus try/catch.
    if (!isMobileView()) {
        try {
            if (window.localStorage.getItem(SIDEBAR_KEY) === '1') {
                shell.classList.add('sidebar-mini');
            }
        } catch (e) { /* localStorage tidak tersedia */ }
    }

    const syncAria = () => {
        if (!toggle) return;
        const mini = shell.classList.contains('sidebar-mini');
        toggle.setAttribute('aria-expanded', mini ? 'false' : 'true');
        toggle.setAttribute('title', mini ? 'Perbesar menu' : 'Perkecil menu');
    };
    syncAria();

    if (toggle) {
        toggle.addEventListener('click', () => {
            if (isMobileView()) {
                shell.classList.toggle('is-menu-open');
                return;
            }

            shell.classList.toggle('sidebar-mini');
            try {
                window.localStorage.setItem(SIDEBAR_KEY, shell.classList.contains('sidebar-mini') ? '1' : '0');
            } catch (e) { /* abaikan: hanya preferensi tampilan */ }
            syncAria();
        });
    }

    scrims.forEach((el) => el.addEventListener('click', () => shell.classList.remove('is-menu-open')));

    // Di layar kecil, menu menutup sendiri setelah salah satu tautan dipilih.
    shell.querySelectorAll('.app-sidebar a').forEach((link) => {
        link.addEventListener('click', () => {
            if (isMobileView()) shell.classList.remove('is-menu-open');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') shell.classList.remove('is-menu-open');
    });

    window.addEventListener('resize', () => {
        if (!isMobileView()) {
            shell.classList.remove('is-menu-open');
            syncAria();
        }
    });
}

/* ---------- Tampilkan / sembunyikan sandi ([data-toggle-pass]) ---------- */
function initPasswordToggle() {
    document.querySelectorAll('[data-toggle-pass]').forEach((btn) => {
        const input = document.getElementById(btn.dataset.togglePass || '');
        if (!input) return;

        btn.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.dataset.state = show ? 'on' : 'off';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Sembunyikan sandi' : 'Tampilkan sandi');
        });
    });
}

/* ---------- QR identitas: dibuat lokal agar token tidak dikirim ke pihak ketiga ---------- */
function initLocalQrCodes() {
    const writer = new BrowserQRCodeSvgWriter();

    document.querySelectorAll('[data-qr-value]').forEach((container) => {
        const value = container.dataset.qrValue;

        if (!value) {
            container.textContent = 'QR tidak dapat dibuat. Muat ulang halaman atau hubungi Administrator.';
            container.setAttribute('role', 'alert');
            return;
        }

        try {
            const svg = writer.write(value, 190, 190);
            svg.setAttribute('role', 'img');
            svg.setAttribute('aria-label', container.dataset.qrLabel || 'QR identitas');
            svg.style.display = 'block';
            svg.style.maxWidth = '100%';

            const title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
            title.textContent = container.dataset.qrLabel || 'QR identitas';
            svg.prepend(title);
            container.replaceChildren(svg);
        } catch (error) {
            container.textContent = 'QR tidak dapat dibuat. Muat ulang halaman atau hubungi Administrator.';
            container.setAttribute('role', 'alert');
        }
    });
}

/* ---------- Dropdown pengguna di topbar (Profil Saya / Keluar) ---------- */
function initUserMenu() {
    const wrap = document.querySelector('[data-user-menu]');
    if (!wrap) return;

    const toggle = wrap.querySelector('[data-user-menu-toggle]');
    const panel = wrap.querySelector('[data-user-menu-panel]');
    if (!toggle || !panel) return;

    const items = () => Array.from(panel.querySelectorAll('[role="menuitem"]'));

    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const close = (focusToggle) => {
        if (panel.hidden) return;
        setOpen(false);
        if (focusToggle) toggle.focus();
    };

    toggle.addEventListener('click', (event) => {
        // Cegah pendengar klik global langsung menutup menu yang baru dibuka.
        event.stopPropagation();
        setOpen(panel.hidden);
    });

    // Klik di luar dropdown menutup menu.
    document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target)) close(false);
    });

    // Esc menutup; panah atas/bawah memindahkan fokus antar item.
    wrap.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close(true);
            return;
        }

        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
        if (panel.hidden) return;

        event.preventDefault();
        const list = items();
        if (!list.length) return;

        const index = list.indexOf(document.activeElement);
        const next = event.key === 'ArrowDown'
            ? (index + 1) % list.length
            : (index <= 0 ? list.length - 1 : index - 1);
        list[next].focus();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('table').forEach((table) => {
        const parent = table.parentElement;
        if (!parent || parent.classList.contains('vaulty-table-wrap') || parent.classList.contains('table-wrap') || parent.classList.contains('table-scroll')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'vaulty-table-wrap';
        parent.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });

    initSidebar();
    initUserMenu();
    initPasswordToggle();
    initLocalQrCodes();
    startClock();
    initRejectDialog();
    initAutoFilter();
    preventDoubleSubmit();
});