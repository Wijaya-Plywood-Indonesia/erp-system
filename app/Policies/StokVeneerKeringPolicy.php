<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\StokVeneerKering;
use Illuminate\Auth\Access\HandlesAuthorization;

class StokVeneerKeringPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StokVeneerKering');
    }

    public function view(AuthUser $authUser, StokVeneerKering $stokVeneerKering): bool
    {
        return $authUser->can('View:StokVeneerKering');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StokVeneerKering');
    }

    public function update(AuthUser $authUser, StokVeneerKering $stokVeneerKering): bool
    {
        return $authUser->can('Update:StokVeneerKering');
    }

    public function delete(AuthUser $authUser, StokVeneerKering $stokVeneerKering): bool
    {
        return $authUser->can('Delete:StokVeneerKering');
    }

    public function restore(AuthUser $authUser, StokVeneerKering $stokVeneerKering): bool
    {
        return $authUser->can('Restore:StokVeneerKering');
    }

    public function forceDelete(AuthUser $authUser, StokVeneerKering $stokVeneerKering): bool
    {
        return $authUser->can('ForceDelete:StokVeneerKering');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StokVeneerKering');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StokVeneerKering');
    }

    public function replicate(AuthUser $authUser, StokVeneerKering $stokVeneerKering): bool
    {
        return $authUser->can('Replicate:StokVeneerKering');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StokVeneerKering');
    }

}