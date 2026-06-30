<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiHp;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiHpPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiHp');
    }

    public function view(AuthUser $authUser, ValidasiHp $validasiHp): bool
    {
        return $authUser->can('View:ValidasiHp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiHp');
    }

    public function update(AuthUser $authUser, ValidasiHp $validasiHp): bool
    {
        return $authUser->can('Update:ValidasiHp');
    }

    public function delete(AuthUser $authUser, ValidasiHp $validasiHp): bool
    {
        return $authUser->can('Delete:ValidasiHp');
    }

    public function restore(AuthUser $authUser, ValidasiHp $validasiHp): bool
    {
        return $authUser->can('Restore:ValidasiHp');
    }

    public function forceDelete(AuthUser $authUser, ValidasiHp $validasiHp): bool
    {
        return $authUser->can('ForceDelete:ValidasiHp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiHp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiHp');
    }

    public function replicate(AuthUser $authUser, ValidasiHp $validasiHp): bool
    {
        return $authUser->can('Replicate:ValidasiHp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiHp');
    }

}