<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\JurnalTiga;
use Illuminate\Auth\Access\HandlesAuthorization;

class JurnalTigaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JurnalTiga');
    }

    public function view(AuthUser $authUser, JurnalTiga $jurnalTiga): bool
    {
        return $authUser->can('View:JurnalTiga');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JurnalTiga');
    }

    public function update(AuthUser $authUser, JurnalTiga $jurnalTiga): bool
    {
        return $authUser->can('Update:JurnalTiga');
    }

    public function delete(AuthUser $authUser, JurnalTiga $jurnalTiga): bool
    {
        return $authUser->can('Delete:JurnalTiga');
    }

    public function restore(AuthUser $authUser, JurnalTiga $jurnalTiga): bool
    {
        return $authUser->can('Restore:JurnalTiga');
    }

    public function forceDelete(AuthUser $authUser, JurnalTiga $jurnalTiga): bool
    {
        return $authUser->can('ForceDelete:JurnalTiga');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JurnalTiga');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JurnalTiga');
    }

    public function replicate(AuthUser $authUser, JurnalTiga $jurnalTiga): bool
    {
        return $authUser->can('Replicate:JurnalTiga');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JurnalTiga');
    }

}