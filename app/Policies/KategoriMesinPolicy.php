<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\KategoriMesin;
use Illuminate\Auth\Access\HandlesAuthorization;

class KategoriMesinPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:KategoriMesin');
    }

    public function view(AuthUser $authUser, KategoriMesin $kategoriMesin): bool
    {
        return $authUser->can('View:KategoriMesin');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:KategoriMesin');
    }

    public function update(AuthUser $authUser, KategoriMesin $kategoriMesin): bool
    {
        return $authUser->can('Update:KategoriMesin');
    }

    public function delete(AuthUser $authUser, KategoriMesin $kategoriMesin): bool
    {
        return $authUser->can('Delete:KategoriMesin');
    }

    public function restore(AuthUser $authUser, KategoriMesin $kategoriMesin): bool
    {
        return $authUser->can('Restore:KategoriMesin');
    }

    public function forceDelete(AuthUser $authUser, KategoriMesin $kategoriMesin): bool
    {
        return $authUser->can('ForceDelete:KategoriMesin');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:KategoriMesin');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:KategoriMesin');
    }

    public function replicate(AuthUser $authUser, KategoriMesin $kategoriMesin): bool
    {
        return $authUser->can('Replicate:KategoriMesin');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:KategoriMesin');
    }

}