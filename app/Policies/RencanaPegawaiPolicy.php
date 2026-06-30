<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RencanaPegawai;
use Illuminate\Auth\Access\HandlesAuthorization;

class RencanaPegawaiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RencanaPegawai');
    }

    public function view(AuthUser $authUser, RencanaPegawai $rencanaPegawai): bool
    {
        return $authUser->can('View:RencanaPegawai');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RencanaPegawai');
    }

    public function update(AuthUser $authUser, RencanaPegawai $rencanaPegawai): bool
    {
        return $authUser->can('Update:RencanaPegawai');
    }

    public function delete(AuthUser $authUser, RencanaPegawai $rencanaPegawai): bool
    {
        return $authUser->can('Delete:RencanaPegawai');
    }

    public function restore(AuthUser $authUser, RencanaPegawai $rencanaPegawai): bool
    {
        return $authUser->can('Restore:RencanaPegawai');
    }

    public function forceDelete(AuthUser $authUser, RencanaPegawai $rencanaPegawai): bool
    {
        return $authUser->can('ForceDelete:RencanaPegawai');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RencanaPegawai');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RencanaPegawai');
    }

    public function replicate(AuthUser $authUser, RencanaPegawai $rencanaPegawai): bool
    {
        return $authUser->can('Replicate:RencanaPegawai');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RencanaPegawai');
    }

}