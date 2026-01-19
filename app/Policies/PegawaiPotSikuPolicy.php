<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiPotSiku;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiPotSikuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiPotSiku');
    }

    public function view(AuthUser $authUser, PegawaiPotSiku $pegawaiPotSiku): bool
    {
        return $authUser->can('View:PegawaiPotSiku');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiPotSiku');
    }

    public function update(AuthUser $authUser, PegawaiPotSiku $pegawaiPotSiku): bool
    {
        return $authUser->can('Update:PegawaiPotSiku');
    }

    public function delete(AuthUser $authUser, PegawaiPotSiku $pegawaiPotSiku): bool
    {
        return $authUser->can('Delete:PegawaiPotSiku');
    }

    public function restore(AuthUser $authUser, PegawaiPotSiku $pegawaiPotSiku): bool
    {
        return $authUser->can('Restore:PegawaiPotSiku');
    }

    public function forceDelete(AuthUser $authUser, PegawaiPotSiku $pegawaiPotSiku): bool
    {
        return $authUser->can('ForceDelete:PegawaiPotSiku');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiPotSiku');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiPotSiku');
    }

    public function replicate(AuthUser $authUser, PegawaiPotSiku $pegawaiPotSiku): bool
    {
        return $authUser->can('Replicate:PegawaiPotSiku');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiPotSiku');
    }

}