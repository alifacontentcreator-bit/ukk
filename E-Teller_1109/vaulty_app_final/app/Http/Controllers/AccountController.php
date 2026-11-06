<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use App\Services\AccountNumberGenerator;
use App\Services\PinResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::with('user')->latest()->get();
        return view('admin.rekening', compact('accounts'));
    }

    public function create()
    {
        // Mengambil ID user yang sudah memiliki rekening
        $existingUserIds = Account::pluck('user_id')->toArray();

        // Mengambil user dengan role 'customer' yang belum punya rekening
        $customers = User::where('role', 'customer')
            ->whereNotIn('id', $existingUserIds)
            ->orderBy('name')
            ->get();

        return view('admin.rekening-create', compact('customers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'customer'))],
            'balance' => ['required', 'numeric', 'min:0'],
        ]);

        if (Account::where('user_id', $data['user_id'])->exists()) {
            return back()->withErrors(['user_id' => 'Nasabah tersebut sudah memiliki rekening.'])->withInput();
        }

        $customer = User::findOrFail($data['user_id']);

        // Data kelas wajib sudah lengkap (diisi saat nasabah didaftarkan) agar no. rekening bisa dibentuk
        if (!$customer->jurusan || !$customer->angkatan || !$customer->kelas_urut) {
            return back()->withErrors([
                'user_id' => 'Data kelas/jurusan nasabah ini belum lengkap. Lengkapi data nasabah terlebih dahulu sebelum membuat rekening.',
            ])->withInput();
        }

        // Generate Nomor Rekening: <JURUSAN>-<ANGKATAN>-<KELAS_URUT>-<PRESENSI>
        $accountNumber = AccountNumberGenerator::generate($customer->jurusan, $customer->angkatan, $customer->kelas_urut);

        $data['account_number'] = $accountNumber;
        $data['pin'] = Hash::make(Str::random(64));
        $data['pin_is_default'] = true;
        $data['qr_token'] = Str::random(48);

        Account::create($data);

        return redirect()->route('admin.rekening')->with(
            'success',
            "Rekening berhasil dibuat! Nomor: {$accountNumber}. Nasabah akan membuat PIN sendiri saat pertama kali masuk aplikasi."
        );
    }

    public function edit(Account $account)
    {
        $account->load('user');
        return view('admin.rekening-edit', compact('account'));
    }

    public function update(Request $request, Account $account)
    {
        $data = $request->validate([
            'balance' => ['required', 'numeric', 'min:0'],
        ]);

        if (!$account->qr_token) {
            $data['qr_token'] = Str::random(48);
        }

        $account->update($data);

        return redirect()->route('admin.rekening')->with('success', 'Rekening berhasil diperbarui.');
    }

    /**
     * Reset DARURAT oleh admin. Reset PIN normal diajukan nasabah dari aplikasi
     * dan disetujui di halaman Admin > Reset PIN. Jalur ini wajib alasan dan
     * tetap tercatat di credential_reset_requests.
     */
    public function resetPin(Request $request, Account $account, PinResetService $resets)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Alasan reset darurat wajib diisi.',
            'reason.min' => 'Alasan reset darurat minimal 10 karakter.',
        ]);

        $resets->forceReset($account, auth()->user(), $data['reason']);

        return back()->with('success', 'PIN sementara nasabah telah direset ke 123456 dan notifikasi telah dikirim.');
    }

    public function qr(Account $account)
    {
        if (!$account->qr_token) {
            $account->update(['qr_token' => Str::random(48)]);
        }

        $account->load('user');
        $scanUrl = route('customer.qr-account', $account->qr_token);

        return view('admin.rekening-qr', compact('account', 'scanUrl'));
    }
}