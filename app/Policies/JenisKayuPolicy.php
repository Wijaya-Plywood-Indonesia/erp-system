<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\JenisKayu;
use Illuminate\Auth\Access\HandlesAuthorization;

class JenisKayuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JenisKayu');
    }

    public function view(AuthUser $authUser, JenisKayu $jenisKayu): bool
    {
        return $authUser->can('View:JenisKayu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JenisKayu');
    }

    public function update(AuthUser $authUser, JenisKayu $jenisKayu): bool
    {
        return $authUser->can('Update:JenisKayu');
    }

    public function delete(AuthUser $authUser, JenisKayu $jenisKayu): bool
    {
        return $authUser->can('Delete:JenisKayu');
    }

    public function restore(AuthUser $authUser, JenisKayu $jenisKayu): bool
    {
        return $authUser->can('Restore:JenisKayu');
    }

    public function forceDelete(AuthUser $authUser, JenisKayu $jenisKayu): bool
    {
        return $authUser->can('ForceDelete:JenisKayu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JenisKayu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JenisKayu');
    }

    public function replicate(AuthUser $authUser, JenisKayu $jenisKayu): bool
    {
        return $authUser->can('Replicate:JenisKayu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JenisKayu');
    }

}