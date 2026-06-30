<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\hasil_guellotine;
use Illuminate\Auth\Access\HandlesAuthorization;

class hasil_guellotinePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HasilGuellotine');
    }

    public function view(AuthUser $authUser, hasil_guellotine $hasilGuellotine): bool
    {
        return $authUser->can('View:HasilGuellotine');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HasilGuellotine');
    }

    public function update(AuthUser $authUser, hasil_guellotine $hasilGuellotine): bool
    {
        return $authUser->can('Update:HasilGuellotine');
    }

    public function delete(AuthUser $authUser, hasil_guellotine $hasilGuellotine): bool
    {
        return $authUser->can('Delete:HasilGuellotine');
    }

    public function restore(AuthUser $authUser, hasil_guellotine $hasilGuellotine): bool
    {
        return $authUser->can('Restore:HasilGuellotine');
    }

    public function forceDelete(AuthUser $authUser, hasil_guellotine $hasilGuellotine): bool
    {
        return $authUser->can('ForceDelete:HasilGuellotine');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HasilGuellotine');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HasilGuellotine');
    }

    public function replicate(AuthUser $authUser, hasil_guellotine $hasilGuellotine): bool
    {
        return $authUser->can('Replicate:HasilGuellotine');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HasilGuellotine');
    }

}