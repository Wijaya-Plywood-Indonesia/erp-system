<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiKedi;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiKediPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiKedi');
    }

    public function view(AuthUser $authUser, ValidasiKedi $validasiKedi): bool
    {
        return $authUser->can('View:ValidasiKedi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiKedi');
    }

    public function update(AuthUser $authUser, ValidasiKedi $validasiKedi): bool
    {
        return $authUser->can('Update:ValidasiKedi');
    }

    public function delete(AuthUser $authUser, ValidasiKedi $validasiKedi): bool
    {
        return $authUser->can('Delete:ValidasiKedi');
    }

    public function restore(AuthUser $authUser, ValidasiKedi $validasiKedi): bool
    {
        return $authUser->can('Restore:ValidasiKedi');
    }

    public function forceDelete(AuthUser $authUser, ValidasiKedi $validasiKedi): bool
    {
        return $authUser->can('ForceDelete:ValidasiKedi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiKedi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiKedi');
    }

    public function replicate(AuthUser $authUser, ValidasiKedi $validasiKedi): bool
    {
        return $authUser->can('Replicate:ValidasiKedi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiKedi');
    }

}