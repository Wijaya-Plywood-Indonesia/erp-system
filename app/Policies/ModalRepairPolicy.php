<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ModalRepair;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModalRepairPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ModalRepair');
    }

    public function view(AuthUser $authUser, ModalRepair $modalRepair): bool
    {
        return $authUser->can('View:ModalRepair');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ModalRepair');
    }

    public function update(AuthUser $authUser, ModalRepair $modalRepair): bool
    {
        return $authUser->can('Update:ModalRepair');
    }

    public function delete(AuthUser $authUser, ModalRepair $modalRepair): bool
    {
        return $authUser->can('Delete:ModalRepair');
    }

    public function restore(AuthUser $authUser, ModalRepair $modalRepair): bool
    {
        return $authUser->can('Restore:ModalRepair');
    }

    public function forceDelete(AuthUser $authUser, ModalRepair $modalRepair): bool
    {
        return $authUser->can('ForceDelete:ModalRepair');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ModalRepair');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ModalRepair');
    }

    public function replicate(AuthUser $authUser, ModalRepair $modalRepair): bool
    {
        return $authUser->can('Replicate:ModalRepair');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ModalRepair');
    }

}