<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BahanPenolongHp;
use Illuminate\Auth\Access\HandlesAuthorization;

class BahanPenolongHpPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BahanPenolongHp');
    }

    public function view(AuthUser $authUser, BahanPenolongHp $bahanPenolongHp): bool
    {
        return $authUser->can('View:BahanPenolongHp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BahanPenolongHp');
    }

    public function update(AuthUser $authUser, BahanPenolongHp $bahanPenolongHp): bool
    {
        return $authUser->can('Update:BahanPenolongHp');
    }

    public function delete(AuthUser $authUser, BahanPenolongHp $bahanPenolongHp): bool
    {
        return $authUser->can('Delete:BahanPenolongHp');
    }

    public function restore(AuthUser $authUser, BahanPenolongHp $bahanPenolongHp): bool
    {
        return $authUser->can('Restore:BahanPenolongHp');
    }

    public function forceDelete(AuthUser $authUser, BahanPenolongHp $bahanPenolongHp): bool
    {
        return $authUser->can('ForceDelete:BahanPenolongHp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BahanPenolongHp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BahanPenolongHp');
    }

    public function replicate(AuthUser $authUser, BahanPenolongHp $bahanPenolongHp): bool
    {
        return $authUser->can('Replicate:BahanPenolongHp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BahanPenolongHp');
    }

}