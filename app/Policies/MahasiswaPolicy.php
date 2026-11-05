<?php

namespace App\Policies;

use App\Models\Mahasiswa;
use App\Models\User;

class MahasiswaPolicy
{
    public function view(User $user, Mahasiswa $mahasiswa): bool
    {
        if ($user->institusi_id === null || (int) $user->institusi_id !== (int) $mahasiswa->institusi_id) {
            return false;
        }

        if ($user->isAdminKeuangan()) {
            return true;
        }

        return (int) $mahasiswa->user_id === (int) $user->id;
    }
}
