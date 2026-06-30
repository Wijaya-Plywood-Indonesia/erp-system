<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HasilRepair;
use Illuminate\Auth\Access\HandlesAuthorization;

class HasilRepairPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HasilRepair');
    }

    public function view(AuthUser $authUser, HasilRepair $hasilRepair): bool
    {
        return $authUser->can('View:HasilRepair');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HasilRepair');
    }

    public function update(AuthUser $authUser, HasilRepair $hasilRepair): bool
    {
        return $authUser->can('Update:HasilRepair');
    }

    public function delete(AuthUser $authUser, HasilRepair $hasilRepair): bool
    {
        return $authUser->can('Delete:HasilRepair');
    }

    public function restore(AuthUser $authUser, HasilRepair $hasilRepair): bool
    {
        return $authUser->can('Restore:HasilRepair');
    }

    public function forceDelete(AuthUser $authUser, HasilRepair $hasilRepair): bool
    {
        return $authUser->can('ForceDelete:HasilRepair');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HasilRepair');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HasilRepair');
    }

    public function replicate(AuthUser $authUser, HasilRepair $hasilRepair): bool
    {
        return $authUser->can('Replicate:HasilRepair');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HasilRepair');
    }

}