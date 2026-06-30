<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailHasilStik;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailHasilStikPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailHasilStik');
    }

    public function view(AuthUser $authUser, DetailHasilStik $detailHasilStik): bool
    {
        return $authUser->can('View:DetailHasilStik');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailHasilStik');
    }

    public function update(AuthUser $authUser, DetailHasilStik $detailHasilStik): bool
    {
        return $authUser->can('Update:DetailHasilStik');
    }

    public function delete(AuthUser $authUser, DetailHasilStik $detailHasilStik): bool
    {
        return $authUser->can('Delete:DetailHasilStik');
    }

    public function restore(AuthUser $authUser, DetailHasilStik $detailHasilStik): bool
    {
        return $authUser->can('Restore:DetailHasilStik');
    }

    public function forceDelete(AuthUser $authUser, DetailHasilStik $detailHasilStik): bool
    {
        return $authUser->can('ForceDelete:DetailHasilStik');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailHasilStik');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailHasilStik');
    }

    public function replicate(AuthUser $authUser, DetailHasilStik $detailHasilStik): bool
    {
        return $authUser->can('Replicate:DetailHasilStik');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailHasilStik');
    }

}