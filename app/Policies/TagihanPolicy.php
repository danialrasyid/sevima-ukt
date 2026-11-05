<?php

namespace App\Policies;

use App\Models\Tagihan;
use App\Models\User;

class TagihanPolicy
{
    /**
     * Admin keuangan: semua tagihan di institusinya.
     * Mahasiswa: hanya tagihan miliknya sendiri.
     */
    public function view(User $user, Tagihan $tagihan): bool
    {
        if ($user->institusi_id === null || (int) $user->institusi_id !== (int) $tagihan->institusi_id) {
            return false;
        }

        if ($user->isAdminKeuangan()) {
            return true;
        }

        $mahasiswa = $user->mahasiswa;

        return $mahasiswa !== null && (int) $mahasiswa->id === (int) $tagihan->mahasiswa_id;
    }
}
