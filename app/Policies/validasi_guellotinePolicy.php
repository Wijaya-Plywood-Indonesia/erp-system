<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\validasi_guellotine;
use Illuminate\Auth\Access\HandlesAuthorization;

class validasi_guellotinePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiGuellotine');
    }

    public function view(AuthUser $authUser, validasi_guellotine $validasiGuellotine): bool
    {
        return $authUser->can('View:ValidasiGuellotine');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiGuellotine');
    }

    public function update(AuthUser $authUser, validasi_guellotine $validasiGuellotine): bool
    {
        return $authUser->can('Update:ValidasiGuellotine');
    }

    public function delete(AuthUser $authUser, validasi_guellotine $validasiGuellotine): bool
    {
        return $authUser->can('Delete:ValidasiGuellotine');
    }

    public function restore(AuthUser $authUser, validasi_guellotine $validasiGuellotine): bool
    {
        return $authUser->can('Restore:ValidasiGuellotine');
    }

    public function forceDelete(AuthUser $authUser, validasi_guellotine $validasiGuellotine): bool
    {
        return $authUser->can('ForceDelete:ValidasiGuellotine');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiGuellotine');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiGuellotine');
    }

    public function replicate(AuthUser $authUser, validasi_guellotine $validasiGuellotine): bool
    {
        return $authUser->can('Replicate:ValidasiGuellotine');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiGuellotine');
    }

}