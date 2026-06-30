<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BahanDempul;
use Illuminate\Auth\Access\HandlesAuthorization;

class BahanDempulPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BahanDempul');
    }

    public function view(AuthUser $authUser, BahanDempul $bahanDempul): bool
    {
        return $authUser->can('View:BahanDempul');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BahanDempul');
    }

    public function update(AuthUser $authUser, BahanDempul $bahanDempul): bool
    {
        return $authUser->can('Update:BahanDempul');
    }

    public function delete(AuthUser $authUser, BahanDempul $bahanDempul): bool
    {
        return $authUser->can('Delete:BahanDempul');
    }

    public function restore(AuthUser $authUser, BahanDempul $bahanDempul): bool
    {
        return $authUser->can('Restore:BahanDempul');
    }

    public function forceDelete(AuthUser $authUser, BahanDempul $bahanDempul): bool
    {
        return $authUser->can('ForceDelete:BahanDempul');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BahanDempul');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BahanDempul');
    }

    public function replicate(AuthUser $authUser, BahanDempul $bahanDempul): bool
    {
        return $authUser->can('Replicate:BahanDempul');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BahanDempul');
    }

}