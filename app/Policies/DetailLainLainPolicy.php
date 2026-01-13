<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailLainLain;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailLainLainPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailLainLain');
    }

    public function view(AuthUser $authUser, DetailLainLain $detailLainLain): bool
    {
        return $authUser->can('View:DetailLainLain');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailLainLain');
    }

    public function update(AuthUser $authUser, DetailLainLain $detailLainLain): bool
    {
        return $authUser->can('Update:DetailLainLain');
    }

    public function delete(AuthUser $authUser, DetailLainLain $detailLainLain): bool
    {
        return $authUser->can('Delete:DetailLainLain');
    }

    public function restore(AuthUser $authUser, DetailLainLain $detailLainLain): bool
    {
        return $authUser->can('Restore:DetailLainLain');
    }

    public function forceDelete(AuthUser $authUser, DetailLainLain $detailLainLain): bool
    {
        return $authUser->can('ForceDelete:DetailLainLain');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailLainLain');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailLainLain');
    }

    public function replicate(AuthUser $authUser, DetailLainLain $detailLainLain): bool
    {
        return $authUser->can('Replicate:DetailLainLain');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailLainLain');
    }

}