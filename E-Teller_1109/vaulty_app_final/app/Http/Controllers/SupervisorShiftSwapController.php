<?php
/* Tujuan file: Controller ini menghubungkan permintaan halaman fitur supervisor shift swap controller dengan validasi, proses aplikasi, dan tampilan yang sesuai. */


namespace App\Http\Controllers;

use App\Models\ShiftSwapRequest;
use App\Services\ShiftSwapService;
use DomainException;
use Illuminate\Http\Request;

/**
 * =============================================================================
 * FILE INI UNTUK: Supervisor memproses pengajuan TUKAR SHIFT.
 * =============================================================================
 *
 * Klik "Setujui" = memindahkan "kunci" jadwal dari Teller A ke Teller B
 * untuk tanggal yang diajukan (berlaku langsung, tanpa menunggu besok).
 */
class SupervisorShiftSwapController extends Controller
{
    public function __construct(private ShiftSwapService $swapService)
    {
    }

    /** Daftar pengajuan tukar shift (default: yang masih menunggu). */
    public function index(Request $request)
    {
        $filter = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,cancelled,semua'],
        ]);

        $status = $filter['status'] ?? 'pending';

        $pengajuan = ShiftSwapRequest::with(['requester', 'substitute', 'shift', 'approver'])
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $jumlahMenunggu = ShiftSwapRequest::pending()->count();

        return view('supervisor.tukar-shift', [
            'pengajuan' => $pengajuan,
            'status' => $status,
            'jumlahMenunggu' => $jumlahMenunggu,
        ]);
    }

    /** Setujui pengajuan → hak jaga pindah dari pemohon ke pengganti. */
    public function setujui(ShiftSwapRequest $swap)
    {
        try {
            $this->swapService->setujui($swap, auth()->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['tukar_shift' => $exception->getMessage()]);
        }

        return back()->with(
            'success',
            'Tukar shift disetujui. ' . $swap->substitute->name . ' sekarang bertugas pada ' .
            $swap->duty_date->translatedFormat('d F Y') . ', dan ' . $swap->requester->name .
            ' tidak dapat bertransaksi pada tanggal tersebut.'
        );
    }

    /** Tolak pengajuan dengan alasan. */
    public function tolak(Request $request, ShiftSwapRequest $swap)
    {
        $data = $request->validate([
            'rejected_reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'rejected_reason.required' => 'Tuliskan alasan penolakan.',
            'rejected_reason.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        try {
            $this->swapService->tolak($swap, auth()->user(), $data['rejected_reason']);
        } catch (DomainException $exception) {
            return back()->withErrors(['tukar_shift' => $exception->getMessage()]);
        }

        return back()->with('success', 'Pengajuan tukar shift ditolak dan Teller sudah diberi tahu.');
    }
}
