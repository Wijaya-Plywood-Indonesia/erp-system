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
        return $authUser->can('ViewAny:BahanHotPress');
    }

    public function view(AuthUser $authUser, BahanHotpress $bahanHotpress): bool
    {
        return $authUser->can('View:BahanHotPress');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BahanHotPress');
    }

    public function update(AuthUser $authUser, BahanHotpress $bahanHotpress): bool
    {
        return $authUser->can('Update:BahanHotPress');
    }

    public function delete(AuthUser $authUser, BahanHotpress $bahanHotpress): bool
    {
        return $authUser->can('Delete:BahanHotPress');
    }

    public function restore(AuthUser $authUser, BahanHotpress $bahanHotpress): bool
    {
        return $authUser->can('Restore:BahanHotPress');
    }

    public function forceDelete(AuthUser $authUser, BahanHotpress $bahanHotpress): bool
    {
        return $authUser->can('ForceDelete:BahanHotPress');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BahanHotPress');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BahanHotPress');
    }

    public function replicate(AuthUser $authUser, BahanHotpress $bahanHotpress): bool
    {
        return $authUser->can('Replicate:BahanHotPress');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BahanHotPress');
    }

}