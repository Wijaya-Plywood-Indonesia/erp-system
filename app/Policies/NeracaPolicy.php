<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Neraca;
use Illuminate\Auth\Access\HandlesAuthorization;

class NeracaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Neraca');
    }

    public function view(AuthUser $authUser, Neraca $neraca): bool
    {
        return $authUser->can('View:Neraca');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Neraca');
    }

    public function update(AuthUser $authUser, Neraca $neraca): bool
    {
        return $authUser->can('Update:Neraca');
    }

    public function delete(AuthUser $authUser, Neraca $neraca): bool
    {
        return $authUser->can('Delete:Neraca');
    }

    public function restore(AuthUser $authUser, Neraca $neraca): bool
    {
        return $authUser->can('Restore:Neraca');
    }

    public function forceDelete(AuthUser $authUser, Neraca $neraca): bool
    {
        return $authUser->can('ForceDelete:Neraca');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Neraca');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Neraca');
    }

    public function replicate(AuthUser $authUser, Neraca $neraca): bool
    {
        return $authUser->can('Replicate:Neraca');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Neraca');
    }

}