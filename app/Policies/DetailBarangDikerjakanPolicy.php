<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailBarangDikerjakan;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailBarangDikerjakanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailBarangDikerjakan');
    }

    public function view(AuthUser $authUser, DetailBarangDikerjakan $detailBarangDikerjakan): bool
    {
        return $authUser->can('View:DetailBarangDikerjakan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailBarangDikerjakan');
    }

    public function update(AuthUser $authUser, DetailBarangDikerjakan $detailBarangDikerjakan): bool
    {
        return $authUser->can('Update:DetailBarangDikerjakan');
    }

    public function delete(AuthUser $authUser, DetailBarangDikerjakan $detailBarangDikerjakan): bool
    {
        return $authUser->can('Delete:DetailBarangDikerjakan');
    }

    public function restore(AuthUser $authUser, DetailBarangDikerjakan $detailBarangDikerjakan): bool
    {
        return $authUser->can('Restore:DetailBarangDikerjakan');
    }

    public function forceDelete(AuthUser $authUser, DetailBarangDikerjakan $detailBarangDikerjakan): bool
    {
        return $authUser->can('ForceDelete:DetailBarangDikerjakan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailBarangDikerjakan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailBarangDikerjakan');
    }

    public function replicate(AuthUser $authUser, DetailBarangDikerjakan $detailBarangDikerjakan): bool
    {
        return $authUser->can('Replicate:DetailBarangDikerjakan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailBarangDikerjakan');
    }

}