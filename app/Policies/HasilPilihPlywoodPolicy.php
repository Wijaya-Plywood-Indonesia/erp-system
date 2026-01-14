<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HasilPilihPlywood;
use Illuminate\Auth\Access\HandlesAuthorization;

class HasilPilihPlywoodPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HasilPilihPlywood');
    }

    public function view(AuthUser $authUser, HasilPilihPlywood $hasilPilihPlywood): bool
    {
        return $authUser->can('View:HasilPilihPlywood');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HasilPilihPlywood');
    }

    public function update(AuthUser $authUser, HasilPilihPlywood $hasilPilihPlywood): bool
    {
        return $authUser->can('Update:HasilPilihPlywood');
    }

    public function delete(AuthUser $authUser, HasilPilihPlywood $hasilPilihPlywood): bool
    {
        return $authUser->can('Delete:HasilPilihPlywood');
    }

    public function restore(AuthUser $authUser, HasilPilihPlywood $hasilPilihPlywood): bool
    {
        return $authUser->can('Restore:HasilPilihPlywood');
    }

    public function forceDelete(AuthUser $authUser, HasilPilihPlywood $hasilPilihPlywood): bool
    {
        return $authUser->can('ForceDelete:HasilPilihPlywood');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HasilPilihPlywood');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HasilPilihPlywood');
    }

    public function replicate(AuthUser $authUser, HasilPilihPlywood $hasilPilihPlywood): bool
    {
        return $authUser->can('Replicate:HasilPilihPlywood');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HasilPilihPlywood');
    }

}