<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TotalSolasi;
use Illuminate\Auth\Access\HandlesAuthorization;

class TotalSolasiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TotalSolasi');
    }

    public function view(AuthUser $authUser, TotalSolasi $totalSolasi): bool
    {
        return $authUser->can('View:TotalSolasi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TotalSolasi');
    }

    public function update(AuthUser $authUser, TotalSolasi $totalSolasi): bool
    {
        return $authUser->can('Update:TotalSolasi');
    }

    public function delete(AuthUser $authUser, TotalSolasi $totalSolasi): bool
    {
        return $authUser->can('Delete:TotalSolasi');
    }

    public function restore(AuthUser $authUser, TotalSolasi $totalSolasi): bool
    {
        return $authUser->can('Restore:TotalSolasi');
    }

    public function forceDelete(AuthUser $authUser, TotalSolasi $totalSolasi): bool
    {
        return $authUser->can('ForceDelete:TotalSolasi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TotalSolasi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TotalSolasi');
    }

    public function replicate(AuthUser $authUser, TotalSolasi $totalSolasi): bool
    {
        return $authUser->can('Replicate:TotalSolasi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TotalSolasi');
    }

}