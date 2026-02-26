<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BahanHotpress;
use Illuminate\Auth\Access\HandlesAuthorization;

class BahanHotpressPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BahanHotpress');
    }

    public function view(AuthUser $authUser, BahanHotpress $bahanHotpress): bool
    {
        return $authUser->can('View:BahanHotpress');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BahanHotpress');
    }

    public function update(AuthUser $authUser, BahanHotpress $bahanHotpress): bool
    {
        return $authUser->can('Update:BahanHotpress');
    }

    public function delete(AuthUser $authUser, BahanHotpress $bahanHotpress): bool
    {
        return $authUser->can('Delete:BahanHotpress');
    }

    public function restore(AuthUser $authUser, BahanHotpress $bahanHotpress): bool
    {
        return $authUser->can('Restore:BahanHotpress');
    }

    public function forceDelete(AuthUser $authUser, BahanHotpress $bahanHotpress): bool
    {
        return $authUser->can('ForceDelete:BahanHotpress');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BahanHotpress');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BahanHotpress');
    }

    public function replicate(AuthUser $authUser, BahanHotpress $bahanHotpress): bool
    {
        return $authUser->can('Replicate:BahanHotpress');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BahanHotpress');
    }

}