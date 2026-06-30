<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\JenisBarang;
use Illuminate\Auth\Access\HandlesAuthorization;

class JenisBarangPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JenisBarang');
    }

    public function view(AuthUser $authUser, JenisBarang $jenisBarang): bool
    {
        return $authUser->can('View:JenisBarang');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JenisBarang');
    }

    public function update(AuthUser $authUser, JenisBarang $jenisBarang): bool
    {
        return $authUser->can('Update:JenisBarang');
    }

    public function delete(AuthUser $authUser, JenisBarang $jenisBarang): bool
    {
        return $authUser->can('Delete:JenisBarang');
    }

    public function restore(AuthUser $authUser, JenisBarang $jenisBarang): bool
    {
        return $authUser->can('Restore:JenisBarang');
    }

    public function forceDelete(AuthUser $authUser, JenisBarang $jenisBarang): bool
    {
        return $authUser->can('ForceDelete:JenisBarang');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JenisBarang');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JenisBarang');
    }

    public function replicate(AuthUser $authUser, JenisBarang $jenisBarang): bool
    {
        return $authUser->can('Replicate:JenisBarang');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JenisBarang');
    }

}