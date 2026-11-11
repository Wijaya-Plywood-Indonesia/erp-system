<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Bm;
use Illuminate\Auth\Access\HandlesAuthorization;

class BmPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Bm');
    }

    public function view(AuthUser $authUser, Bm $bm): bool
    {
        return $authUser->can('View:Bm');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Bm');
    }

    public function update(AuthUser $authUser, Bm $bm): bool
    {
        return $authUser->can('Update:Bm');
    }

    public function delete(AuthUser $authUser, Bm $bm): bool
    {
        return $authUser->can('Delete:Bm');
    }

    public function restore(AuthUser $authUser, Bm $bm): bool
    {
        return $authUser->can('Restore:Bm');
    }

    public function forceDelete(AuthUser $authUser, Bm $bm): bool
    {
        return $authUser->can('ForceDelete:Bm');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Bm');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Bm');
    }

    public function replicate(AuthUser $authUser, Bm $bm): bool
    {
        return $authUser->can('Replicate:Bm');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Bm');
    }

}