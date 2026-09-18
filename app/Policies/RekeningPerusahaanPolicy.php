<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RekeningPerusahaan;
use Illuminate\Auth\Access\HandlesAuthorization;

class RekeningPerusahaanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RekeningPerusahaan');
    }

    public function view(AuthUser $authUser, RekeningPerusahaan $rekeningPerusahaan): bool
    {
        return $authUser->can('View:RekeningPerusahaan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RekeningPerusahaan');
    }

    public function update(AuthUser $authUser, RekeningPerusahaan $rekeningPerusahaan): bool
    {
        return $authUser->can('Update:RekeningPerusahaan');
    }

    public function delete(AuthUser $authUser, RekeningPerusahaan $rekeningPerusahaan): bool
    {
        return $authUser->can('Delete:RekeningPerusahaan');
    }

    public function restore(AuthUser $authUser, RekeningPerusahaan $rekeningPerusahaan): bool
    {
        return $authUser->can('Restore:RekeningPerusahaan');
    }

    public function forceDelete(AuthUser $authUser, RekeningPerusahaan $rekeningPerusahaan): bool
    {
        return $authUser->can('ForceDelete:RekeningPerusahaan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RekeningPerusahaan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RekeningPerusahaan');
    }

    public function replicate(AuthUser $authUser, RekeningPerusahaan $rekeningPerusahaan): bool
    {
        return $authUser->can('Replicate:RekeningPerusahaan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RekeningPerusahaan');
    }

}