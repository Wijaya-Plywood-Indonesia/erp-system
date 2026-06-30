<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DokumenKayu;
use Illuminate\Auth\Access\HandlesAuthorization;

class DokumenKayuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DokumenKayu');
    }

    public function view(AuthUser $authUser, DokumenKayu $dokumenKayu): bool
    {
        return $authUser->can('View:DokumenKayu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DokumenKayu');
    }

    public function update(AuthUser $authUser, DokumenKayu $dokumenKayu): bool
    {
        return $authUser->can('Update:DokumenKayu');
    }

    public function delete(AuthUser $authUser, DokumenKayu $dokumenKayu): bool
    {
        return $authUser->can('Delete:DokumenKayu');
    }

    public function restore(AuthUser $authUser, DokumenKayu $dokumenKayu): bool
    {
        return $authUser->can('Restore:DokumenKayu');
    }

    public function forceDelete(AuthUser $authUser, DokumenKayu $dokumenKayu): bool
    {
        return $authUser->can('ForceDelete:DokumenKayu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DokumenKayu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DokumenKayu');
    }

    public function replicate(AuthUser $authUser, DokumenKayu $dokumenKayu): bool
    {
        return $authUser->can('Replicate:DokumenKayu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DokumenKayu');
    }

}