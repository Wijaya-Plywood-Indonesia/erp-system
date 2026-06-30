<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailHasilPaletRotary;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailHasilPaletRotaryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailHasilPaletRotary');
    }

    public function view(AuthUser $authUser, DetailHasilPaletRotary $detailHasilPaletRotary): bool
    {
        return $authUser->can('View:DetailHasilPaletRotary');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailHasilPaletRotary');
    }

    public function update(AuthUser $authUser, DetailHasilPaletRotary $detailHasilPaletRotary): bool
    {
        return $authUser->can('Update:DetailHasilPaletRotary');
    }

    public function delete(AuthUser $authUser, DetailHasilPaletRotary $detailHasilPaletRotary): bool
    {
        return $authUser->can('Delete:DetailHasilPaletRotary');
    }

    public function restore(AuthUser $authUser, DetailHasilPaletRotary $detailHasilPaletRotary): bool
    {
        return $authUser->can('Restore:DetailHasilPaletRotary');
    }

    public function forceDelete(AuthUser $authUser, DetailHasilPaletRotary $detailHasilPaletRotary): bool
    {
        return $authUser->can('ForceDelete:DetailHasilPaletRotary');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailHasilPaletRotary');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailHasilPaletRotary');
    }

    public function replicate(AuthUser $authUser, DetailHasilPaletRotary $detailHasilPaletRotary): bool
    {
        return $authUser->can('Replicate:DetailHasilPaletRotary');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailHasilPaletRotary');
    }

}