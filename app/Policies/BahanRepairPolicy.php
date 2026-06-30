<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BahanRepair;
use Illuminate\Auth\Access\HandlesAuthorization;

class BahanRepairPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BahanRepair');
    }

    public function view(AuthUser $authUser, BahanRepair $bahanRepair): bool
    {
        return $authUser->can('View:BahanRepair');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BahanRepair');
    }

    public function update(AuthUser $authUser, BahanRepair $bahanRepair): bool
    {
        return $authUser->can('Update:BahanRepair');
    }

    public function delete(AuthUser $authUser, BahanRepair $bahanRepair): bool
    {
        return $authUser->can('Delete:BahanRepair');
    }

    public function restore(AuthUser $authUser, BahanRepair $bahanRepair): bool
    {
        return $authUser->can('Restore:BahanRepair');
    }

    public function forceDelete(AuthUser $authUser, BahanRepair $bahanRepair): bool
    {
        return $authUser->can('ForceDelete:BahanRepair');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BahanRepair');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BahanRepair');
    }

    public function replicate(AuthUser $authUser, BahanRepair $bahanRepair): bool
    {
        return $authUser->can('Replicate:BahanRepair');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BahanRepair');
    }

}