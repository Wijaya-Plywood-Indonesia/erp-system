<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Lahan;
use Illuminate\Auth\Access\HandlesAuthorization;

class LahanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Lahan');
    }

    public function view(AuthUser $authUser, Lahan $lahan): bool
    {
        return $authUser->can('View:Lahan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Lahan');
    }

    public function update(AuthUser $authUser, Lahan $lahan): bool
    {
        return $authUser->can('Update:Lahan');
    }

    public function delete(AuthUser $authUser, Lahan $lahan): bool
    {
        return $authUser->can('Delete:Lahan');
    }

    public function restore(AuthUser $authUser, Lahan $lahan): bool
    {
        return $authUser->can('Restore:Lahan');
    }

    public function forceDelete(AuthUser $authUser, Lahan $lahan): bool
    {
        return $authUser->can('ForceDelete:Lahan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Lahan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Lahan');
    }

    public function replicate(AuthUser $authUser, Lahan $lahan): bool
    {
        return $authUser->can('Replicate:Lahan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Lahan');
    }

}