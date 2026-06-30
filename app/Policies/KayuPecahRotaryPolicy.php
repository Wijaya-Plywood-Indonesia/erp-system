<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\KayuPecahRotary;
use Illuminate\Auth\Access\HandlesAuthorization;

class KayuPecahRotaryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:KayuPecahRotary');
    }

    public function view(AuthUser $authUser, KayuPecahRotary $kayuPecahRotary): bool
    {
        return $authUser->can('View:KayuPecahRotary');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:KayuPecahRotary');
    }

    public function update(AuthUser $authUser, KayuPecahRotary $kayuPecahRotary): bool
    {
        return $authUser->can('Update:KayuPecahRotary');
    }

    public function delete(AuthUser $authUser, KayuPecahRotary $kayuPecahRotary): bool
    {
        return $authUser->can('Delete:KayuPecahRotary');
    }

    public function restore(AuthUser $authUser, KayuPecahRotary $kayuPecahRotary): bool
    {
        return $authUser->can('Restore:KayuPecahRotary');
    }

    public function forceDelete(AuthUser $authUser, KayuPecahRotary $kayuPecahRotary): bool
    {
        return $authUser->can('ForceDelete:KayuPecahRotary');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:KayuPecahRotary');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:KayuPecahRotary');
    }

    public function replicate(AuthUser $authUser, KayuPecahRotary $kayuPecahRotary): bool
    {
        return $authUser->can('Replicate:KayuPecahRotary');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:KayuPecahRotary');
    }

}