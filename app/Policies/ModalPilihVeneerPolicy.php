<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ModalPilihVeneer;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModalPilihVeneerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ModalPilihVeneer');
    }

    public function view(AuthUser $authUser, ModalPilihVeneer $modalPilihVeneer): bool
    {
        return $authUser->can('View:ModalPilihVeneer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ModalPilihVeneer');
    }

    public function update(AuthUser $authUser, ModalPilihVeneer $modalPilihVeneer): bool
    {
        return $authUser->can('Update:ModalPilihVeneer');
    }

    public function delete(AuthUser $authUser, ModalPilihVeneer $modalPilihVeneer): bool
    {
        return $authUser->can('Delete:ModalPilihVeneer');
    }

    public function restore(AuthUser $authUser, ModalPilihVeneer $modalPilihVeneer): bool
    {
        return $authUser->can('Restore:ModalPilihVeneer');
    }

    public function forceDelete(AuthUser $authUser, ModalPilihVeneer $modalPilihVeneer): bool
    {
        return $authUser->can('ForceDelete:ModalPilihVeneer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ModalPilihVeneer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ModalPilihVeneer');
    }

    public function replicate(AuthUser $authUser, ModalPilihVeneer $modalPilihVeneer): bool
    {
        return $authUser->can('Replicate:ModalPilihVeneer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ModalPilihVeneer');
    }

}