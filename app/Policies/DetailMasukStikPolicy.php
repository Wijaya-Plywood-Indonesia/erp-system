<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailMasukStik;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailMasukStikPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailMasukStik');
    }

    public function view(AuthUser $authUser, DetailMasukStik $detailMasukStik): bool
    {
        return $authUser->can('View:DetailMasukStik');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailMasukStik');
    }

    public function update(AuthUser $authUser, DetailMasukStik $detailMasukStik): bool
    {
        return $authUser->can('Update:DetailMasukStik');
    }

    public function delete(AuthUser $authUser, DetailMasukStik $detailMasukStik): bool
    {
        return $authUser->can('Delete:DetailMasukStik');
    }

    public function restore(AuthUser $authUser, DetailMasukStik $detailMasukStik): bool
    {
        return $authUser->can('Restore:DetailMasukStik');
    }

    public function forceDelete(AuthUser $authUser, DetailMasukStik $detailMasukStik): bool
    {
        return $authUser->can('ForceDelete:DetailMasukStik');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailMasukStik');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailMasukStik');
    }

    public function replicate(AuthUser $authUser, DetailMasukStik $detailMasukStik): bool
    {
        return $authUser->can('Replicate:DetailMasukStik');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailMasukStik');
    }

}