<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\KayuMasuk;
use Illuminate\Auth\Access\HandlesAuthorization;

class KayuMasukPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:KayuMasuk');
    }

    public function view(AuthUser $authUser, KayuMasuk $kayuMasuk): bool
    {
        return $authUser->can('View:KayuMasuk');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:KayuMasuk');
    }

    public function update(AuthUser $authUser, KayuMasuk $kayuMasuk): bool
    {
        return $authUser->can('Update:KayuMasuk');
    }

    public function delete(AuthUser $authUser, KayuMasuk $kayuMasuk): bool
    {
        return $authUser->can('Delete:KayuMasuk');
    }

    public function restore(AuthUser $authUser, KayuMasuk $kayuMasuk): bool
    {
        return $authUser->can('Restore:KayuMasuk');
    }

    public function forceDelete(AuthUser $authUser, KayuMasuk $kayuMasuk): bool
    {
        return $authUser->can('ForceDelete:KayuMasuk');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:KayuMasuk');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:KayuMasuk');
    }

    public function replicate(AuthUser $authUser, KayuMasuk $kayuMasuk): bool
    {
        return $authUser->can('Replicate:KayuMasuk');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:KayuMasuk');
    }

}