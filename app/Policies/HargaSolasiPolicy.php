<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HargaSolasi;
use Illuminate\Auth\Access\HandlesAuthorization;

class HargaSolasiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HargaSolasi');
    }

    public function view(AuthUser $authUser, HargaSolasi $hargaSolasi): bool
    {
        return $authUser->can('View:HargaSolasi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HargaSolasi');
    }

    public function update(AuthUser $authUser, HargaSolasi $hargaSolasi): bool
    {
        return $authUser->can('Update:HargaSolasi');
    }

    public function delete(AuthUser $authUser, HargaSolasi $hargaSolasi): bool
    {
        return $authUser->can('Delete:HargaSolasi');
    }

    public function restore(AuthUser $authUser, HargaSolasi $hargaSolasi): bool
    {
        return $authUser->can('Restore:HargaSolasi');
    }

    public function forceDelete(AuthUser $authUser, HargaSolasi $hargaSolasi): bool
    {
        return $authUser->can('ForceDelete:HargaSolasi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HargaSolasi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HargaSolasi');
    }

    public function replicate(AuthUser $authUser, HargaSolasi $hargaSolasi): bool
    {
        return $authUser->can('Replicate:HargaSolasi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HargaSolasi');
    }

}