<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\KategoriBarang;
use Illuminate\Auth\Access\HandlesAuthorization;

class KategoriBarangPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:KategoriBarang');
    }

    public function view(AuthUser $authUser, KategoriBarang $kategoriBarang): bool
    {
        return $authUser->can('View:KategoriBarang');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:KategoriBarang');
    }

    public function update(AuthUser $authUser, KategoriBarang $kategoriBarang): bool
    {
        return $authUser->can('Update:KategoriBarang');
    }

    public function delete(AuthUser $authUser, KategoriBarang $kategoriBarang): bool
    {
        return $authUser->can('Delete:KategoriBarang');
    }

    public function restore(AuthUser $authUser, KategoriBarang $kategoriBarang): bool
    {
        return $authUser->can('Restore:KategoriBarang');
    }

    public function forceDelete(AuthUser $authUser, KategoriBarang $kategoriBarang): bool
    {
        return $authUser->can('ForceDelete:KategoriBarang');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:KategoriBarang');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:KategoriBarang');
    }

    public function replicate(AuthUser $authUser, KategoriBarang $kategoriBarang): bool
    {
        return $authUser->can('Replicate:KategoriBarang');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:KategoriBarang');
    }

}