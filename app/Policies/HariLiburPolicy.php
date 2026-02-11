<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HariLibur;
use Illuminate\Auth\Access\HandlesAuthorization;

class HariLiburPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HariLibur');
    }

    public function view(AuthUser $authUser, HariLibur $hariLibur): bool
    {
        return $authUser->can('View:HariLibur');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HariLibur');
    }

    public function update(AuthUser $authUser, HariLibur $hariLibur): bool
    {
        return $authUser->can('Update:HariLibur');
    }

    public function delete(AuthUser $authUser, HariLibur $hariLibur): bool
    {
        return $authUser->can('Delete:HariLibur');
    }

    public function restore(AuthUser $authUser, HariLibur $hariLibur): bool
    {
        return $authUser->can('Restore:HariLibur');
    }

    public function forceDelete(AuthUser $authUser, HariLibur $hariLibur): bool
    {
        return $authUser->can('ForceDelete:HariLibur');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HariLibur');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HariLibur');
    }

    public function replicate(AuthUser $authUser, HariLibur $hariLibur): bool
    {
        return $authUser->can('Replicate:HariLibur');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HariLibur');
    }

}