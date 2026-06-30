<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailHasil;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailHasilPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailHasil');
    }

    public function view(AuthUser $authUser, DetailHasil $detailHasil): bool
    {
        return $authUser->can('View:DetailHasil');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailHasil');
    }

    public function update(AuthUser $authUser, DetailHasil $detailHasil): bool
    {
        return $authUser->can('Update:DetailHasil');
    }

    public function delete(AuthUser $authUser, DetailHasil $detailHasil): bool
    {
        return $authUser->can('Delete:DetailHasil');
    }

    public function restore(AuthUser $authUser, DetailHasil $detailHasil): bool
    {
        return $authUser->can('Restore:DetailHasil');
    }

    public function forceDelete(AuthUser $authUser, DetailHasil $detailHasil): bool
    {
        return $authUser->can('ForceDelete:DetailHasil');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailHasil');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailHasil');
    }

    public function replicate(AuthUser $authUser, DetailHasil $detailHasil): bool
    {
        return $authUser->can('Replicate:DetailHasil');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailHasil');
    }

}