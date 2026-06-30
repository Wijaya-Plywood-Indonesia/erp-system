<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PenggunaanLahanRotary;
use Illuminate\Auth\Access\HandlesAuthorization;

class PenggunaanLahanRotaryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PenggunaanLahanRotary');
    }

    public function view(AuthUser $authUser, PenggunaanLahanRotary $penggunaanLahanRotary): bool
    {
        return $authUser->can('View:PenggunaanLahanRotary');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PenggunaanLahanRotary');
    }

    public function update(AuthUser $authUser, PenggunaanLahanRotary $penggunaanLahanRotary): bool
    {
        return $authUser->can('Update:PenggunaanLahanRotary');
    }

    public function delete(AuthUser $authUser, PenggunaanLahanRotary $penggunaanLahanRotary): bool
    {
        return $authUser->can('Delete:PenggunaanLahanRotary');
    }

    public function restore(AuthUser $authUser, PenggunaanLahanRotary $penggunaanLahanRotary): bool
    {
        return $authUser->can('Restore:PenggunaanLahanRotary');
    }

    public function forceDelete(AuthUser $authUser, PenggunaanLahanRotary $penggunaanLahanRotary): bool
    {
        return $authUser->can('ForceDelete:PenggunaanLahanRotary');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PenggunaanLahanRotary');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PenggunaanLahanRotary');
    }

    public function replicate(AuthUser $authUser, PenggunaanLahanRotary $penggunaanLahanRotary): bool
    {
        return $authUser->can('Replicate:PenggunaanLahanRotary');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PenggunaanLahanRotary');
    }

}