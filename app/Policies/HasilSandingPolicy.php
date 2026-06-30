<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HasilSanding;
use Illuminate\Auth\Access\HandlesAuthorization;

class HasilSandingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HasilSanding');
    }

    public function view(AuthUser $authUser, HasilSanding $hasilSanding): bool
    {
        return $authUser->can('View:HasilSanding');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HasilSanding');
    }

    public function update(AuthUser $authUser, HasilSanding $hasilSanding): bool
    {
        return $authUser->can('Update:HasilSanding');
    }

    public function delete(AuthUser $authUser, HasilSanding $hasilSanding): bool
    {
        return $authUser->can('Delete:HasilSanding');
    }

    public function restore(AuthUser $authUser, HasilSanding $hasilSanding): bool
    {
        return $authUser->can('Restore:HasilSanding');
    }

    public function forceDelete(AuthUser $authUser, HasilSanding $hasilSanding): bool
    {
        return $authUser->can('ForceDelete:HasilSanding');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HasilSanding');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HasilSanding');
    }

    public function replicate(AuthUser $authUser, HasilSanding $hasilSanding): bool
    {
        return $authUser->can('Replicate:HasilSanding');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HasilSanding');
    }

}