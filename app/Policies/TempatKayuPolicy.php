<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TempatKayu;
use Illuminate\Auth\Access\HandlesAuthorization;

class TempatKayuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TempatKayu');
    }

    public function view(AuthUser $authUser, TempatKayu $tempatKayu): bool
    {
        return $authUser->can('View:TempatKayu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TempatKayu');
    }

    public function update(AuthUser $authUser, TempatKayu $tempatKayu): bool
    {
        return $authUser->can('Update:TempatKayu');
    }

    public function delete(AuthUser $authUser, TempatKayu $tempatKayu): bool
    {
        return $authUser->can('Delete:TempatKayu');
    }

    public function restore(AuthUser $authUser, TempatKayu $tempatKayu): bool
    {
        return $authUser->can('Restore:TempatKayu');
    }

    public function forceDelete(AuthUser $authUser, TempatKayu $tempatKayu): bool
    {
        return $authUser->can('ForceDelete:TempatKayu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TempatKayu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TempatKayu');
    }

    public function replicate(AuthUser $authUser, TempatKayu $tempatKayu): bool
    {
        return $authUser->can('Replicate:TempatKayu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TempatKayu');
    }

}