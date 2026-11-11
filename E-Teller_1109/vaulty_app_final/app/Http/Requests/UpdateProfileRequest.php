<?php
/* Tujuan file: Form Request ini menentukan validasi dan otorisasi masukan untuk alur update profile request sebelum diproses controller. */


namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Perubahan DATA AKUN yang boleh diubah sendiri (hanya nama lengkap).
 *
 * Form ini sengaja HANYA memvalidasi "name". Kolom lain (role, status,
 * username, nis, nip, kelas, photo, balance, pin, dsb.) tidak pernah
 * diambil dari request, jadi walau dikirim paksa akan diabaikan.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Rapikan input SEBELUM validasi: buang spasi tepi pada nama lengkap.
     *
     * Tanpa ini, nama seperti "  Budi  " akan lolos aturan max:100 dan tersimpan
     * dengan spasi berlebih.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.string' => 'Nama lengkap tidak valid.',
            'name.max' => 'Nama lengkap maksimal 100 karakter.',
        ];
    }
}
