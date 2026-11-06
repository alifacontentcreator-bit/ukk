<?php

namespace App\Http\Controllers;

use App\Models\CredentialResetRequest;
use App\Services\PinResetService;
use DomainException;
use Illuminate\Http\Request;

class AdminPinResetController extends Controller
{
    public function index(PinResetService $resets)
    {
        $resets->expireStale();

        $pending = CredentialResetRequest::with(['account.user', 'requester'])
            ->open()
            ->where('type', CredentialResetRequest::TYPE_PIN)
            ->oldest()
            ->get();

        $history = CredentialResetRequest::with(['account.user', 'requester', 'reviewer'])
            ->where('type', CredentialResetRequest::TYPE_PIN)
            ->where('status', '!=', CredentialResetRequest::STATUS_PENDING)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.reset-pin', compact('pending', 'history'));
    }

    public function approve(int $id, PinResetService $resets)
    {
        $request = CredentialResetRequest::findOrFail($id);

        try {
            $approved = $resets->approve($request, auth()->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['reset_pin' => $exception->getMessage()]);
        }

        if (!$approved) {
            return back()->withErrors(['reset_pin' => 'Pengajuan sudah kedaluwarsa. Minta nasabah mengajukan ulang dari aplikasi.']);
        }

        return back()->with('success', 'Pengajuan lupa/buka blokir PIN disetujui. Blokir PIN dibuka dan nasabah akan diminta membuat PIN baru melalui aplikasi.');
    }

    public function reject(int $id, Request $request, PinResetService $resets)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $resets->reject(CredentialResetRequest::findOrFail($id), auth()->user(), $data['reason']);
        } catch (DomainException $exception) {
            return back()->withErrors(['reset_pin' => $exception->getMessage()]);
        }

        return back()->with('success', 'Pengajuan lupa PIN ditolak. Nasabah akan menerima alasan penolakan di aplikasi.');
    }
}