<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\GantiPisauRotary;
use Illuminate\Auth\Access\HandlesAuthorization;

class GantiPisauRotaryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GantiPisauRotary');
    }

    public function view(AuthUser $authUser, GantiPisauRotary $gantiPisauRotary): bool
    {
        return $authUser->can('View:GantiPisauRotary');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GantiPisauRotary');
    }

    public function update(AuthUser $authUser, GantiPisauRotary $gantiPisauRotary): bool
    {
        return $authUser->can('Update:GantiPisauRotary');
    }

    public function delete(AuthUser $authUser, GantiPisauRotary $gantiPisauRotary): bool
    {
        return $authUser->can('Delete:GantiPisauRotary');
    }

    public function restore(AuthUser $authUser, GantiPisauRotary $gantiPisauRotary): bool
    {
        return $authUser->can('Restore:GantiPisauRotary');
    }

    public function forceDelete(AuthUser $authUser, GantiPisauRotary $gantiPisauRotary): bool
    {
        return $authUser->can('ForceDelete:GantiPisauRotary');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GantiPisauRotary');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GantiPisauRotary');
    }

    public function replicate(AuthUser $authUser, GantiPisauRotary $gantiPisauRotary): bool
    {
        return $authUser->can('Replicate:GantiPisauRotary');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GantiPisauRotary');
    }

}