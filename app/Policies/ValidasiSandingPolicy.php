<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiSanding;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiSandingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiSanding');
    }

    public function view(AuthUser $authUser, ValidasiSanding $validasiSanding): bool
    {
        return $authUser->can('View:ValidasiSanding');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiSanding');
    }

    public function update(AuthUser $authUser, ValidasiSanding $validasiSanding): bool
    {
        return $authUser->can('Update:ValidasiSanding');
    }

    public function delete(AuthUser $authUser, ValidasiSanding $validasiSanding): bool
    {
        return $authUser->can('Delete:ValidasiSanding');
    }

    public function restore(AuthUser $authUser, ValidasiSanding $validasiSanding): bool
    {
        return $authUser->can('Restore:ValidasiSanding');
    }

    public function forceDelete(AuthUser $authUser, ValidasiSanding $validasiSanding): bool
    {
        return $authUser->can('ForceDelete:ValidasiSanding');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiSanding');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiSanding');
    }

    public function replicate(AuthUser $authUser, ValidasiSanding $validasiSanding): bool
    {
        return $authUser->can('Replicate:ValidasiSanding');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiSanding');
    }

}