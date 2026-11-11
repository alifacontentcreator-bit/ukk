<?php
/* Tujuan file: Controller ini menghubungkan permintaan halaman fitur supervisor pin reset controller dengan validasi, proses aplikasi, dan tampilan yang sesuai. */


namespace App\Http\Controllers;

use App\Models\CredentialResetRequest;
use App\Services\PinResetService;
use DomainException;
use Illuminate\Http\Request;

/**
 * Sisi SUPERVISOR dari alur reset PIN: pemeriksa kedua (checker) yang
 * menyetujui atau menolak pengajuan teller.
 */
class SupervisorPinResetController extends Controller
{
    public function index(Request $request, PinResetService $resets)
    {
        $resets->expireStale();

        $f = $request->validate([
            'status' => ['nullable', 'in:approved,rejected,expired,cancelled'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $pending = CredentialResetRequest::with(['account.user', 'requester'])
            ->open()
            ->where('type', CredentialResetRequest::TYPE_PIN)
            ->oldest()
            ->get();

        $history = CredentialResetRequest::with(['account.user', 'requester', 'reviewer'])
            ->where('type', CredentialResetRequest::TYPE_PIN)
            ->where('status', '!=', CredentialResetRequest::STATUS_PENDING);

        if (!empty($f['status'])) {
            $history->where('status', $f['status']);
        }
        if (!empty($f['q'])) {
            $q = trim($f['q']);
            $history->whereHas('account', function ($a) use ($q) {
                $a->where('account_number', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%"));
            });
        }

        $history = $history->latest()->paginate(10)->withQueryString();

        return view('supervisor.reset-pin', compact('pending', 'history'));
    }

    public function approve(int $id, PinResetService $resets)
    {
        $req = CredentialResetRequest::with('account.user')->findOrFail($id);

        try {
            $done = $resets->approve($req, auth()->user());
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        if (!$done) {
            return back()->withErrors(['error' => 'Pengajuan sudah kedaluwarsa. Minta teller mengajukan ulang.']);
        }

        $nama = $req->account?->user?->name ?? 'nasabah';

        return back()->with('success', "Reset PIN {$nama} disetujui. Nasabah dapat membuat PIN baru melalui aplikasi.");
    }

    public function reject(int $id, Request $request, PinResetService $resets)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $req = CredentialResetRequest::findOrFail($id);

        try {
            $resets->reject($req, auth()->user(), $data['reason']);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Pengajuan reset PIN ditolak.');
    }
}