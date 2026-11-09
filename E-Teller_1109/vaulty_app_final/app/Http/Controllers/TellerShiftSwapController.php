<?php

namespace App\Http\Controllers;

use App\Models\ShiftSwapRequest;
use App\Services\ShiftSwapService;
use DomainException;
use Illuminate\Http\Request;

/**
 * =============================================================================
 * FILE INI UNTUK: Teller mengajukan / membatalkan TUKAR SHIFT.
 * =============================================================================
 *
 * Form pengajuannya ada di halaman "Absensi Jaga" (satu halaman dengan absensi).
 * Setelah Teller mengajukan, Supervisor & Admin langsung menerima notifikasi.
 */
class TellerShiftSwapController extends Controller
{
    public function __construct(private ShiftSwapService $swapService)
    {
    }

    /** Simpan pengajuan tukar shift dari Teller yang sedang login. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'duty_shift_id' => ['required', 'integer', 'exists:duty_shifts,id'],
            'substitute_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'tanggal.required' => 'Pilih tanggal tukar shift.',
            'duty_shift_id.required' => 'Pilih shift Anda yang akan ditukar.',
            'substitute_id.required' => 'Pilih teman yang menggantikan Anda.',
            'reason.required' => 'Tuliskan alasan (mis. ada rapat di sekolah).',
            'reason.min' => 'Alasan minimal 5 karakter.',
        ]);

        try {
            $this->swapService->ajukan(auth()->user(), $data);
        } catch (DomainException $exception) {
            return back()->withErrors(['tukar_shift' => $exception->getMessage()]);
        }

        return back()->with(
            'success',
            'Pengajuan tukar shift terkirim. Supervisor dan Admin sudah menerima notifikasi ' .
            'dan akan memprosesnya. Anda akan diberi tahu setelah disetujui.'
        );
    }

    /** Batalkan pengajuan yang masih menunggu. */
    public function batalkan(ShiftSwapRequest $swap)
    {
        if ((int) $swap->requester_id !== (int) auth()->id()) {
            abort(404);
        }

        if ($swap->status !== 'pending') {
            return back()->withErrors(['tukar_shift' => 'Pengajuan ini sudah diproses, tidak bisa dibatalkan.']);
        }

        $swap->update(['status' => 'cancelled']);

        return back()->with('success', 'Pengajuan tukar shift dibatalkan.');
    }
}
