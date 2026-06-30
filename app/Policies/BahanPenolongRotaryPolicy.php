<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BahanPenolongRotary;
use Illuminate\Auth\Access\HandlesAuthorization;

class BahanPenolongRotaryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BahanPenolongRotary');
    }

    public function view(AuthUser $authUser, BahanPenolongRotary $bahanPenolongRotary): bool
    {
        return $authUser->can('View:BahanPenolongRotary');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BahanPenolongRotary');
    }

    public function update(AuthUser $authUser, BahanPenolongRotary $bahanPenolongRotary): bool
    {
        return $authUser->can('Update:BahanPenolongRotary');
    }

    public function delete(AuthUser $authUser, BahanPenolongRotary $bahanPenolongRotary): bool
    {
        return $authUser->can('Delete:BahanPenolongRotary');
    }

    public function restore(AuthUser $authUser, BahanPenolongRotary $bahanPenolongRotary): bool
    {
        return $authUser->can('Restore:BahanPenolongRotary');
    }

    public function forceDelete(AuthUser $authUser, BahanPenolongRotary $bahanPenolongRotary): bool
    {
        return $authUser->can('ForceDelete:BahanPenolongRotary');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BahanPenolongRotary');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BahanPenolongRotary');
    }

    public function replicate(AuthUser $authUser, BahanPenolongRotary $bahanPenolongRotary): bool
    {
        return $authUser->can('Replicate:BahanPenolongRotary');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BahanPenolongRotary');
    }

}