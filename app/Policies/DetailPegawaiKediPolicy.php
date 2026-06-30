<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailPegawaiKedi;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailPegawaiKediPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailPegawaiKedi');
    }

    public function view(AuthUser $authUser, DetailPegawaiKedi $detailPegawaiKedi): bool
    {
        return $authUser->can('View:DetailPegawaiKedi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailPegawaiKedi');
    }

    public function update(AuthUser $authUser, DetailPegawaiKedi $detailPegawaiKedi): bool
    {
        return $authUser->can('Update:DetailPegawaiKedi');
    }

    public function delete(AuthUser $authUser, DetailPegawaiKedi $detailPegawaiKedi): bool
    {
        return $authUser->can('Delete:DetailPegawaiKedi');
    }

    public function restore(AuthUser $authUser, DetailPegawaiKedi $detailPegawaiKedi): bool
    {
        return $authUser->can('Restore:DetailPegawaiKedi');
    }

    public function forceDelete(AuthUser $authUser, DetailPegawaiKedi $detailPegawaiKedi): bool
    {
        return $authUser->can('ForceDelete:DetailPegawaiKedi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailPegawaiKedi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailPegawaiKedi');
    }

    public function replicate(AuthUser $authUser, DetailPegawaiKedi $detailPegawaiKedi): bool
    {
        return $authUser->can('Replicate:DetailPegawaiKedi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailPegawaiKedi');
    }

}