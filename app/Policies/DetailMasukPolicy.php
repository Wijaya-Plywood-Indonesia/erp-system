<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailMasuk;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailMasukPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailMasuk');
    }

    public function view(AuthUser $authUser, DetailMasuk $detailMasuk): bool
    {
        return $authUser->can('View:DetailMasuk');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailMasuk');
    }

    public function update(AuthUser $authUser, DetailMasuk $detailMasuk): bool
    {
        return $authUser->can('Update:DetailMasuk');
    }

    public function delete(AuthUser $authUser, DetailMasuk $detailMasuk): bool
    {
        return $authUser->can('Delete:DetailMasuk');
    }

    public function restore(AuthUser $authUser, DetailMasuk $detailMasuk): bool
    {
        return $authUser->can('Restore:DetailMasuk');
    }

    public function forceDelete(AuthUser $authUser, DetailMasuk $detailMasuk): bool
    {
        return $authUser->can('ForceDelete:DetailMasuk');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailMasuk');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailMasuk');
    }

    public function replicate(AuthUser $authUser, DetailMasuk $detailMasuk): bool
    {
        return $authUser->can('Replicate:DetailMasuk');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailMasuk');
    }

}