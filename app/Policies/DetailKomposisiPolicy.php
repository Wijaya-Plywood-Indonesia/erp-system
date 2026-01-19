<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailKomposisi;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailKomposisiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailKomposisi');
    }

    public function view(AuthUser $authUser, DetailKomposisi $detailKomposisi): bool
    {
        return $authUser->can('View:DetailKomposisi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailKomposisi');
    }

    public function update(AuthUser $authUser, DetailKomposisi $detailKomposisi): bool
    {
        return $authUser->can('Update:DetailKomposisi');
    }

    public function delete(AuthUser $authUser, DetailKomposisi $detailKomposisi): bool
    {
        return $authUser->can('Delete:DetailKomposisi');
    }

    public function restore(AuthUser $authUser, DetailKomposisi $detailKomposisi): bool
    {
        return $authUser->can('Restore:DetailKomposisi');
    }

    public function forceDelete(AuthUser $authUser, DetailKomposisi $detailKomposisi): bool
    {
        return $authUser->can('ForceDelete:DetailKomposisi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailKomposisi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailKomposisi');
    }

    public function replicate(AuthUser $authUser, DetailKomposisi $detailKomposisi): bool
    {
        return $authUser->can('Replicate:DetailKomposisi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailKomposisi');
    }

}