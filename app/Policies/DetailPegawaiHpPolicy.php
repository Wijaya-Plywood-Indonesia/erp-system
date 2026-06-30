<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailPegawaiHp;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailPegawaiHpPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailPegawaiHp');
    }

    public function view(AuthUser $authUser, DetailPegawaiHp $detailPegawaiHp): bool
    {
        return $authUser->can('View:DetailPegawaiHp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailPegawaiHp');
    }

    public function update(AuthUser $authUser, DetailPegawaiHp $detailPegawaiHp): bool
    {
        return $authUser->can('Update:DetailPegawaiHp');
    }

    public function delete(AuthUser $authUser, DetailPegawaiHp $detailPegawaiHp): bool
    {
        return $authUser->can('Delete:DetailPegawaiHp');
    }

    public function restore(AuthUser $authUser, DetailPegawaiHp $detailPegawaiHp): bool
    {
        return $authUser->can('Restore:DetailPegawaiHp');
    }

    public function forceDelete(AuthUser $authUser, DetailPegawaiHp $detailPegawaiHp): bool
    {
        return $authUser->can('ForceDelete:DetailPegawaiHp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailPegawaiHp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailPegawaiHp');
    }

    public function replicate(AuthUser $authUser, DetailPegawaiHp $detailPegawaiHp): bool
    {
        return $authUser->can('Replicate:DetailPegawaiHp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailPegawaiHp');
    }

}