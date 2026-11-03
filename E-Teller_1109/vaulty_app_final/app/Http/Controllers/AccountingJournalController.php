<?php

namespace App\Http\Controllers;

use App\Models\AccountingJournal;
use Illuminate\Http\Request;

class AccountingJournalController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $kategori = $filters['kategori'] ?? 'semua';
        $jurnals = $this->filteredQuery($filters)->get();

        return view('admin.jurnal', compact('jurnals', 'kategori', 'filters'));
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $kategori = $filters['kategori'] ?? 'semua';
        $jurnals = $this->filteredQuery($filters)->get();

        return view('admin.jurnal.cetak', compact('jurnals', 'kategori', 'filters'));
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'kategori' => ['nullable', 'in:semua,siswa,guru'],
            'date' => ['nullable', 'date'],
            // Terima query lama agar tautan yang sudah dibagikan tetap berfungsi.
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'name' => ['nullable', 'string', 'max:255'],
            'account_code' => ['nullable', 'string', 'max:10'],
        ]);
    }

    private function filteredQuery(array $filters)
    {
        $query = AccountingJournal::with(['transaction.account.user'])->latest();

        if (($filters['kategori'] ?? 'semua') === 'siswa') {
            $query->whereHas('transaction.account', fn ($account) => $account->where('product_type', 'tabungan_siswa'));
        } elseif (($filters['kategori'] ?? 'semua') === 'guru') {
            $query->whereHas('transaction.account', fn ($account) => $account->where('product_type', 'tabungan_karyawan'));
        }

        $date = $filters['date'] ?? $filters['date_to'] ?? $filters['date_from'] ?? null;
        if ($date) {
            $query->whereDate('accounting_journals.created_at', $date);
        }
        if (!empty($filters['name'])) {
            $name = trim($filters['name']);
            if ($name !== '') {
                $query->whereHas('transaction.account.user', fn ($user) => $user->where('name', 'like', '%' . $name . '%'));
            }
        }
        if (!empty($filters['account_code'])) {
            $accountCode = trim($filters['account_code']);
            if ($accountCode !== '') {
                $query->where('account_code', 'like', '%' . $accountCode . '%');
            }
        }

        return $query;
    }
}