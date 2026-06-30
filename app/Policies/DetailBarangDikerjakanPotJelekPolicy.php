<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailBarangDikerjakanPotJelek;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailBarangDikerjakanPotJelekPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailBarangDikerjakanPotJelek');
    }

    public function view(AuthUser $authUser, DetailBarangDikerjakanPotJelek $detailBarangDikerjakanPotJelek): bool
    {
        return $authUser->can('View:DetailBarangDikerjakanPotJelek');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailBarangDikerjakanPotJelek');
    }

    public function update(AuthUser $authUser, DetailBarangDikerjakanPotJelek $detailBarangDikerjakanPotJelek): bool
    {
        return $authUser->can('Update:DetailBarangDikerjakanPotJelek');
    }

    public function delete(AuthUser $authUser, DetailBarangDikerjakanPotJelek $detailBarangDikerjakanPotJelek): bool
    {
        return $authUser->can('Delete:DetailBarangDikerjakanPotJelek');
    }

    public function restore(AuthUser $authUser, DetailBarangDikerjakanPotJelek $detailBarangDikerjakanPotJelek): bool
    {
        return $authUser->can('Restore:DetailBarangDikerjakanPotJelek');
    }

    public function forceDelete(AuthUser $authUser, DetailBarangDikerjakanPotJelek $detailBarangDikerjakanPotJelek): bool
    {
        return $authUser->can('ForceDelete:DetailBarangDikerjakanPotJelek');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailBarangDikerjakanPotJelek');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailBarangDikerjakanPotJelek');
    }

    public function replicate(AuthUser $authUser, DetailBarangDikerjakanPotJelek $detailBarangDikerjakanPotJelek): bool
    {
        return $authUser->can('Replicate:DetailBarangDikerjakanPotJelek');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailBarangDikerjakanPotJelek');
    }

}