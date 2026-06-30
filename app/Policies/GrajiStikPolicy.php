<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\GrajiStik;
use Illuminate\Auth\Access\HandlesAuthorization;

class GrajiStikPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GrajiStik');
    }

    public function view(AuthUser $authUser, GrajiStik $grajiStik): bool
    {
        return $authUser->can('View:GrajiStik');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GrajiStik');
    }

    public function update(AuthUser $authUser, GrajiStik $grajiStik): bool
    {
        return $authUser->can('Update:GrajiStik');
    }

    public function delete(AuthUser $authUser, GrajiStik $grajiStik): bool
    {
        return $authUser->can('Delete:GrajiStik');
    }

    public function restore(AuthUser $authUser, GrajiStik $grajiStik): bool
    {
        return $authUser->can('Restore:GrajiStik');
    }

    public function forceDelete(AuthUser $authUser, GrajiStik $grajiStik): bool
    {
        return $authUser->can('ForceDelete:GrajiStik');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GrajiStik');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GrajiStik');
    }

    public function replicate(AuthUser $authUser, GrajiStik $grajiStik): bool
    {
        return $authUser->can('Replicate:GrajiStik');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GrajiStik');
    }

}