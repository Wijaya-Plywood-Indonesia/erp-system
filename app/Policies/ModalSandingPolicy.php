<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ModalSanding;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModalSandingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ModalSanding');
    }

    public function view(AuthUser $authUser, ModalSanding $modalSanding): bool
    {
        return $authUser->can('View:ModalSanding');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ModalSanding');
    }

    public function update(AuthUser $authUser, ModalSanding $modalSanding): bool
    {
        return $authUser->can('Update:ModalSanding');
    }

    public function delete(AuthUser $authUser, ModalSanding $modalSanding): bool
    {
        return $authUser->can('Delete:ModalSanding');
    }

    public function restore(AuthUser $authUser, ModalSanding $modalSanding): bool
    {
        return $authUser->can('Restore:ModalSanding');
    }

    public function forceDelete(AuthUser $authUser, ModalSanding $modalSanding): bool
    {
        return $authUser->can('ForceDelete:ModalSanding');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ModalSanding');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ModalSanding');
    }

    public function replicate(AuthUser $authUser, ModalSanding $modalSanding): bool
    {
        return $authUser->can('Replicate:ModalSanding');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ModalSanding');
    }

}