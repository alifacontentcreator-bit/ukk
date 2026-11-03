<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Perubahan sandi sendiri.
 *
 * Aturan sandi mengikuti alur yang sudah dipakai aplikasi:
 * minimal 8 karakter + konfirmasi (lihat AuthController@resetPassword).
 */
class UpdateProfilePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:255'],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
                'different:current_password',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Sandi saat ini wajib diisi.',
            'password.required' => 'Sandi baru wajib diisi.',
            'password.min' => 'Sandi baru minimal 8 karakter.',
            'password.max' => 'Sandi baru maksimal 255 karakter.',
            'password.confirmed' => 'Konfirmasi sandi tidak cocok.',
            'password.different' => 'Sandi baru tidak boleh sama dengan sandi lama.',
        ];
    }
}
