<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiNyusup;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiNyusupPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiNyusup');
    }

    public function view(AuthUser $authUser, ValidasiNyusup $validasiNyusup): bool
    {
        return $authUser->can('View:ValidasiNyusup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiNyusup');
    }

    public function update(AuthUser $authUser, ValidasiNyusup $validasiNyusup): bool
    {
        return $authUser->can('Update:ValidasiNyusup');
    }

    public function delete(AuthUser $authUser, ValidasiNyusup $validasiNyusup): bool
    {
        return $authUser->can('Delete:ValidasiNyusup');
    }

    public function restore(AuthUser $authUser, ValidasiNyusup $validasiNyusup): bool
    {
        return $authUser->can('Restore:ValidasiNyusup');
    }

    public function forceDelete(AuthUser $authUser, ValidasiNyusup $validasiNyusup): bool
    {
        return $authUser->can('ForceDelete:ValidasiNyusup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiNyusup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiNyusup');
    }

    public function replicate(AuthUser $authUser, ValidasiNyusup $validasiNyusup): bool
    {
        return $authUser->can('Replicate:ValidasiNyusup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiNyusup');
    }

}