<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SubAnakAkun;
use Illuminate\Auth\Access\HandlesAuthorization;

class SubAnakAkunPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SubAnakAkun');
    }

    public function view(AuthUser $authUser, SubAnakAkun $subAnakAkun): bool
    {
        return $authUser->can('View:SubAnakAkun');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SubAnakAkun');
    }

    public function update(AuthUser $authUser, SubAnakAkun $subAnakAkun): bool
    {
        return $authUser->can('Update:SubAnakAkun');
    }

    public function delete(AuthUser $authUser, SubAnakAkun $subAnakAkun): bool
    {
        return $authUser->can('Delete:SubAnakAkun');
    }

    public function restore(AuthUser $authUser, SubAnakAkun $subAnakAkun): bool
    {
        return $authUser->can('Restore:SubAnakAkun');
    }

    public function forceDelete(AuthUser $authUser, SubAnakAkun $subAnakAkun): bool
    {
        return $authUser->can('ForceDelete:SubAnakAkun');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SubAnakAkun');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SubAnakAkun');
    }

    public function replicate(AuthUser $authUser, SubAnakAkun $subAnakAkun): bool
    {
        return $authUser->can('Replicate:SubAnakAkun');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SubAnakAkun');
    }

}