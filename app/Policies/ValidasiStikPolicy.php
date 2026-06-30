<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiStik;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiStikPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiStik');
    }

    public function view(AuthUser $authUser, ValidasiStik $validasiStik): bool
    {
        return $authUser->can('View:ValidasiStik');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiStik');
    }

    public function update(AuthUser $authUser, ValidasiStik $validasiStik): bool
    {
        return $authUser->can('Update:ValidasiStik');
    }

    public function delete(AuthUser $authUser, ValidasiStik $validasiStik): bool
    {
        return $authUser->can('Delete:ValidasiStik');
    }

    public function restore(AuthUser $authUser, ValidasiStik $validasiStik): bool
    {
        return $authUser->can('Restore:ValidasiStik');
    }

    public function forceDelete(AuthUser $authUser, ValidasiStik $validasiStik): bool
    {
        return $authUser->can('ForceDelete:ValidasiStik');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiStik');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiStik');
    }

    public function replicate(AuthUser $authUser, ValidasiStik $validasiStik): bool
    {
        return $authUser->can('Replicate:ValidasiStik');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiStik');
    }

}