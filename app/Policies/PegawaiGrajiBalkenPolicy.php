<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiGrajiBalken;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiGrajiBalkenPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiGrajiBalken');
    }

    public function view(AuthUser $authUser, PegawaiGrajiBalken $pegawaiGrajiBalken): bool
    {
        return $authUser->can('View:PegawaiGrajiBalken');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiGrajiBalken');
    }

    public function update(AuthUser $authUser, PegawaiGrajiBalken $pegawaiGrajiBalken): bool
    {
        return $authUser->can('Update:PegawaiGrajiBalken');
    }

    public function delete(AuthUser $authUser, PegawaiGrajiBalken $pegawaiGrajiBalken): bool
    {
        return $authUser->can('Delete:PegawaiGrajiBalken');
    }

    public function restore(AuthUser $authUser, PegawaiGrajiBalken $pegawaiGrajiBalken): bool
    {
        return $authUser->can('Restore:PegawaiGrajiBalken');
    }

    public function forceDelete(AuthUser $authUser, PegawaiGrajiBalken $pegawaiGrajiBalken): bool
    {
        return $authUser->can('ForceDelete:PegawaiGrajiBalken');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiGrajiBalken');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiGrajiBalken');
    }

    public function replicate(AuthUser $authUser, PegawaiGrajiBalken $pegawaiGrajiBalken): bool
    {
        return $authUser->can('Replicate:PegawaiGrajiBalken');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiGrajiBalken');
    }

}