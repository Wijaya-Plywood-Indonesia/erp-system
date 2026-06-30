<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BahanPilihPlywood;
use Illuminate\Auth\Access\HandlesAuthorization;

class BahanPilihPlywoodPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BahanPilihPlywood');
    }

    public function view(AuthUser $authUser, BahanPilihPlywood $bahanPilihPlywood): bool
    {
        return $authUser->can('View:BahanPilihPlywood');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BahanPilihPlywood');
    }

    public function update(AuthUser $authUser, BahanPilihPlywood $bahanPilihPlywood): bool
    {
        return $authUser->can('Update:BahanPilihPlywood');
    }

    public function delete(AuthUser $authUser, BahanPilihPlywood $bahanPilihPlywood): bool
    {
        return $authUser->can('Delete:BahanPilihPlywood');
    }

    public function restore(AuthUser $authUser, BahanPilihPlywood $bahanPilihPlywood): bool
    {
        return $authUser->can('Restore:BahanPilihPlywood');
    }

    public function forceDelete(AuthUser $authUser, BahanPilihPlywood $bahanPilihPlywood): bool
    {
        return $authUser->can('ForceDelete:BahanPilihPlywood');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BahanPilihPlywood');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BahanPilihPlywood');
    }

    public function replicate(AuthUser $authUser, BahanPilihPlywood $bahanPilihPlywood): bool
    {
        return $authUser->can('Replicate:BahanPilihPlywood');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BahanPilihPlywood');
    }

}