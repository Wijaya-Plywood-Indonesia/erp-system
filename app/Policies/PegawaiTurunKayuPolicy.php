<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiTurunKayu;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiTurunKayuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiTurunKayu');
    }

    public function view(AuthUser $authUser, PegawaiTurunKayu $pegawaiTurunKayu): bool
    {
        return $authUser->can('View:PegawaiTurunKayu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiTurunKayu');
    }

    public function update(AuthUser $authUser, PegawaiTurunKayu $pegawaiTurunKayu): bool
    {
        return $authUser->can('Update:PegawaiTurunKayu');
    }

    public function delete(AuthUser $authUser, PegawaiTurunKayu $pegawaiTurunKayu): bool
    {
        return $authUser->can('Delete:PegawaiTurunKayu');
    }

    public function restore(AuthUser $authUser, PegawaiTurunKayu $pegawaiTurunKayu): bool
    {
        return $authUser->can('Restore:PegawaiTurunKayu');
    }

    public function forceDelete(AuthUser $authUser, PegawaiTurunKayu $pegawaiTurunKayu): bool
    {
        return $authUser->can('ForceDelete:PegawaiTurunKayu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiTurunKayu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiTurunKayu');
    }

    public function replicate(AuthUser $authUser, PegawaiTurunKayu $pegawaiTurunKayu): bool
    {
        return $authUser->can('Replicate:PegawaiTurunKayu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiTurunKayu');
    }

}