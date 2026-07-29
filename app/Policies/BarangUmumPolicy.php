<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BarangUmum;
use Illuminate\Auth\Access\HandlesAuthorization;

class BarangUmumPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BarangUmum');
    }

    public function view(AuthUser $authUser, BarangUmum $barangUmum): bool
    {
        return $authUser->can('View:BarangUmum');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BarangUmum');
    }

    public function update(AuthUser $authUser, BarangUmum $barangUmum): bool
    {
        return $authUser->can('Update:BarangUmum');
    }

    public function delete(AuthUser $authUser, BarangUmum $barangUmum): bool
    {
        return $authUser->can('Delete:BarangUmum');
    }

    public function restore(AuthUser $authUser, BarangUmum $barangUmum): bool
    {
        return $authUser->can('Restore:BarangUmum');
    }

    public function forceDelete(AuthUser $authUser, BarangUmum $barangUmum): bool
    {
        return $authUser->can('ForceDelete:BarangUmum');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BarangUmum');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BarangUmum');
    }

    public function replicate(AuthUser $authUser, BarangUmum $barangUmum): bool
    {
        return $authUser->can('Replicate:BarangUmum');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BarangUmum');
    }

}