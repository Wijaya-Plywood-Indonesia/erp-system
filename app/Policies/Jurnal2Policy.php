<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Jurnal2;
use Illuminate\Auth\Access\HandlesAuthorization;

class Jurnal2Policy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Jurnal2');
    }

    public function view(AuthUser $authUser, Jurnal2 $jurnal2): bool
    {
        return $authUser->can('View:Jurnal2');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Jurnal2');
    }

    public function update(AuthUser $authUser, Jurnal2 $jurnal2): bool
    {
        return $authUser->can('Update:Jurnal2');
    }

    public function delete(AuthUser $authUser, Jurnal2 $jurnal2): bool
    {
        return $authUser->can('Delete:Jurnal2');
    }

    public function restore(AuthUser $authUser, Jurnal2 $jurnal2): bool
    {
        return $authUser->can('Restore:Jurnal2');
    }

    public function forceDelete(AuthUser $authUser, Jurnal2 $jurnal2): bool
    {
        return $authUser->can('ForceDelete:Jurnal2');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Jurnal2');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Jurnal2');
    }

    public function replicate(AuthUser $authUser, Jurnal2 $jurnal2): bool
    {
        return $authUser->can('Replicate:Jurnal2');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Jurnal2');
    }

}