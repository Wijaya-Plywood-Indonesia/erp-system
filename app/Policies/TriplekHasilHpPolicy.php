<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TriplekHasilHp;
use Illuminate\Auth\Access\HandlesAuthorization;

class TriplekHasilHpPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TriplekHasilHp');
    }

    public function view(AuthUser $authUser, TriplekHasilHp $triplekHasilHp): bool
    {
        return $authUser->can('View:TriplekHasilHp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TriplekHasilHp');
    }

    public function update(AuthUser $authUser, TriplekHasilHp $triplekHasilHp): bool
    {
        return $authUser->can('Update:TriplekHasilHp');
    }

    public function delete(AuthUser $authUser, TriplekHasilHp $triplekHasilHp): bool
    {
        return $authUser->can('Delete:TriplekHasilHp');
    }

    public function restore(AuthUser $authUser, TriplekHasilHp $triplekHasilHp): bool
    {
        return $authUser->can('Restore:TriplekHasilHp');
    }

    public function forceDelete(AuthUser $authUser, TriplekHasilHp $triplekHasilHp): bool
    {
        return $authUser->can('ForceDelete:TriplekHasilHp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TriplekHasilHp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TriplekHasilHp');
    }

    public function replicate(AuthUser $authUser, TriplekHasilHp $triplekHasilHp): bool
    {
        return $authUser->can('Replicate:TriplekHasilHp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TriplekHasilHp');
    }

}