<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiDempul;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiDempulPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiDempul');
    }

    public function view(AuthUser $authUser, ValidasiDempul $validasiDempul): bool
    {
        return $authUser->can('View:ValidasiDempul');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiDempul');
    }

    public function update(AuthUser $authUser, ValidasiDempul $validasiDempul): bool
    {
        return $authUser->can('Update:ValidasiDempul');
    }

    public function delete(AuthUser $authUser, ValidasiDempul $validasiDempul): bool
    {
        return $authUser->can('Delete:ValidasiDempul');
    }

    public function restore(AuthUser $authUser, ValidasiDempul $validasiDempul): bool
    {
        return $authUser->can('Restore:ValidasiDempul');
    }

    public function forceDelete(AuthUser $authUser, ValidasiDempul $validasiDempul): bool
    {
        return $authUser->can('ForceDelete:ValidasiDempul');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiDempul');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiDempul');
    }

    public function replicate(AuthUser $authUser, ValidasiDempul $validasiDempul): bool
    {
        return $authUser->can('Replicate:ValidasiDempul');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiDempul');
    }

}