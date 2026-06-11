<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OpnameStok;
use Illuminate\Auth\Access\HandlesAuthorization;

class OpnameStokPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OpnameStok');
    }

    public function view(AuthUser $authUser, OpnameStok $opnameStok): bool
    {
        return $authUser->can('View:OpnameStok');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OpnameStok');
    }

    public function update(AuthUser $authUser, OpnameStok $opnameStok): bool
    {
        return $authUser->can('Update:OpnameStok');
    }

    public function delete(AuthUser $authUser, OpnameStok $opnameStok): bool
    {
        return $authUser->can('Delete:OpnameStok');
    }

    public function restore(AuthUser $authUser, OpnameStok $opnameStok): bool
    {
        return $authUser->can('Restore:OpnameStok');
    }

    public function forceDelete(AuthUser $authUser, OpnameStok $opnameStok): bool
    {
        return $authUser->can('ForceDelete:OpnameStok');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OpnameStok');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OpnameStok');
    }

    public function replicate(AuthUser $authUser, OpnameStok $opnameStok): bool
    {
        return $authUser->can('Replicate:OpnameStok');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OpnameStok');
    }

}