<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiPilihPlywood;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiPilihPlywoodPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiPilihPlywood');
    }

    public function view(AuthUser $authUser, PegawaiPilihPlywood $pegawaiPilihPlywood): bool
    {
        return $authUser->can('View:PegawaiPilihPlywood');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiPilihPlywood');
    }

    public function update(AuthUser $authUser, PegawaiPilihPlywood $pegawaiPilihPlywood): bool
    {
        return $authUser->can('Update:PegawaiPilihPlywood');
    }

    public function delete(AuthUser $authUser, PegawaiPilihPlywood $pegawaiPilihPlywood): bool
    {
        return $authUser->can('Delete:PegawaiPilihPlywood');
    }

    public function restore(AuthUser $authUser, PegawaiPilihPlywood $pegawaiPilihPlywood): bool
    {
        return $authUser->can('Restore:PegawaiPilihPlywood');
    }

    public function forceDelete(AuthUser $authUser, PegawaiPilihPlywood $pegawaiPilihPlywood): bool
    {
        return $authUser->can('ForceDelete:PegawaiPilihPlywood');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiPilihPlywood');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiPilihPlywood');
    }

    public function replicate(AuthUser $authUser, PegawaiPilihPlywood $pegawaiPilihPlywood): bool
    {
        return $authUser->can('Replicate:PegawaiPilihPlywood');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiPilihPlywood');
    }

}