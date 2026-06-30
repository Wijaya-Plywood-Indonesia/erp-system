<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailMasukKedi;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailMasukKediPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailMasukKedi');
    }

    public function view(AuthUser $authUser, DetailMasukKedi $detailMasukKedi): bool
    {
        return $authUser->can('View:DetailMasukKedi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailMasukKedi');
    }

    public function update(AuthUser $authUser, DetailMasukKedi $detailMasukKedi): bool
    {
        return $authUser->can('Update:DetailMasukKedi');
    }

    public function delete(AuthUser $authUser, DetailMasukKedi $detailMasukKedi): bool
    {
        return $authUser->can('Delete:DetailMasukKedi');
    }

    public function restore(AuthUser $authUser, DetailMasukKedi $detailMasukKedi): bool
    {
        return $authUser->can('Restore:DetailMasukKedi');
    }

    public function forceDelete(AuthUser $authUser, DetailMasukKedi $detailMasukKedi): bool
    {
        return $authUser->can('ForceDelete:DetailMasukKedi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailMasukKedi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailMasukKedi');
    }

    public function replicate(AuthUser $authUser, DetailMasukKedi $detailMasukKedi): bool
    {
        return $authUser->can('Replicate:DetailMasukKedi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailMasukKedi');
    }

}