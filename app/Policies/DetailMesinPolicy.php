<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailMesin;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailMesinPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailMesin');
    }

    public function view(AuthUser $authUser, DetailMesin $detailMesin): bool
    {
        return $authUser->can('View:DetailMesin');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailMesin');
    }

    public function update(AuthUser $authUser, DetailMesin $detailMesin): bool
    {
        return $authUser->can('Update:DetailMesin');
    }

    public function delete(AuthUser $authUser, DetailMesin $detailMesin): bool
    {
        return $authUser->can('Delete:DetailMesin');
    }

    public function restore(AuthUser $authUser, DetailMesin $detailMesin): bool
    {
        return $authUser->can('Restore:DetailMesin');
    }

    public function forceDelete(AuthUser $authUser, DetailMesin $detailMesin): bool
    {
        return $authUser->can('ForceDelete:DetailMesin');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailMesin');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailMesin');
    }

    public function replicate(AuthUser $authUser, DetailMesin $detailMesin): bool
    {
        return $authUser->can('Replicate:DetailMesin');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailMesin');
    }

}