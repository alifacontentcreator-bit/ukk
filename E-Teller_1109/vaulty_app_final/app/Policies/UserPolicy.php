<?php
/* Tujuan file: Policy ini menentukan hak akses pengguna terhadap operasi fitur user policy. */


namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Tentukan apakah user bisa mengelola user lain (admin saja)
     */
    public function manage(User $user): bool
    {
        return $user->role === 'admin';
    }
}
