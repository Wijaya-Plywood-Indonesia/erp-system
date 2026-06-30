<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Perusahaan;
use Illuminate\Auth\Access\HandlesAuthorization;

class PerusahaanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Perusahaan');
    }

    public function view(AuthUser $authUser, Perusahaan $perusahaan): bool
    {
        return $authUser->can('View:Perusahaan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Perusahaan');
    }

    public function update(AuthUser $authUser, Perusahaan $perusahaan): bool
    {
        return $authUser->can('Update:Perusahaan');
    }

    public function delete(AuthUser $authUser, Perusahaan $perusahaan): bool
    {
        return $authUser->can('Delete:Perusahaan');
    }

    public function restore(AuthUser $authUser, Perusahaan $perusahaan): bool
    {
        return $authUser->can('Restore:Perusahaan');
    }

    public function forceDelete(AuthUser $authUser, Perusahaan $perusahaan): bool
    {
        return $authUser->can('ForceDelete:Perusahaan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Perusahaan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Perusahaan');
    }

    public function replicate(AuthUser $authUser, Perusahaan $perusahaan): bool
    {
        return $authUser->can('Replicate:Perusahaan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Perusahaan');
    }

}