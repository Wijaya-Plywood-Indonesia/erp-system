<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Komposisi;
use Illuminate\Auth\Access\HandlesAuthorization;

class KomposisiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Komposisi');
    }

    public function view(AuthUser $authUser, Komposisi $komposisi): bool
    {
        return $authUser->can('View:Komposisi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Komposisi');
    }

    public function update(AuthUser $authUser, Komposisi $komposisi): bool
    {
        return $authUser->can('Update:Komposisi');
    }

    public function delete(AuthUser $authUser, Komposisi $komposisi): bool
    {
        return $authUser->can('Delete:Komposisi');
    }

    public function restore(AuthUser $authUser, Komposisi $komposisi): bool
    {
        return $authUser->can('Restore:Komposisi');
    }

    public function forceDelete(AuthUser $authUser, Komposisi $komposisi): bool
    {
        return $authUser->can('ForceDelete:Komposisi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Komposisi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Komposisi');
    }

    public function replicate(AuthUser $authUser, Komposisi $komposisi): bool
    {
        return $authUser->can('Replicate:Komposisi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Komposisi');
    }

}