<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Mesin;
use Illuminate\Auth\Access\HandlesAuthorization;

class MesinPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Mesin');
    }

    public function view(AuthUser $authUser, Mesin $mesin): bool
    {
        return $authUser->can('View:Mesin');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Mesin');
    }

    public function update(AuthUser $authUser, Mesin $mesin): bool
    {
        return $authUser->can('Update:Mesin');
    }

    public function delete(AuthUser $authUser, Mesin $mesin): bool
    {
        return $authUser->can('Delete:Mesin');
    }

    public function restore(AuthUser $authUser, Mesin $mesin): bool
    {
        return $authUser->can('Restore:Mesin');
    }

    public function forceDelete(AuthUser $authUser, Mesin $mesin): bool
    {
        return $authUser->can('ForceDelete:Mesin');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Mesin');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Mesin');
    }

    public function replicate(AuthUser $authUser, Mesin $mesin): bool
    {
        return $authUser->can('Replicate:Mesin');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Mesin');
    }

}