<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\IndukAkun;
use Illuminate\Auth\Access\HandlesAuthorization;

class IndukAkunPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IndukAkun');
    }

    public function view(AuthUser $authUser, IndukAkun $indukAkun): bool
    {
        return $authUser->can('View:IndukAkun');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IndukAkun');
    }

    public function update(AuthUser $authUser, IndukAkun $indukAkun): bool
    {
        return $authUser->can('Update:IndukAkun');
    }

    public function delete(AuthUser $authUser, IndukAkun $indukAkun): bool
    {
        return $authUser->can('Delete:IndukAkun');
    }

    public function restore(AuthUser $authUser, IndukAkun $indukAkun): bool
    {
        return $authUser->can('Restore:IndukAkun');
    }

    public function forceDelete(AuthUser $authUser, IndukAkun $indukAkun): bool
    {
        return $authUser->can('ForceDelete:IndukAkun');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:IndukAkun');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:IndukAkun');
    }

    public function replicate(AuthUser $authUser, IndukAkun $indukAkun): bool
    {
        return $authUser->can('Replicate:IndukAkun');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:IndukAkun');
    }

}