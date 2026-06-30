<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailPegawaiStik;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailPegawaiStikPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailPegawaiStik');
    }

    public function view(AuthUser $authUser, DetailPegawaiStik $detailPegawaiStik): bool
    {
        return $authUser->can('View:DetailPegawaiStik');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailPegawaiStik');
    }

    public function update(AuthUser $authUser, DetailPegawaiStik $detailPegawaiStik): bool
    {
        return $authUser->can('Update:DetailPegawaiStik');
    }

    public function delete(AuthUser $authUser, DetailPegawaiStik $detailPegawaiStik): bool
    {
        return $authUser->can('Delete:DetailPegawaiStik');
    }

    public function restore(AuthUser $authUser, DetailPegawaiStik $detailPegawaiStik): bool
    {
        return $authUser->can('Restore:DetailPegawaiStik');
    }

    public function forceDelete(AuthUser $authUser, DetailPegawaiStik $detailPegawaiStik): bool
    {
        return $authUser->can('ForceDelete:DetailPegawaiStik');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailPegawaiStik');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailPegawaiStik');
    }

    public function replicate(AuthUser $authUser, DetailPegawaiStik $detailPegawaiStik): bool
    {
        return $authUser->can('Replicate:DetailPegawaiStik');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailPegawaiStik');
    }

}