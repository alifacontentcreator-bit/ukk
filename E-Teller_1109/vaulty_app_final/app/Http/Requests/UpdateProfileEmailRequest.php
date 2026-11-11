<?php
/* Tujuan file: Form Request ini menentukan validasi dan otorisasi masukan untuk alur update profile email request sebelum diproses controller. */


namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Perubahan email pemulihan milik sendiri.
 *
 * Wajib menyertakan sandi saat ini (diperiksa Hash::check di controller) dan
 * email harus unik. Pesan galat sengaja umum agar tidak membocorkan akun lain.
 */
class UpdateProfileEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Rapikan input SEBELUM validasi: buang spasi tepi dan jadikan huruf kecil.
     *
     * Tujuannya agar pemeriksaan unik (Rule::unique) ikut menangkap email yang
     * sama tetapi ditulis dengan huruf besar, dan nilai yang disimpan seragam
     * dengan data yang dipakai alur pemulihan sandi.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($this->user()->getKey()),
            ],
            'current_password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 150 karakter.',
            'email.unique' => 'Email tidak dapat digunakan.',
            'current_password.required' => 'Sandi saat ini wajib diisi.',
        ];
    }
}
