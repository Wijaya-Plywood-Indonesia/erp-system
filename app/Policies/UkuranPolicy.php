<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Ukuran;
use Illuminate\Auth\Access\HandlesAuthorization;

class UkuranPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Ukuran');
    }

    public function view(AuthUser $authUser, Ukuran $ukuran): bool
    {
        return $authUser->can('View:Ukuran');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Ukuran');
    }

    public function update(AuthUser $authUser, Ukuran $ukuran): bool
    {
        return $authUser->can('Update:Ukuran');
    }

    public function delete(AuthUser $authUser, Ukuran $ukuran): bool
    {
        return $authUser->can('Delete:Ukuran');
    }

    public function restore(AuthUser $authUser, Ukuran $ukuran): bool
    {
        return $authUser->can('Restore:Ukuran');
    }

    public function forceDelete(AuthUser $authUser, Ukuran $ukuran): bool
    {
        return $authUser->can('ForceDelete:Ukuran');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Ukuran');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Ukuran');
    }

    public function replicate(AuthUser $authUser, Ukuran $ukuran): bool
    {
        return $authUser->can('Replicate:Ukuran');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Ukuran');
    }

}