<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\KontrakKerja;
use Illuminate\Auth\Access\HandlesAuthorization;

class KontrakKerjaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:KontrakKerja');
    }

    public function view(AuthUser $authUser, KontrakKerja $kontrakKerja): bool
    {
        return $authUser->can('View:KontrakKerja');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:KontrakKerja');
    }

    public function update(AuthUser $authUser, KontrakKerja $kontrakKerja): bool
    {
        return $authUser->can('Update:KontrakKerja');
    }

    public function delete(AuthUser $authUser, KontrakKerja $kontrakKerja): bool
    {
        return $authUser->can('Delete:KontrakKerja');
    }

    public function restore(AuthUser $authUser, KontrakKerja $kontrakKerja): bool
    {
        return $authUser->can('Restore:KontrakKerja');
    }

    public function forceDelete(AuthUser $authUser, KontrakKerja $kontrakKerja): bool
    {
        return $authUser->can('ForceDelete:KontrakKerja');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:KontrakKerja');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:KontrakKerja');
    }

    public function replicate(AuthUser $authUser, KontrakKerja $kontrakKerja): bool
    {
        return $authUser->can('Replicate:KontrakKerja');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:KontrakKerja');
    }

}