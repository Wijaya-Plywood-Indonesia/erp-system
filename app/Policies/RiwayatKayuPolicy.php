<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RiwayatKayu;
use Illuminate\Auth\Access\HandlesAuthorization;

class RiwayatKayuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RiwayatKayu');
    }

    public function view(AuthUser $authUser, RiwayatKayu $riwayatKayu): bool
    {
        return $authUser->can('View:RiwayatKayu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RiwayatKayu');
    }

    public function update(AuthUser $authUser, RiwayatKayu $riwayatKayu): bool
    {
        return $authUser->can('Update:RiwayatKayu');
    }

    public function delete(AuthUser $authUser, RiwayatKayu $riwayatKayu): bool
    {
        return $authUser->can('Delete:RiwayatKayu');
    }

    public function restore(AuthUser $authUser, RiwayatKayu $riwayatKayu): bool
    {
        return $authUser->can('Restore:RiwayatKayu');
    }

    public function forceDelete(AuthUser $authUser, RiwayatKayu $riwayatKayu): bool
    {
        return $authUser->can('ForceDelete:RiwayatKayu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RiwayatKayu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RiwayatKayu');
    }

    public function replicate(AuthUser $authUser, RiwayatKayu $riwayatKayu): bool
    {
        return $authUser->can('Replicate:RiwayatKayu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RiwayatKayu');
    }

}