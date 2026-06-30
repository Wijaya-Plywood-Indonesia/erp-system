<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BarangSetengahJadiHp;
use Illuminate\Auth\Access\HandlesAuthorization;

class BarangSetengahJadiHpPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BarangSetengahJadiHp');
    }

    public function view(AuthUser $authUser, BarangSetengahJadiHp $barangSetengahJadiHp): bool
    {
        return $authUser->can('View:BarangSetengahJadiHp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BarangSetengahJadiHp');
    }

    public function update(AuthUser $authUser, BarangSetengahJadiHp $barangSetengahJadiHp): bool
    {
        return $authUser->can('Update:BarangSetengahJadiHp');
    }

    public function delete(AuthUser $authUser, BarangSetengahJadiHp $barangSetengahJadiHp): bool
    {
        return $authUser->can('Delete:BarangSetengahJadiHp');
    }

    public function restore(AuthUser $authUser, BarangSetengahJadiHp $barangSetengahJadiHp): bool
    {
        return $authUser->can('Restore:BarangSetengahJadiHp');
    }

    public function forceDelete(AuthUser $authUser, BarangSetengahJadiHp $barangSetengahJadiHp): bool
    {
        return $authUser->can('ForceDelete:BarangSetengahJadiHp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BarangSetengahJadiHp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BarangSetengahJadiHp');
    }

    public function replicate(AuthUser $authUser, BarangSetengahJadiHp $barangSetengahJadiHp): bool
    {
        return $authUser->can('Replicate:BarangSetengahJadiHp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BarangSetengahJadiHp');
    }

}