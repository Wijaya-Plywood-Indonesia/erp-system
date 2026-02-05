<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Jurnal1st;
use Illuminate\Auth\Access\HandlesAuthorization;

class Jurnal1stPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Jurnal1st');
    }

    public function view(AuthUser $authUser, Jurnal1st $jurnal1st): bool
    {
        return $authUser->can('View:Jurnal1st');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Jurnal1st');
    }

    public function update(AuthUser $authUser, Jurnal1st $jurnal1st): bool
    {
        return $authUser->can('Update:Jurnal1st');
    }

    public function delete(AuthUser $authUser, Jurnal1st $jurnal1st): bool
    {
        return $authUser->can('Delete:Jurnal1st');
    }

    public function restore(AuthUser $authUser, Jurnal1st $jurnal1st): bool
    {
        return $authUser->can('Restore:Jurnal1st');
    }

    public function forceDelete(AuthUser $authUser, Jurnal1st $jurnal1st): bool
    {
        return $authUser->can('ForceDelete:Jurnal1st');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Jurnal1st');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Jurnal1st');
    }

    public function replicate(AuthUser $authUser, Jurnal1st $jurnal1st): bool
    {
        return $authUser->can('Replicate:Jurnal1st');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Jurnal1st');
    }

}