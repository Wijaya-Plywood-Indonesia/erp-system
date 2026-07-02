<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AnakAkun;
use Illuminate\Auth\Access\HandlesAuthorization;

class AnakAkunPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AnakAkun');
    }

    public function view(AuthUser $authUser, AnakAkun $anakAkun): bool
    {
        return $authUser->can('View:AnakAkun');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AnakAkun');
    }

    public function update(AuthUser $authUser, AnakAkun $anakAkun): bool
    {
        return $authUser->can('Update:AnakAkun');
    }

    public function delete(AuthUser $authUser, AnakAkun $anakAkun): bool
    {
        return $authUser->can('Delete:AnakAkun');
    }

    public function restore(AuthUser $authUser, AnakAkun $anakAkun): bool
    {
        return $authUser->can('Restore:AnakAkun');
    }

    public function forceDelete(AuthUser $authUser, AnakAkun $anakAkun): bool
    {
        return $authUser->can('ForceDelete:AnakAkun');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AnakAkun');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AnakAkun');
    }

    public function replicate(AuthUser $authUser, AnakAkun $anakAkun): bool
    {
        return $authUser->can('Replicate:AnakAkun');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AnakAkun');
    }

}