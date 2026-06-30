<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailBongkarKedi;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailBongkarKediPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailBongkarKedi');
    }

    public function view(AuthUser $authUser, DetailBongkarKedi $detailBongkarKedi): bool
    {
        return $authUser->can('View:DetailBongkarKedi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailBongkarKedi');
    }

    public function update(AuthUser $authUser, DetailBongkarKedi $detailBongkarKedi): bool
    {
        return $authUser->can('Update:DetailBongkarKedi');
    }

    public function delete(AuthUser $authUser, DetailBongkarKedi $detailBongkarKedi): bool
    {
        return $authUser->can('Delete:DetailBongkarKedi');
    }

    public function restore(AuthUser $authUser, DetailBongkarKedi $detailBongkarKedi): bool
    {
        return $authUser->can('Restore:DetailBongkarKedi');
    }

    public function forceDelete(AuthUser $authUser, DetailBongkarKedi $detailBongkarKedi): bool
    {
        return $authUser->can('ForceDelete:DetailBongkarKedi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailBongkarKedi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailBongkarKedi');
    }

    public function replicate(AuthUser $authUser, DetailBongkarKedi $detailBongkarKedi): bool
    {
        return $authUser->can('Replicate:DetailBongkarKedi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailBongkarKedi');
    }

}