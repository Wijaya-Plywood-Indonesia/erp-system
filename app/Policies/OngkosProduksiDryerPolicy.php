<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OngkosProduksiDryer;
use Illuminate\Auth\Access\HandlesAuthorization;

class OngkosProduksiDryerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OngkosProduksiDryer');
    }

    public function view(AuthUser $authUser, OngkosProduksiDryer $ongkosProduksiDryer): bool
    {
        return $authUser->can('View:OngkosProduksiDryer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OngkosProduksiDryer');
    }

    public function update(AuthUser $authUser, OngkosProduksiDryer $ongkosProduksiDryer): bool
    {
        return $authUser->can('Update:OngkosProduksiDryer');
    }

    public function delete(AuthUser $authUser, OngkosProduksiDryer $ongkosProduksiDryer): bool
    {
        return $authUser->can('Delete:OngkosProduksiDryer');
    }

    public function restore(AuthUser $authUser, OngkosProduksiDryer $ongkosProduksiDryer): bool
    {
        return $authUser->can('Restore:OngkosProduksiDryer');
    }

    public function forceDelete(AuthUser $authUser, OngkosProduksiDryer $ongkosProduksiDryer): bool
    {
        return $authUser->can('ForceDelete:OngkosProduksiDryer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OngkosProduksiDryer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OngkosProduksiDryer');
    }

    public function replicate(AuthUser $authUser, OngkosProduksiDryer $ongkosProduksiDryer): bool
    {
        return $authUser->can('Replicate:OngkosProduksiDryer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OngkosProduksiDryer');
    }

}