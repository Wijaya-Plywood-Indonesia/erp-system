<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiPotSiku;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiPotSikuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiPotSiku');
    }

    public function view(AuthUser $authUser, ValidasiPotSiku $validasiPotSiku): bool
    {
        return $authUser->can('View:ValidasiPotSiku');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiPotSiku');
    }

    public function update(AuthUser $authUser, ValidasiPotSiku $validasiPotSiku): bool
    {
        return $authUser->can('Update:ValidasiPotSiku');
    }

    public function delete(AuthUser $authUser, ValidasiPotSiku $validasiPotSiku): bool
    {
        return $authUser->can('Delete:ValidasiPotSiku');
    }

    public function restore(AuthUser $authUser, ValidasiPotSiku $validasiPotSiku): bool
    {
        return $authUser->can('Restore:ValidasiPotSiku');
    }

    public function forceDelete(AuthUser $authUser, ValidasiPotSiku $validasiPotSiku): bool
    {
        return $authUser->can('ForceDelete:ValidasiPotSiku');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiPotSiku');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiPotSiku');
    }

    public function replicate(AuthUser $authUser, ValidasiPotSiku $validasiPotSiku): bool
    {
        return $authUser->can('Replicate:ValidasiPotSiku');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiPotSiku');
    }

}