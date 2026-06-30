<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RekapKayuMasuk;
use Illuminate\Auth\Access\HandlesAuthorization;

class RekapKayuMasukPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RekapKayuMasuk');
    }

    public function view(AuthUser $authUser, RekapKayuMasuk $rekapKayuMasuk): bool
    {
        return $authUser->can('View:RekapKayuMasuk');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RekapKayuMasuk');
    }

    public function update(AuthUser $authUser, RekapKayuMasuk $rekapKayuMasuk): bool
    {
        return $authUser->can('Update:RekapKayuMasuk');
    }

    public function delete(AuthUser $authUser, RekapKayuMasuk $rekapKayuMasuk): bool
    {
        return $authUser->can('Delete:RekapKayuMasuk');
    }

    public function restore(AuthUser $authUser, RekapKayuMasuk $rekapKayuMasuk): bool
    {
        return $authUser->can('Restore:RekapKayuMasuk');
    }

    public function forceDelete(AuthUser $authUser, RekapKayuMasuk $rekapKayuMasuk): bool
    {
        return $authUser->can('ForceDelete:RekapKayuMasuk');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RekapKayuMasuk');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RekapKayuMasuk');
    }

    public function replicate(AuthUser $authUser, RekapKayuMasuk $rekapKayuMasuk): bool
    {
        return $authUser->can('Replicate:RekapKayuMasuk');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RekapKayuMasuk');
    }

}