<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiSanding;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiSandingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiSanding');
    }

    public function view(AuthUser $authUser, PegawaiSanding $pegawaiSanding): bool
    {
        return $authUser->can('View:PegawaiSanding');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiSanding');
    }

    public function update(AuthUser $authUser, PegawaiSanding $pegawaiSanding): bool
    {
        return $authUser->can('Update:PegawaiSanding');
    }

    public function delete(AuthUser $authUser, PegawaiSanding $pegawaiSanding): bool
    {
        return $authUser->can('Delete:PegawaiSanding');
    }

    public function restore(AuthUser $authUser, PegawaiSanding $pegawaiSanding): bool
    {
        return $authUser->can('Restore:PegawaiSanding');
    }

    public function forceDelete(AuthUser $authUser, PegawaiSanding $pegawaiSanding): bool
    {
        return $authUser->can('ForceDelete:PegawaiSanding');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiSanding');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiSanding');
    }

    public function replicate(AuthUser $authUser, PegawaiSanding $pegawaiSanding): bool
    {
        return $authUser->can('Replicate:PegawaiSanding');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiSanding');
    }

}