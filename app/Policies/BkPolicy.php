<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Bk;
use Illuminate\Auth\Access\HandlesAuthorization;

class BkPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Bk');
    }

    public function view(AuthUser $authUser, Bk $bk): bool
    {
        return $authUser->can('View:Bk');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Bk');
    }

    public function update(AuthUser $authUser, Bk $bk): bool
    {
        return $authUser->can('Update:Bk');
    }

    public function delete(AuthUser $authUser, Bk $bk): bool
    {
        return $authUser->can('Delete:Bk');
    }

    public function restore(AuthUser $authUser, Bk $bk): bool
    {
        return $authUser->can('Restore:Bk');
    }

    public function forceDelete(AuthUser $authUser, Bk $bk): bool
    {
        return $authUser->can('ForceDelete:Bk');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Bk');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Bk');
    }

    public function replicate(AuthUser $authUser, Bk $bk): bool
    {
        return $authUser->can('Replicate:Bk');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Bk');
    }

}