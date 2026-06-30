<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HppLogHarian;
use Illuminate\Auth\Access\HandlesAuthorization;

class HppLogHarianPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HppLogHarian');
    }

    public function view(AuthUser $authUser, HppLogHarian $hppLogHarian): bool
    {
        return $authUser->can('View:HppLogHarian');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HppLogHarian');
    }

    public function update(AuthUser $authUser, HppLogHarian $hppLogHarian): bool
    {
        return $authUser->can('Update:HppLogHarian');
    }

    public function delete(AuthUser $authUser, HppLogHarian $hppLogHarian): bool
    {
        return $authUser->can('Delete:HppLogHarian');
    }

    public function restore(AuthUser $authUser, HppLogHarian $hppLogHarian): bool
    {
        return $authUser->can('Restore:HppLogHarian');
    }

    public function forceDelete(AuthUser $authUser, HppLogHarian $hppLogHarian): bool
    {
        return $authUser->can('ForceDelete:HppLogHarian');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HppLogHarian');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HppLogHarian');
    }

    public function replicate(AuthUser $authUser, HppLogHarian $hppLogHarian): bool
    {
        return $authUser->can('Replicate:HppLogHarian');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HppLogHarian');
    }

}