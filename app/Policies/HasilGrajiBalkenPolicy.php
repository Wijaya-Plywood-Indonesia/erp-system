<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HasilGrajiBalken;
use Illuminate\Auth\Access\HandlesAuthorization;

class HasilGrajiBalkenPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HasilGrajiBalken');
    }

    public function view(AuthUser $authUser, HasilGrajiBalken $hasilGrajiBalken): bool
    {
        return $authUser->can('View:HasilGrajiBalken');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HasilGrajiBalken');
    }

    public function update(AuthUser $authUser, HasilGrajiBalken $hasilGrajiBalken): bool
    {
        return $authUser->can('Update:HasilGrajiBalken');
    }

    public function delete(AuthUser $authUser, HasilGrajiBalken $hasilGrajiBalken): bool
    {
        return $authUser->can('Delete:HasilGrajiBalken');
    }

    public function restore(AuthUser $authUser, HasilGrajiBalken $hasilGrajiBalken): bool
    {
        return $authUser->can('Restore:HasilGrajiBalken');
    }

    public function forceDelete(AuthUser $authUser, HasilGrajiBalken $hasilGrajiBalken): bool
    {
        return $authUser->can('ForceDelete:HasilGrajiBalken');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HasilGrajiBalken');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HasilGrajiBalken');
    }

    public function replicate(AuthUser $authUser, HasilGrajiBalken $hasilGrajiBalken): bool
    {
        return $authUser->can('Replicate:HasilGrajiBalken');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HasilGrajiBalken');
    }

}