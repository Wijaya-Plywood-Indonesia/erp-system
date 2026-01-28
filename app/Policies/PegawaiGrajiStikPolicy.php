<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiGrajiStik;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiGrajiStikPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiGrajiStik');
    }

    public function view(AuthUser $authUser, PegawaiGrajiStik $pegawaiGrajiStik): bool
    {
        return $authUser->can('View:PegawaiGrajiStik');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiGrajiStik');
    }

    public function update(AuthUser $authUser, PegawaiGrajiStik $pegawaiGrajiStik): bool
    {
        return $authUser->can('Update:PegawaiGrajiStik');
    }

    public function delete(AuthUser $authUser, PegawaiGrajiStik $pegawaiGrajiStik): bool
    {
        return $authUser->can('Delete:PegawaiGrajiStik');
    }

    public function restore(AuthUser $authUser, PegawaiGrajiStik $pegawaiGrajiStik): bool
    {
        return $authUser->can('Restore:PegawaiGrajiStik');
    }

    public function forceDelete(AuthUser $authUser, PegawaiGrajiStik $pegawaiGrajiStik): bool
    {
        return $authUser->can('ForceDelete:PegawaiGrajiStik');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiGrajiStik');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiGrajiStik');
    }

    public function replicate(AuthUser $authUser, PegawaiGrajiStik $pegawaiGrajiStik): bool
    {
        return $authUser->can('Replicate:PegawaiGrajiStik');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiGrajiStik');
    }

}