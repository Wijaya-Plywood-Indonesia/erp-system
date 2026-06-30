<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiRepair;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiRepairPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiRepair');
    }

    public function view(AuthUser $authUser, ValidasiRepair $validasiRepair): bool
    {
        return $authUser->can('View:ValidasiRepair');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiRepair');
    }

    public function update(AuthUser $authUser, ValidasiRepair $validasiRepair): bool
    {
        return $authUser->can('Update:ValidasiRepair');
    }

    public function delete(AuthUser $authUser, ValidasiRepair $validasiRepair): bool
    {
        return $authUser->can('Delete:ValidasiRepair');
    }

    public function restore(AuthUser $authUser, ValidasiRepair $validasiRepair): bool
    {
        return $authUser->can('Restore:ValidasiRepair');
    }

    public function forceDelete(AuthUser $authUser, ValidasiRepair $validasiRepair): bool
    {
        return $authUser->can('ForceDelete:ValidasiRepair');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiRepair');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiRepair');
    }

    public function replicate(AuthUser $authUser, ValidasiRepair $validasiRepair): bool
    {
        return $authUser->can('Replicate:ValidasiRepair');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiRepair');
    }

}