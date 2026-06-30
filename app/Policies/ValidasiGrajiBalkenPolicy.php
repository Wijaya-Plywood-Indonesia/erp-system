<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiGrajiBalken;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiGrajiBalkenPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiGrajiBalken');
    }

    public function view(AuthUser $authUser, ValidasiGrajiBalken $validasiGrajiBalken): bool
    {
        return $authUser->can('View:ValidasiGrajiBalken');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiGrajiBalken');
    }

    public function update(AuthUser $authUser, ValidasiGrajiBalken $validasiGrajiBalken): bool
    {
        return $authUser->can('Update:ValidasiGrajiBalken');
    }

    public function delete(AuthUser $authUser, ValidasiGrajiBalken $validasiGrajiBalken): bool
    {
        return $authUser->can('Delete:ValidasiGrajiBalken');
    }

    public function restore(AuthUser $authUser, ValidasiGrajiBalken $validasiGrajiBalken): bool
    {
        return $authUser->can('Restore:ValidasiGrajiBalken');
    }

    public function forceDelete(AuthUser $authUser, ValidasiGrajiBalken $validasiGrajiBalken): bool
    {
        return $authUser->can('ForceDelete:ValidasiGrajiBalken');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiGrajiBalken');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiGrajiBalken');
    }

    public function replicate(AuthUser $authUser, ValidasiGrajiBalken $validasiGrajiBalken): bool
    {
        return $authUser->can('Replicate:ValidasiGrajiBalken');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiGrajiBalken');
    }

}