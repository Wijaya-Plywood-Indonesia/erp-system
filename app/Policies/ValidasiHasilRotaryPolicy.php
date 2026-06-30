<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiHasilRotary;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiHasilRotaryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiHasilRotary');
    }

    public function view(AuthUser $authUser, ValidasiHasilRotary $validasiHasilRotary): bool
    {
        return $authUser->can('View:ValidasiHasilRotary');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiHasilRotary');
    }

    public function update(AuthUser $authUser, ValidasiHasilRotary $validasiHasilRotary): bool
    {
        return $authUser->can('Update:ValidasiHasilRotary');
    }

    public function delete(AuthUser $authUser, ValidasiHasilRotary $validasiHasilRotary): bool
    {
        return $authUser->can('Delete:ValidasiHasilRotary');
    }

    public function restore(AuthUser $authUser, ValidasiHasilRotary $validasiHasilRotary): bool
    {
        return $authUser->can('Restore:ValidasiHasilRotary');
    }

    public function forceDelete(AuthUser $authUser, ValidasiHasilRotary $validasiHasilRotary): bool
    {
        return $authUser->can('ForceDelete:ValidasiHasilRotary');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiHasilRotary');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiHasilRotary');
    }

    public function replicate(AuthUser $authUser, ValidasiHasilRotary $validasiHasilRotary): bool
    {
        return $authUser->can('Replicate:ValidasiHasilRotary');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiHasilRotary');
    }

}