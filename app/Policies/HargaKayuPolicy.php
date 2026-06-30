<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HargaKayu;
use Illuminate\Auth\Access\HandlesAuthorization;

class HargaKayuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HargaKayu');
    }

    public function view(AuthUser $authUser, HargaKayu $hargaKayu): bool
    {
        return $authUser->can('View:HargaKayu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HargaKayu');
    }

    public function update(AuthUser $authUser, HargaKayu $hargaKayu): bool
    {
        return $authUser->can('Update:HargaKayu');
    }

    public function delete(AuthUser $authUser, HargaKayu $hargaKayu): bool
    {
        return $authUser->can('Delete:HargaKayu');
    }

    public function restore(AuthUser $authUser, HargaKayu $hargaKayu): bool
    {
        return $authUser->can('Restore:HargaKayu');
    }

    public function forceDelete(AuthUser $authUser, HargaKayu $hargaKayu): bool
    {
        return $authUser->can('ForceDelete:HargaKayu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HargaKayu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HargaKayu');
    }

    public function replicate(AuthUser $authUser, HargaKayu $hargaKayu): bool
    {
        return $authUser->can('Replicate:HargaKayu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HargaKayu');
    }

}