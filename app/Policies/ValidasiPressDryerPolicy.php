<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiPressDryer;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiPressDryerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiPressDryer');
    }

    public function view(AuthUser $authUser, ValidasiPressDryer $validasiPressDryer): bool
    {
        return $authUser->can('View:ValidasiPressDryer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiPressDryer');
    }

    public function update(AuthUser $authUser, ValidasiPressDryer $validasiPressDryer): bool
    {
        return $authUser->can('Update:ValidasiPressDryer');
    }

    public function delete(AuthUser $authUser, ValidasiPressDryer $validasiPressDryer): bool
    {
        return $authUser->can('Delete:ValidasiPressDryer');
    }

    public function restore(AuthUser $authUser, ValidasiPressDryer $validasiPressDryer): bool
    {
        return $authUser->can('Restore:ValidasiPressDryer');
    }

    public function forceDelete(AuthUser $authUser, ValidasiPressDryer $validasiPressDryer): bool
    {
        return $authUser->can('ForceDelete:ValidasiPressDryer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiPressDryer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiPressDryer');
    }

    public function replicate(AuthUser $authUser, ValidasiPressDryer $validasiPressDryer): bool
    {
        return $authUser->can('Replicate:ValidasiPressDryer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiPressDryer');
    }

}