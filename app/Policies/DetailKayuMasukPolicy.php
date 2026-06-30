<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailKayuMasuk;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailKayuMasukPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailKayuMasuk');
    }

    public function view(AuthUser $authUser, DetailKayuMasuk $detailKayuMasuk): bool
    {
        return $authUser->can('View:DetailKayuMasuk');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailKayuMasuk');
    }

    public function update(AuthUser $authUser, DetailKayuMasuk $detailKayuMasuk): bool
    {
        return $authUser->can('Update:DetailKayuMasuk');
    }

    public function delete(AuthUser $authUser, DetailKayuMasuk $detailKayuMasuk): bool
    {
        return $authUser->can('Delete:DetailKayuMasuk');
    }

    public function restore(AuthUser $authUser, DetailKayuMasuk $detailKayuMasuk): bool
    {
        return $authUser->can('Restore:DetailKayuMasuk');
    }

    public function forceDelete(AuthUser $authUser, DetailKayuMasuk $detailKayuMasuk): bool
    {
        return $authUser->can('ForceDelete:DetailKayuMasuk');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailKayuMasuk');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailKayuMasuk');
    }

    public function replicate(AuthUser $authUser, DetailKayuMasuk $detailKayuMasuk): bool
    {
        return $authUser->can('Replicate:DetailKayuMasuk');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailKayuMasuk');
    }

}