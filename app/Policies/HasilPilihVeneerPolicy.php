<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HasilPilihVeneer;
use Illuminate\Auth\Access\HandlesAuthorization;

class HasilPilihVeneerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HasilPilihVeneer');
    }

    public function view(AuthUser $authUser, HasilPilihVeneer $hasilPilihVeneer): bool
    {
        return $authUser->can('View:HasilPilihVeneer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HasilPilihVeneer');
    }

    public function update(AuthUser $authUser, HasilPilihVeneer $hasilPilihVeneer): bool
    {
        return $authUser->can('Update:HasilPilihVeneer');
    }

    public function delete(AuthUser $authUser, HasilPilihVeneer $hasilPilihVeneer): bool
    {
        return $authUser->can('Delete:HasilPilihVeneer');
    }

    public function restore(AuthUser $authUser, HasilPilihVeneer $hasilPilihVeneer): bool
    {
        return $authUser->can('Restore:HasilPilihVeneer');
    }

    public function forceDelete(AuthUser $authUser, HasilPilihVeneer $hasilPilihVeneer): bool
    {
        return $authUser->can('ForceDelete:HasilPilihVeneer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HasilPilihVeneer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HasilPilihVeneer');
    }

    public function replicate(AuthUser $authUser, HasilPilihVeneer $hasilPilihVeneer): bool
    {
        return $authUser->can('Replicate:HasilPilihVeneer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HasilPilihVeneer');
    }

}