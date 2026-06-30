<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HasilGrajiStik;
use Illuminate\Auth\Access\HandlesAuthorization;

class HasilGrajiStikPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HasilGrajiStik');
    }

    public function view(AuthUser $authUser, HasilGrajiStik $hasilGrajiStik): bool
    {
        return $authUser->can('View:HasilGrajiStik');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HasilGrajiStik');
    }

    public function update(AuthUser $authUser, HasilGrajiStik $hasilGrajiStik): bool
    {
        return $authUser->can('Update:HasilGrajiStik');
    }

    public function delete(AuthUser $authUser, HasilGrajiStik $hasilGrajiStik): bool
    {
        return $authUser->can('Delete:HasilGrajiStik');
    }

    public function restore(AuthUser $authUser, HasilGrajiStik $hasilGrajiStik): bool
    {
        return $authUser->can('Restore:HasilGrajiStik');
    }

    public function forceDelete(AuthUser $authUser, HasilGrajiStik $hasilGrajiStik): bool
    {
        return $authUser->can('ForceDelete:HasilGrajiStik');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HasilGrajiStik');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HasilGrajiStik');
    }

    public function replicate(AuthUser $authUser, HasilGrajiStik $hasilGrajiStik): bool
    {
        return $authUser->can('Replicate:HasilGrajiStik');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HasilGrajiStik');
    }

}