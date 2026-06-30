<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailTurunKayu;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailTurunKayuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailTurunKayu');
    }

    public function view(AuthUser $authUser, DetailTurunKayu $detailTurunKayu): bool
    {
        return $authUser->can('View:DetailTurunKayu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailTurunKayu');
    }

    public function update(AuthUser $authUser, DetailTurunKayu $detailTurunKayu): bool
    {
        return $authUser->can('Update:DetailTurunKayu');
    }

    public function delete(AuthUser $authUser, DetailTurunKayu $detailTurunKayu): bool
    {
        return $authUser->can('Delete:DetailTurunKayu');
    }

    public function restore(AuthUser $authUser, DetailTurunKayu $detailTurunKayu): bool
    {
        return $authUser->can('Restore:DetailTurunKayu');
    }

    public function forceDelete(AuthUser $authUser, DetailTurunKayu $detailTurunKayu): bool
    {
        return $authUser->can('ForceDelete:DetailTurunKayu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailTurunKayu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailTurunKayu');
    }

    public function replicate(AuthUser $authUser, DetailTurunKayu $detailTurunKayu): bool
    {
        return $authUser->can('Replicate:DetailTurunKayu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailTurunKayu');
    }

}