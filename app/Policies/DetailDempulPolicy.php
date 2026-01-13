<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailDempul;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailDempulPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailDempul');
    }

    public function view(AuthUser $authUser, DetailDempul $detailDempul): bool
    {
        return $authUser->can('View:DetailDempul');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailDempul');
    }

    public function update(AuthUser $authUser, DetailDempul $detailDempul): bool
    {
        return $authUser->can('Update:DetailDempul');
    }

    public function delete(AuthUser $authUser, DetailDempul $detailDempul): bool
    {
        return $authUser->can('Delete:DetailDempul');
    }

    public function restore(AuthUser $authUser, DetailDempul $detailDempul): bool
    {
        return $authUser->can('Restore:DetailDempul');
    }

    public function forceDelete(AuthUser $authUser, DetailDempul $detailDempul): bool
    {
        return $authUser->can('ForceDelete:DetailDempul');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailDempul');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailDempul');
    }

    public function replicate(AuthUser $authUser, DetailDempul $detailDempul): bool
    {
        return $authUser->can('Replicate:DetailDempul');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailDempul');
    }

}