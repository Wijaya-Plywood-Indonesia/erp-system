<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailBarangDikerjakanPotSiku;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailBarangDikerjakanPotSikuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailBarangDikerjakanPotSiku');
    }

    public function view(AuthUser $authUser, DetailBarangDikerjakanPotSiku $detailBarangDikerjakanPotSiku): bool
    {
        return $authUser->can('View:DetailBarangDikerjakanPotSiku');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailBarangDikerjakanPotSiku');
    }

    public function update(AuthUser $authUser, DetailBarangDikerjakanPotSiku $detailBarangDikerjakanPotSiku): bool
    {
        return $authUser->can('Update:DetailBarangDikerjakanPotSiku');
    }

    public function delete(AuthUser $authUser, DetailBarangDikerjakanPotSiku $detailBarangDikerjakanPotSiku): bool
    {
        return $authUser->can('Delete:DetailBarangDikerjakanPotSiku');
    }

    public function restore(AuthUser $authUser, DetailBarangDikerjakanPotSiku $detailBarangDikerjakanPotSiku): bool
    {
        return $authUser->can('Restore:DetailBarangDikerjakanPotSiku');
    }

    public function forceDelete(AuthUser $authUser, DetailBarangDikerjakanPotSiku $detailBarangDikerjakanPotSiku): bool
    {
        return $authUser->can('ForceDelete:DetailBarangDikerjakanPotSiku');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailBarangDikerjakanPotSiku');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailBarangDikerjakanPotSiku');
    }

    public function replicate(AuthUser $authUser, DetailBarangDikerjakanPotSiku $detailBarangDikerjakanPotSiku): bool
    {
        return $authUser->can('Replicate:DetailBarangDikerjakanPotSiku');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailBarangDikerjakanPotSiku');
    }

}