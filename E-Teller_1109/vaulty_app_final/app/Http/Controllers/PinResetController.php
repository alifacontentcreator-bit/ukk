<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CredentialResetRequest;
use App\Services\PinResetService;
use DomainException;
use Illuminate\Http\Request;

/**
 * Sisi TELLER dari alur reset PIN. Teller hanya MENGAJUKAN; PIN baru berubah
 * setelah Supervisor menyetujui (lihat SupervisorPinResetController).
 */
class PinResetController extends Controller
{
    /** Dokumen identitas yang boleh dipilih teller sebagai bukti pemeriksaan. */
    public const DOKUMEN = [
        'kartu_pelajar' => 'Kartu Pelajar',
        'ktp' => 'KTP',
        'kartu_pegawai' => 'Kartu Identitas Guru/Pegawai',
        'dokumen_lain' => 'Dokumen identitas lain yang sah',
    ];

    public function index(Request $request, PinResetService $resets)
    {
        $resets->expireStale();

        $f = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,expired,cancelled'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $query = CredentialResetRequest::with(['account.user', 'reviewer'])
            ->where('requested_by', auth()->id())
            ->where('type', CredentialResetRequest::TYPE_PIN);

        if (!empty($f['status'])) {
            $query->where('status', $f['status']);
        }
        if (!empty($f['q'])) {
            $q = trim($f['q']);
            $query->whereHas('account', function ($a) use ($q) {
                $a->where('account_number', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%"));
            });
        }

        $requests = $query->latest()->paginate(10)->withQueryString();
        $dokumen = self::DOKUMEN;

        return view('teller.reset-pin', compact('requests', 'dokumen'));
    }

    public function store(Request $request, PinResetService $resets)
    {
        $data = $request->validate([
            'account_number' => ['required', 'string', 'exists:accounts,account_number'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'verification_doc' => ['required', 'in:' . implode(',', array_keys(self::DOKUMEN))],
            'identity_checked' => ['accepted'],
        ], [
            'account_number.exists' => 'Nomor rekening tidak ditemukan.',
            'reason.min' => 'Alasan reset minimal 10 karakter.',
            'identity_checked.accepted' => 'Anda harus menyatakan sudah mencocokkan identitas dan wajah nasabah.',
        ]);

        $note = self::DOKUMEN[$data['verification_doc']] . ' diperiksa langsung; wajah dan identitas dicocokkan oleh teller.';

        try {
            $req = $resets->request(auth()->user(), $data['account_number'], $data['reason'], $note);
        } catch (DomainException $e) {
            return back()->withErrors(['account_number' => $e->getMessage()])->withInput();
        }

        $nama = Account::find($req->account_id)?->user?->name ?? 'nasabah';

        return redirect()->route('teller.reset-pin')
            ->with('success', "Pengajuan reset PIN untuk {$nama} dikirim ke Supervisor. PIN baru direset setelah disetujui (berlaku " . PinResetService::EXPIRE_HOURS . ' jam).');
    }

    public function cancel(int $id, PinResetService $resets)
    {
        $req = CredentialResetRequest::findOrFail($id);

        try {
            $resets->cancel($req, auth()->user());
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Pengajuan reset PIN dibatalkan.');
    }
}