<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BahanHotPress;
use Illuminate\Auth\Access\HandlesAuthorization;

class BahanHotPressPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BahanHotPress');
    }

    public function view(AuthUser $authUser, BahanHotPress $bahanHotPress): bool
    {
        return $authUser->can('View:BahanHotPress');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BahanHotPress');
    }

    public function update(AuthUser $authUser, BahanHotPress $bahanHotPress): bool
    {
        return $authUser->can('Update:BahanHotPress');
    }

    public function delete(AuthUser $authUser, BahanHotPress $bahanHotPress): bool
    {
        return $authUser->can('Delete:BahanHotPress');
    }

    public function restore(AuthUser $authUser, BahanHotPress $bahanHotPress): bool
    {
        return $authUser->can('Restore:BahanHotPress');
    }

    public function forceDelete(AuthUser $authUser, BahanHotPress $bahanHotPress): bool
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

    public function replicate(AuthUser $authUser, BahanHotPress $bahanHotPress): bool
    {
        return $authUser->can('Replicate:BahanHotPress');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BahanHotPress');
    }

}