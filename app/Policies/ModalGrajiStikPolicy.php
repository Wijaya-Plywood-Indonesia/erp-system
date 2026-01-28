<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ModalGrajiStik;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModalGrajiStikPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ModalGrajiStik');
    }

    public function view(AuthUser $authUser, ModalGrajiStik $modalGrajiStik): bool
    {
        return $authUser->can('View:ModalGrajiStik');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ModalGrajiStik');
    }

    public function update(AuthUser $authUser, ModalGrajiStik $modalGrajiStik): bool
    {
        return $authUser->can('Update:ModalGrajiStik');
    }

    public function delete(AuthUser $authUser, ModalGrajiStik $modalGrajiStik): bool
    {
        return $authUser->can('Delete:ModalGrajiStik');
    }

    public function restore(AuthUser $authUser, ModalGrajiStik $modalGrajiStik): bool
    {
        return $authUser->can('Restore:ModalGrajiStik');
    }

    public function forceDelete(AuthUser $authUser, ModalGrajiStik $modalGrajiStik): bool
    {
        return $authUser->can('ForceDelete:ModalGrajiStik');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ModalGrajiStik');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ModalGrajiStik');
    }

    public function replicate(AuthUser $authUser, ModalGrajiStik $modalGrajiStik): bool
    {
        return $authUser->can('Replicate:ModalGrajiStik');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ModalGrajiStik');
    }

}