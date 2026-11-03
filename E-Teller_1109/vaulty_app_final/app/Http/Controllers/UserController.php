<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Account;
use App\Services\AccountNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Menampilkan daftar pengguna.
     */
    public function index(Request $request)
    {
        $this->authorize('manage', User::class);

        $filters = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'nis' => ['nullable', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'in:customer,teller,supervisor,admin'],
        ]);

        $query = User::latest();

        if (!empty($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        if (!empty($filters['nis'])) {
            $query->where(function ($identity) use ($filters) {
                $identity->where('nis', 'like', '%' . $filters['nis'] . '%')
                    ->orWhere('nip', 'like', '%' . $filters['nis'] . '%');
            });
        }

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        $users = $query->get();

        return view('admin.users.index', compact('users', 'filters'));
    }

    /**
     * Form tambah pengguna.
     */
    public function create()
    {
        $this->authorize('manage', User::class);

        return view('admin.users.create');
    }

    /**
     * Menyimpan pengguna baru.
     */
    public function store(Request $request)
    {
        $this->authorize('manage', User::class);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:admin,supervisor,teller,customer'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'no_telp' => ['nullable', 'string', 'max:20', 'unique:users,no_telp'],
            'photo' => [
                'nullable',
                'file',
                'mimes:jpeg,png,jpg,gif,svg,webp,bmp,heic',
                'max:5120',
            ],
        ];

        /*
         * Validasi pengguna internal:
         * Admin, Teller, dan Supervisor.
         */
        if ($request->role !== 'customer') {
            $rules['username'] = [
                'required',
                'string',
                'max:255',
                'unique:users,username',
            ];

            $rules['password'] = [
                'required',
                'string',
                'min:6',
            ];

            if (in_array($request->role, ['teller', 'supervisor'], true)) {
                $rules['email'] = [
                    'required',
                    'email',
                    'max:255',
                    'unique:users,email',
                ];
            }

            if ($request->role === 'teller') {
                $rules['nis'] = [
                    'required',
                    'digits:5',
                    'unique:users,nis',
                ];

                $rules['kelas'] = [
                    'required',
                    'string',
                    'max:30',
                ];
            }

            if ($request->role === 'supervisor') {
                $rules['nip'] = [
                    'required',
                    'string',
                    'max:30',
                    'unique:users,nip',
                ];

                $rules['alamat'] = [
                    'required',
                    'string',
                    'max:255',
                ];
            }
        } else {
            $rules['email'] = [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ];

            /*
             * Validasi nasabah siswa.
             */
            if ($request->input('tipe_nasabah', 'siswa') === 'siswa') {
                $rules['nis'] = [
                    'required',
                    'digits:5',
                    'unique:users,nis',
                ];

                $rules['kode_kelas'] = [
                    'required',
                    'string',
                ];
            } else {
                /*
                 * Validasi nasabah guru/karyawan.
                 */
                $rules['nip'] = [
                    'required',
                    'string',
                    'max:30',
                    'unique:users,nip',
                ];

                $rules['jabatan'] = [
                    'required',
                    'in:GRU,KAR',
                ];
            }
        }

        $request->validate($rules, [
            'email.unique' => 'Email ini sudah digunakan oleh pengguna lain.',
            'no_telp.unique' => 'Nomor telepon ini sudah digunakan oleh pengguna lain.',
            'nis.unique' => 'Nomor induk siswa ini sudah digunakan oleh pengguna lain.',
            'nis.digits' => 'Nomor induk siswa wajib terdiri dari tepat 5 angka.',
            'nip.unique' => 'Nomor induk pegawai ini sudah digunakan oleh pengguna lain.',
            'username.unique' => 'Nama pengguna ini sudah digunakan.',
        ]);

        /*
         * Username nasabah harus menggunakan NIS atau NIP.
         * Pastikan tidak bertabrakan dengan username pengguna lain.
         */
        if ($request->role === 'customer') {
            $usernameNasabah = $request->input('tipe_nasabah', 'siswa') === 'siswa'
                ? $request->nis
                : $request->nip;

            if (User::where('username', $usernameNasabah)->exists()) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'error' => "Username '{$usernameNasabah}' sudah digunakan.",
                    ]);
            }
        }

        DB::beginTransaction();

        try {
            /*
             * =====================================================
             * NASABAH SISWA
             * =====================================================
             */
            if (
                $request->role === 'customer' &&
                $request->input('tipe_nasabah', 'siswa') === 'siswa'
            ) {
                $classAttrs = AccountNumberGenerator::resolveClassAttributes(
                    $request->kode_kelas
                );

                $username = $request->nis;

                $photoPath = null;

                if ($request->hasFile('photo')) {
                    $photoPath = $request->file('photo')
                        ->store('photos', 'public');
                }

                /*
                 * Password acak sementara.
                 * Nasabah wajib membuat password sendiri melalui aktivasi.
                 */
                $temporaryPassword = Str::random(64);

                $user = User::create([
                    'name' => $request->name,
                    'username' => $username,
                    'password' => Hash::make($temporaryPassword),
                    'role' => 'customer',
                    'status' => 'active',

                    'nis' => $request->nis,
                    'kelas' => $classAttrs['kelas'],
                    'jurusan' => $classAttrs['jurusan'],
                    'tingkat' => $classAttrs['tingkat'],
                    'kelas_urut' => $classAttrs['kelas_urut'],
                    'angkatan' => $classAttrs['angkatan'],

                    'email' => $request->email,
                    'no_telp' => $request->no_telp,
                    'photo' => $photoPath,
                ]);

                /*
                 * Tandai akun belum diaktivasi.
                 */
                $user->forceFill([
                    'must_change_password' => true,
                ])->save();

                /*
                 * Buat nomor rekening siswa.
                 */
                $noRekening = AccountNumberGenerator::generate(
                    $classAttrs['jurusan'],
                    $classAttrs['angkatan'],
                    $classAttrs['kelas_urut']
                );

                /*
                 * Buat rekening dan QR nasabah.
                 */
                Account::create([
                    'user_id' => $user->id,
                    'account_number' => $noRekening,
                    'pin' => Hash::make(Str::random(64)),
                    'pin_is_default' => true,
                    'balance' => 0,
                    'product_type' => 'tabungan_siswa',
                    'qr_token' => Str::random(48),
                    'status' => 'active',
                ]);

                DB::commit();

                return redirect()
                    ->route('admin.users')
                    ->with(
                        'success',
                        "Nasabah siswa berhasil ditambahkan. " .
                        "No. rekening: {$noRekening}. " .
                        "Username: {$username}. " .
                        "Nasabah dapat meminta kode aktivasi dari aplikasi. Kode dikirim ke email terdaftar dan berlaku selama 3 menit."
                    );
            }

            /*
             * =====================================================
             * NASABAH GURU / KARYAWAN
             * =====================================================
             */
            if ($request->role === 'customer') {
                $username = $request->nip;

                $photoPath = null;

                if ($request->hasFile('photo')) {
                    $photoPath = $request->file('photo')
                        ->store('photos', 'public');
                }

                /*
                 * Password acak sementara.
                 */
                $temporaryPassword = Str::random(64);

                $user = User::create([
                    'name' => $request->name,
                    'username' => $username,
                    'password' => Hash::make($temporaryPassword),
                    'role' => 'customer',
                    'status' => 'active',

                    'nip' => $request->nip,
                    'jabatan' => $request->jabatan,

                    'email' => $request->email,
                    'no_telp' => $request->no_telp,
                    'photo' => $photoPath,
                ]);

                /*
                 * Akun belum boleh login sebelum aktivasi.
                 */
                $user->forceFill([
                    'must_change_password' => true,
                ])->save();

                /*
                 * Buat nomor rekening guru/karyawan.
                 */
                $noRekening = AccountNumberGenerator::generateStaff(
                    $request->jabatan,
                    $request->nip
                );

                /*
                 * Buat rekening dan QR nasabah.
                 */
                Account::create([
                    'user_id' => $user->id,
                    'account_number' => $noRekening,
                    'pin' => Hash::make(Str::random(64)),
                    'pin_is_default' => true,
                    'balance' => 0,
                    'product_type' => 'tabungan_karyawan',
                    'qr_token' => Str::random(48),
                    'status' => 'active',
                ]);

                DB::commit();

                return redirect()
                    ->route('admin.users')
                    ->with(
                        'success',
                        "Nasabah guru/karyawan berhasil ditambahkan. " .
                        "No. rekening: {$noRekening}. " .
                        "Username: {$username}. " .
                        "Nasabah dapat meminta kode aktivasi dari aplikasi. Kode dikirim ke email terdaftar dan berlaku selama 3 menit."
                    );
            }

            /*
             * =====================================================
             * PENGGUNA INTERNAL
             * ADMIN, TELLER, SUPERVISOR
             * =====================================================
             */
            $photoPath = null;

            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')
                    ->store('photos', 'public');
            }

            User::create([
                'name' => $request->name,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'status' => 'active',

                'nis' => $request->role === 'teller'
                    ? $request->nis
                    : null,

                'kelas' => $request->role === 'teller'
                    ? $request->kelas
                    : null,

                'nip' => $request->role === 'supervisor'
                    ? $request->nip
                    : null,

                'alamat' => $request->role === 'supervisor'
                    ? $request->alamat
                    : null,

                'email' => $request->email,
                'no_telp' => $request->no_telp,
                'photo' => $photoPath,
            ]);

            DB::commit();

            return redirect()
                ->route('admin.users')
                ->with('success', 'Pengguna berhasil ditambahkan.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors([
                    'error' => 'Gagal menyimpan data: ' . $e->getMessage(),
                ]);
        }
    }

    /**
     * Form edit pengguna.
     */
    public function edit(User $user)
    {
        $this->authorize('manage', User::class);

        return view('admin.users.edit', compact('user'));
    }

    /**
     * Memperbarui data pengguna.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('manage', User::class);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'username' => [
                'required',
                'string',
                'max:255',
                'unique:users,username,' . $user->id,
            ],

            'role' => [
                'required',
                'in:admin,supervisor,teller,customer',
            ],

            'password' => [
                'nullable',
                'string',
                'min:6',
            ],

            'photo' => [
                'nullable',
                'file',
                'mimes:jpeg,png,jpg,gif,svg,webp,bmp,heic',
                'max:5120',
            ],

            'email' => [
                $request->role === 'customer' ||
                in_array($request->role, ['teller', 'supervisor'], true)
                    ? 'required'
                    : 'nullable',
                'email',
                'max:255',
                'unique:users,email,' . $user->id,
            ],

            'no_telp' => [
                'nullable',
                'string',
                'max:20',
                'unique:users,no_telp,' . $user->id,
            ],

            'nip' => [
                $request->role === 'supervisor' ? 'required' : 'nullable',
                'string',
                'max:30',
                'unique:users,nip,' . $user->id,
            ],

            'alamat' => [
                $request->role === 'supervisor' ? 'required' : 'nullable',
                'string',
                'max:255',
            ],

            'nis' => [
                $request->role === 'teller' || ($request->role === 'customer' && filled($user->nis))
                    ? 'required'
                    : 'nullable',
                'digits:5',
                'unique:users,nis,' . $user->id,
            ],

            'kelas' => [
                $request->role === 'teller' ? 'required' : 'nullable',
                'string',
                'max:30',
            ],
        ], [
            'email.unique' => 'Email ini sudah digunakan oleh pengguna lain.',
            'no_telp.unique' => 'Nomor telepon ini sudah digunakan oleh pengguna lain.',
            'nis.unique' => 'Nomor induk siswa ini sudah digunakan oleh pengguna lain.',
            'nis.digits' => 'Nomor induk siswa wajib terdiri dari tepat 5 angka.',
            'nip.unique' => 'Nomor induk pegawai ini sudah digunakan oleh pengguna lain.',
            'username.unique' => 'Nama pengguna ini sudah digunakan.',
        ]);

        $data = [
            'name' => $request->name,
            'username' => $request->username,
            'role' => $request->role,
            'email' => $request->email,
            'no_telp' => $request->no_telp,

            'nis' => $request->role === 'teller'
                ? $request->nis
                : ($request->role === 'customer' && filled($user->nis) ? $request->nis : null),

            'kelas' => $request->role === 'teller'
                ? $request->kelas
                : ($request->role === 'customer' ? $user->kelas : null),

            'nip' => $request->role === 'supervisor'
                ? $request->nip
                : ($request->role === 'customer' ? $user->nip : null),

            'alamat' => $request->role === 'supervisor'
                ? $request->alamat
                : ($request->role === 'customer' ? $user->alamat : null),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')
                ->store('photos', 'public');
        }

        if (
            $user->must_change_password &&
            $user->email !== $data['email']
        ) {
            DB::table('customer_activation_tokens')
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users')
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    /**
     * Blokir atau aktifkan pengguna.
     */
    public function toggleStatus(User $user)
    {
        $this->authorize('manage', User::class);

        if ($user->id === auth()->id()) {
            return back()->withErrors([
                'error' => 'Akses ditolak: Anda tidak dapat memblokir akun Anda sendiri.',
            ]);
        }

        $user->status = $user->status === 'blocked'
            ? 'active'
            : 'blocked';

        $user->save();

        $keterangan = $user->status === 'blocked'
            ? 'DIBLOKIR'
            : 'DIAKTIFKAN';

        return back()->with(
            'success',
            "Status akun {$user->name} berhasil {$keterangan}."
        );
    }

    /**
     * Menghapus pengguna.
     */
    public function destroy(User $user)
    {
        $this->authorize('manage', User::class);

        if ($user->id === auth()->id()) {
            return back()->withErrors([
                'error' => 'Anda tidak bisa menghapus akun Anda sendiri.',
            ]);
        }

        /*
         * Transaksi, laporan harian, dan koreksi memakai foreign key RESTRICT
         * agar jejak audit tidak ikut terhapus. Tolak lebih awal supaya admin
         * tidak melihat error SQL 1451, dan arahkan ke fitur blokir.
         */
        $blockers = $user->deletionBlockers();

        if ($blockers !== []) {
            return back()->withErrors([
                'error' => sprintf(
                    'Pengguna %s tidak dapat dihapus karena masih memiliki %s. '
                    . 'Jejak transaksi dan audit wajib dipertahankan; gunakan tombol blokir bila akun ini sudah tidak dipakai.',
                    $user->name,
                    implode(', ', $blockers)
                ),
            ]);
        }

        try {
            DB::transaction(fn () => $user->delete());
        } catch (QueryException $e) {
            /*
             * Jaring pengaman bila masih ada tabel lain yang menunjuk ke user
             * ini (mis. data baru yang belum terdaftar di deletionBlockers()).
             */
            if (!$this->isForeignKeyViolation($e)) {
                throw $e;
            }

            return back()->withErrors([
                'error' => "Pengguna {$user->name} tidak dapat dihapus karena datanya masih terhubung dengan transaksi, laporan, atau riwayat lain. Gunakan tombol blokir agar data tersebut tetap utuh.",
            ]);
        }

        return back()->with(
            'success',
            'Pengguna berhasil dihapus secara permanen.'
        );
    }

    /**
     * Apakah exception berasal dari pelanggaran foreign key (SQLSTATE 23000).
     */
    private function isForeignKeyViolation(QueryException $e): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;

        return in_array($driverCode, [1451, 1452], true)
            || str_contains(strtolower($e->getMessage()), 'foreign key constraint');
    }
}