<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiPilihPlywood;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiPilihPlywoodPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiPilihPlywood');
    }

    public function view(AuthUser $authUser, ValidasiPilihPlywood $validasiPilihPlywood): bool
    {
        return $authUser->can('View:ValidasiPilihPlywood');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiPilihPlywood');
    }

    public function update(AuthUser $authUser, ValidasiPilihPlywood $validasiPilihPlywood): bool
    {
        return $authUser->can('Update:ValidasiPilihPlywood');
    }

    public function delete(AuthUser $authUser, ValidasiPilihPlywood $validasiPilihPlywood): bool
    {
        return $authUser->can('Delete:ValidasiPilihPlywood');
    }

    public function restore(AuthUser $authUser, ValidasiPilihPlywood $validasiPilihPlywood): bool
    {
        return $authUser->can('Restore:ValidasiPilihPlywood');
    }

    public function forceDelete(AuthUser $authUser, ValidasiPilihPlywood $validasiPilihPlywood): bool
    {
        return $authUser->can('ForceDelete:ValidasiPilihPlywood');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiPilihPlywood');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiPilihPlywood');
    }

    public function replicate(AuthUser $authUser, ValidasiPilihPlywood $validasiPilihPlywood): bool
    {
        return $authUser->can('Replicate:ValidasiPilihPlywood');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiPilihPlywood');
    }

}