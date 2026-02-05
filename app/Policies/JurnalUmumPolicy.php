<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\JurnalUmum;
use Illuminate\Auth\Access\HandlesAuthorization;

class JurnalUmumPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JurnalUmum');
    }

    public function view(AuthUser $authUser, JurnalUmum $jurnalUmum): bool
    {
        return $authUser->can('View:JurnalUmum');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JurnalUmum');
    }

    public function update(AuthUser $authUser, JurnalUmum $jurnalUmum): bool
    {
        return $authUser->can('Update:JurnalUmum');
    }

    public function delete(AuthUser $authUser, JurnalUmum $jurnalUmum): bool
    {
        return $authUser->can('Delete:JurnalUmum');
    }

    public function restore(AuthUser $authUser, JurnalUmum $jurnalUmum): bool
    {
        return $authUser->can('Restore:JurnalUmum');
    }

    public function forceDelete(AuthUser $authUser, JurnalUmum $jurnalUmum): bool
    {
        return $authUser->can('ForceDelete:JurnalUmum');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JurnalUmum');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JurnalUmum');
    }

    public function replicate(AuthUser $authUser, JurnalUmum $jurnalUmum): bool
    {
        return $authUser->can('Replicate:JurnalUmum');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JurnalUmum');
    }

}