<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiGrajiStik;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiGrajiStikPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiGrajiStik');
    }

    public function view(AuthUser $authUser, ValidasiGrajiStik $validasiGrajiStik): bool
    {
        return $authUser->can('View:ValidasiGrajiStik');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiGrajiStik');
    }

    public function update(AuthUser $authUser, ValidasiGrajiStik $validasiGrajiStik): bool
    {
        return $authUser->can('Update:ValidasiGrajiStik');
    }

    public function delete(AuthUser $authUser, ValidasiGrajiStik $validasiGrajiStik): bool
    {
        return $authUser->can('Delete:ValidasiGrajiStik');
    }

    public function restore(AuthUser $authUser, ValidasiGrajiStik $validasiGrajiStik): bool
    {
        return $authUser->can('Restore:ValidasiGrajiStik');
    }

    public function forceDelete(AuthUser $authUser, ValidasiGrajiStik $validasiGrajiStik): bool
    {
        return $authUser->can('ForceDelete:ValidasiGrajiStik');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiGrajiStik');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiGrajiStik');
    }

    public function replicate(AuthUser $authUser, ValidasiGrajiStik $validasiGrajiStik): bool
    {
        return $authUser->can('Replicate:ValidasiGrajiStik');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiGrajiStik');
    }

}