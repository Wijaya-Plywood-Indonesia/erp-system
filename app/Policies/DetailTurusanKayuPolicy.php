<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailTurusanKayu;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailTurusanKayuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailTurusanKayu');
    }

    public function view(AuthUser $authUser, DetailTurusanKayu $detailTurusanKayu): bool
    {
        return $authUser->can('View:DetailTurusanKayu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailTurusanKayu');
    }

    public function update(AuthUser $authUser, DetailTurusanKayu $detailTurusanKayu): bool
    {
        return $authUser->can('Update:DetailTurusanKayu');
    }

    public function delete(AuthUser $authUser, DetailTurusanKayu $detailTurusanKayu): bool
    {
        return $authUser->can('Delete:DetailTurusanKayu');
    }

    public function restore(AuthUser $authUser, DetailTurusanKayu $detailTurusanKayu): bool
    {
        return $authUser->can('Restore:DetailTurusanKayu');
    }

    public function forceDelete(AuthUser $authUser, DetailTurusanKayu $detailTurusanKayu): bool
    {
        return $authUser->can('ForceDelete:DetailTurusanKayu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailTurusanKayu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailTurusanKayu');
    }

    public function replicate(AuthUser $authUser, DetailTurusanKayu $detailTurusanKayu): bool
    {
        return $authUser->can('Replicate:DetailTurusanKayu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailTurusanKayu');
    }

}