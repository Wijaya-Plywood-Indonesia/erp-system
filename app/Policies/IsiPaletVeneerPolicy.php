<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\IsiPaletVeneer;
use Illuminate\Auth\Access\HandlesAuthorization;

class IsiPaletVeneerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IsiPaletVeneer');
    }

    public function view(AuthUser $authUser, IsiPaletVeneer $isiPaletVeneer): bool
    {
        return $authUser->can('View:IsiPaletVeneer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IsiPaletVeneer');
    }

    public function update(AuthUser $authUser, IsiPaletVeneer $isiPaletVeneer): bool
    {
        return $authUser->can('Update:IsiPaletVeneer');
    }

    public function delete(AuthUser $authUser, IsiPaletVeneer $isiPaletVeneer): bool
    {
        return $authUser->can('Delete:IsiPaletVeneer');
    }

    public function restore(AuthUser $authUser, IsiPaletVeneer $isiPaletVeneer): bool
    {
        return $authUser->can('Restore:IsiPaletVeneer');
    }

    public function forceDelete(AuthUser $authUser, IsiPaletVeneer $isiPaletVeneer): bool
    {
        return $authUser->can('ForceDelete:IsiPaletVeneer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:IsiPaletVeneer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:IsiPaletVeneer');
    }

    public function replicate(AuthUser $authUser, IsiPaletVeneer $isiPaletVeneer): bool
    {
        return $authUser->can('Replicate:IsiPaletVeneer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:IsiPaletVeneer');
    }

}