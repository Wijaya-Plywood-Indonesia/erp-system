<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ListPekerjaanMenumpuk;
use Illuminate\Auth\Access\HandlesAuthorization;

class ListPekerjaanMenumpukPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ListPekerjaanMenumpuk');
    }

    public function view(AuthUser $authUser, ListPekerjaanMenumpuk $listPekerjaanMenumpuk): bool
    {
        return $authUser->can('View:ListPekerjaanMenumpuk');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ListPekerjaanMenumpuk');
    }

    public function update(AuthUser $authUser, ListPekerjaanMenumpuk $listPekerjaanMenumpuk): bool
    {
        return $authUser->can('Update:ListPekerjaanMenumpuk');
    }

    public function delete(AuthUser $authUser, ListPekerjaanMenumpuk $listPekerjaanMenumpuk): bool
    {
        return $authUser->can('Delete:ListPekerjaanMenumpuk');
    }

    public function restore(AuthUser $authUser, ListPekerjaanMenumpuk $listPekerjaanMenumpuk): bool
    {
        return $authUser->can('Restore:ListPekerjaanMenumpuk');
    }

    public function forceDelete(AuthUser $authUser, ListPekerjaanMenumpuk $listPekerjaanMenumpuk): bool
    {
        return $authUser->can('ForceDelete:ListPekerjaanMenumpuk');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ListPekerjaanMenumpuk');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ListPekerjaanMenumpuk');
    }

    public function replicate(AuthUser $authUser, ListPekerjaanMenumpuk $listPekerjaanMenumpuk): bool
    {
        return $authUser->can('Replicate:ListPekerjaanMenumpuk');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ListPekerjaanMenumpuk');
    }

}