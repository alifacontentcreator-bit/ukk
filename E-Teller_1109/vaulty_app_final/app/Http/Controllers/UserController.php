<?php
/* Tujuan file: Controller ini menghubungkan permintaan halaman fitur user controller dengan validasi, proses aplikasi, dan tampilan yang sesuai. */


namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Account;
use App\Services\AccountNumberGenerator;
use App\Services\ExcelExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

        $users = $query->with('account')->get();

        return view('admin.users.index', compact('users', 'filters'));
    }

    public function importStudents(Request $request)
    {
        $this->authorize('manage', User::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        try {
            $rows = IOFactory::load($request->file('file')->getRealPath())
                ->getActiveSheet()
                ->toArray(null, true, true, false);
        } catch (\Throwable $exception) {
            return back()->withErrors(['file' => 'File tidak dapat dibaca. Gunakan Excel (.xlsx/.xls) atau CSV yang valid.']);
        }

        try {
            [$headerIndex, $columns] = $this->studentImportColumns($rows);
        } catch (\DomainException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        $result = ['created' => 0, 'skipped' => []];
        $processed = 0;

        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
            $name = trim((string) ($row[$columns['name']] ?? ''));
            $nis = trim((string) ($row[$columns['nis']] ?? ''));
            $classLabel = $this->normalizeStudentClass((string) ($row[$columns['class']] ?? ''));
            $email = mb_strtolower(trim((string) ($row[$columns['email']] ?? '')));

            if ($name === '' && $nis === '' && $classLabel === '' && $email === '') {
                continue;
            }

            if (++$processed > 500) {
                $result['skipped'][] = ['row' => $index + 1, 'name' => '-', 'reason' => 'Batas impor 500 siswa per file sudah tercapai.'];
                break;
            }

            $skip = function (string $reason) use (&$result, $index, $name): void {
                $result['skipped'][] = ['row' => $index + 1, 'name' => $name ?: '-', 'reason' => $reason];
            };

            if ($name === '' || strlen($name) > 255) {
                $skip('Nama wajib diisi dan maksimal 255 karakter.');
                continue;
            }
            if (!preg_match('/^\d{5}$/', $nis)) {
                $skip('NIS harus terdiri dari tepat 5 angka.');
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skip('Email wajib diisi dengan format yang valid.');
                continue;
            }

            try {
                $classAttrs = AccountNumberGenerator::resolveClassAttributes($classLabel);
            } catch (\Throwable $exception) {
                $skip('Format kelas tidak valid. Contoh: XII RPL1.');
                continue;
            }

            if (User::where('nis', $nis)->orWhere('username', $nis)->exists()) {
                $skip('NIS atau username sudah terdaftar.');
                continue;
            }
            if (User::where('email', $email)->exists()) {
                $skip('Email sudah digunakan akun lain.');
                continue;
            }

            try {
                DB::transaction(function () use ($name, $nis, $email, $classAttrs): void {
                    $accountNumber = AccountNumberGenerator::generate(
                        $classAttrs['jurusan'],
                        $classAttrs['angkatan'],
                        $classAttrs['kelas_urut']
                    );

                    $user = User::create([
                        'name' => $name,
                        'username' => $nis,
                        'password' => Hash::make(Str::random(64)),
                        'role' => 'customer',
                        'status' => 'inactive',
                        'nis' => $nis,
                        'kelas' => $classAttrs['kelas'],
                        'jurusan' => $classAttrs['jurusan'],
                        'tingkat' => $classAttrs['tingkat'],
                        'kelas_urut' => $classAttrs['kelas_urut'],
                        'angkatan' => $classAttrs['angkatan'],
                        'email' => $email,
                    ]);

                    /*
                     * Akun nasabah baru TIDAK langsung aktif. Statusnya nonaktif
                     * sampai nasabah menyelesaikan aktivasi di aplikasi mobile,
                     * dengan tenggat aktivasi 60 hari sejak akun dibuat. Bila
                     * tenggat terlewat, akun tetap nonaktif (tidak dihapus) dan
                     * masih dapat diaktivasi.
                     */
                    $user->forceFill([
                        'must_change_password' => true,
                        'activation_deadline' => now()->addDays(User::ACTIVATION_WINDOW_DAYS),
                    ])->save();

                    Account::create([
                        'user_id' => $user->id,
                        'account_number' => $accountNumber,
                        'pin' => Hash::make(Str::random(64)),
                        'pin_is_default' => true,
                        'balance' => 0,
                        'product_type' => 'tabungan_siswa',
                        'qr_token' => Str::random(48),
                        'status' => 'active',
                    ]);
                });
                $result['created']++;
            } catch (\Throwable $exception) {
                report($exception);
                $skip('Gagal membuat akun atau rekening. Periksa data duplikat lalu coba kembali.');
            }
        }

        return redirect()->route('admin.users')
            ->with('student_import_result', $result)
            ->with('success', $result['created'] . ' akun siswa dibuat dan menunggu aktivasi.');
    }

    public function studentImportTemplate(ExcelExport $excel)
    {
        $this->authorize('manage', User::class);

        return $excel->download(
            'template-impor-siswa.xlsx',
            ['Nama', 'NIS', 'Kelas', 'Email'],
            [],
            'Impor Siswa'
        );
    }

    private function studentImportColumns(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            $columns = [];
            foreach ($row as $column => $value) {
                $header = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $value));
                if ($header === '') continue;
                if (!isset($columns['name']) && str_starts_with($header, 'nama')) $columns['name'] = $column;
                elseif (!isset($columns['nis']) && $header === 'nis') $columns['nis'] = $column;
                elseif (!isset($columns['class']) && str_starts_with($header, 'kelas')) $columns['class'] = $column;
                elseif (!isset($columns['email']) && str_contains($header, 'email')) $columns['email'] = $column;
            }

            if (isset($columns['name'], $columns['nis'], $columns['class'], $columns['email'])) {
                return [$index, $columns];
            }
        }

        throw new \DomainException('Header wajib: Nama, NIS, Kelas, dan Email.');
    }

    private function normalizeStudentClass(string $class): string
    {
        $class = strtoupper(trim($class));
        if (preg_match('/^(XII|XI|X)\s+([A-Z]+)\s*(\d*)$/', $class, $matches)) {
            return $matches[1] . ' ' . $matches[2] . $matches[3];
        }

        return $class;
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
                    'status' => 'inactive',

                    'nis' => $request->nis,
                    'kelas' => $classAttrs['kelas'],
                    'jurusan' => $classAttrs['jurusan'],
                    'tingkat' => $classAttrs['tingkat'],
                    'kelas_urut' => $classAttrs['kelas_urut'],
                    'angkatan' => $classAttrs['angkatan'],

                    'email' => $request->email,
                    'photo' => $photoPath,
                ]);

                /*
                 * Akun belum diaktivasi: status nonaktif sampai nasabah
                 * menyelesaikan aktivasi di aplikasi mobile. Tenggat aktivasi
                 * 60 hari sejak akun dibuat; lewat tenggat akun tetap
                 * nonaktif (tidak dihapus) dan masih dapat diaktivasi.
                 */
                $user->forceFill([
                    'must_change_password' => true,
                    'activation_deadline' => now()->addDays(User::ACTIVATION_WINDOW_DAYS),
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
                        "Akun berstatus NONAKTIF sampai nasabah menyelesaikan aktivasi di aplikasi mobile. " .
                        "Kode aktivasi dikirim ke email terdaftar dan berlaku selama 3 menit; " .
                        "tenggat aktivasi 60 hari sejak akun dibuat."
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
                    'status' => 'inactive',

                    'nip' => $request->nip,
                    'jabatan' => $request->jabatan,

                    'email' => $request->email,
                    'photo' => $photoPath,
                ]);

                /*
                 * Akun belum boleh login sebelum aktivasi: status nonaktif
                 * sampai nasabah menyelesaikan aktivasi di aplikasi mobile,
                 * dengan tenggat aktivasi 60 hari sejak akun dibuat.
                 */
                $user->forceFill([
                    'must_change_password' => true,
                    'activation_deadline' => now()->addDays(User::ACTIVATION_WINDOW_DAYS),
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
                        "Akun berstatus NONAKTIF sampai nasabah menyelesaikan aktivasi di aplikasi mobile. " .
                        "Kode aktivasi dikirim ke email terdaftar dan berlaku selama 3 menit; " .
                        "tenggat aktivasi 60 hari sejak akun dibuat."
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

            'status' => [
                'nullable',
                'in:active,inactive,blocked',
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
            'nis.unique' => 'Nomor induk siswa ini sudah digunakan oleh pengguna lain.',
            'nis.digits' => 'Nomor induk siswa wajib terdiri dari tepat 5 angka.',
            'nip.unique' => 'Nomor induk pegawai ini sudah digunakan oleh pengguna lain.',
            'username.unique' => 'Nama pengguna ini sudah digunakan.',
            'photo.uploaded' => 'Foto profil gagal diunggah. Ukuran berkas melebihi batas server; pilih foto yang lebih kecil (maksimal 5 MB).',
            'photo.file' => 'Foto profil gagal diunggah. Pilih berkas gambar yang valid.',
            'photo.mimes' => 'Foto profil harus berupa gambar (JPG, PNG, WEBP, GIF, BMP, atau SVG).',
            'photo.max' => 'Ukuran foto profil maksimal 5 MB. Pilih foto yang lebih kecil.',
        ]);

        /*
         * Jaring pengaman: formulir menandai "photo_expected" saat admin sudah
         * memilih berkas foto. Bila berkas itu tidak sampai ke server (mis.
         * ukurannya melebihi batas PHP sehingga dianggap tidak ada), permintaan
         * ditolak dengan pesan yang jelas. Tanpa ini, penyimpanan tetap
         * "berhasil" padahal foto profil tidak ikut berganti.
         */
        if ($request->boolean('photo_expected') && !$request->hasFile('photo')) {
            return back()
                ->withInput()
                ->withErrors([
                    'photo' => 'Foto profil gagal diunggah, kemungkinan ukurannya melebihi batas server. '
                        . 'Pilih foto maksimal 5 MB lalu simpan kembali.',
                ]);
        }

        $data = [
            'name' => $request->name,
            'username' => $request->username,
            'role' => $request->role,
            'email' => $request->email,

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

        /*
         * Foto profil hanya diganti bila admin benar-benar memilih berkas baru.
         * Berkas foto lama dihapus dari disk "public" supaya tidak menumpuk
         * berkas yatim dan tidak ada gambar lama yang tertinggal.
         */
        if ($request->hasFile('photo')) {
            $fotoBaru = $request->file('photo')->store('photos', 'public');

            if ($fotoBaru) {
                $data['photo'] = $fotoBaru;

                if (filled($user->photo) && $user->photo !== $fotoBaru) {
                    Storage::disk('public')->delete($user->photo);
                }
            }
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

        /*
         * Bila peran diubah dari nasabah menjadi petugas (admin/teller/
         * supervisor), akun tidak lagi memakai aktivasi aplikasi mobile:
         * kebutuhan aktivasi dihapus dan akun langsung aktif agar bisa login
         * di web. Tanpa ini akun petugas akan tetap berstatus nonaktif.
         */
        $promosiPetugas = $data['role'] !== 'customer' && $user->isAwaitingActivation();

        if ($promosiPetugas) {
            $user->forceFill([
                'must_change_password' => false,
                'password_changed_at' => now(),
                'activation_deadline' => null,
                'status' => 'active',
            ]);
        }

        /*
         * Status dari form hanya diterapkan bila memang berbeda. Akun sendiri
         * tidak boleh dinonaktifkan dari form ini, dan akun nasabah yang belum
         * diaktivasi tidak boleh diaktifkan manual: status aktifnya diberikan
         * otomatis saat aktivasi aplikasi mobile berhasil.
         */
        $statusBaru = $request->input('status');

        if (
            !$promosiPetugas
            && in_array($statusBaru, ['active', 'inactive', 'blocked'], true)
            && $statusBaru !== $user->status
        ) {
            if ($user->id === auth()->id() && $statusBaru !== 'active') {
                return back()->withInput()->withErrors([
                    'status' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
                ]);
            }

            if ($data['role'] === 'customer' && $user->isAwaitingActivation() && $statusBaru === 'active') {
                return back()->withInput()->withErrors([
                    'status' => "Status aktif akun nasabah {$user->name} diberikan otomatis "
                        . 'setelah nasabah menyelesaikan aktivasi di aplikasi mobile.',
                ]);
            }

            $data['status'] = $statusBaru;
        }

        $user->update($data);

        return redirect()
            ->route('admin.users')
            ->with(
                'success',
                array_key_exists('photo', $data)
                    ? 'Data pengguna dan foto profil berhasil diperbarui.'
                    : 'Data pengguna berhasil diperbarui.'
            );
    }

    /**
     * Nonaktifkan atau aktifkan kembali pengguna.
     *
     * Akun NASABAH TIDAK PERNAH DIHAPUS. Akun nasabah baru berstatus nonaktif
     * sampai nasabah menyelesaikan aktivasi di aplikasi mobile; status aktif
     * diberikan oleh sistem saat aktivasi berhasil, bukan oleh admin.
     */
    public function toggleStatus(User $user)
    {
        $this->authorize('manage', User::class);

        if ($user->id === auth()->id()) {
            return back()->withErrors([
                'error' => 'Akses ditolak: Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ]);
        }

        /*
         * Akun nasabah yang belum diaktivasi hanya boleh dibuka blokirnya.
         * Mengaktifkannya secara manual akan membuat status "Aktif" padahal
         * nasabah belum pernah membuat password/PIN di aplikasi mobile.
         */
        if ($user->isAwaitingActivation() && $user->status !== 'blocked') {
            return back()->withErrors([
                'error' => "Akun nasabah {$user->name} masih menunggu aktivasi di aplikasi mobile. "
                    . 'Status aktif diberikan otomatis setelah nasabah menyelesaikan aktivasi.',
            ]);
        }

        if ($user->status === 'active') {
            // Status apa pun selain "active" (mis. blocked/inactive) dianggap nonaktif.
            $user->status = 'blocked';
        } elseif ($user->isAwaitingActivation()) {
            // Buka blokir akun yang belum diaktivasi: kembali menunggu aktivasi.
            $user->status = 'inactive';
        } else {
            $user->status = 'active';
        }

        $user->save();

        if ($user->isAwaitingActivation()) {
            return back()->with(
                'success',
                "Blokir akun {$user->name} berhasil dibuka. Akun tetap nonaktif sampai "
                    . 'nasabah menyelesaikan aktivasi di aplikasi mobile.'
            );
        }

        $keterangan = $user->status === 'active'
            ? 'DIAKTIFKAN KEMBALI'
            : 'DINONAKTIFKAN';

        return back()->with(
            'success',
            "Status akun {$user->name} berhasil {$keterangan}."
        );
    }
}
