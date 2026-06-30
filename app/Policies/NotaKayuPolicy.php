<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\NotaKayu;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotaKayuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NotaKayu');
    }

    public function view(AuthUser $authUser, NotaKayu $notaKayu): bool
    {
        return $authUser->can('View:NotaKayu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NotaKayu');
    }

    public function update(AuthUser $authUser, NotaKayu $notaKayu): bool
    {
        return $authUser->can('Update:NotaKayu');
    }

    public function delete(AuthUser $authUser, NotaKayu $notaKayu): bool
    {
        return $authUser->can('Delete:NotaKayu');
    }

    public function restore(AuthUser $authUser, NotaKayu $notaKayu): bool
    {
        return $authUser->can('Restore:NotaKayu');
    }

    public function forceDelete(AuthUser $authUser, NotaKayu $notaKayu): bool
    {
        return $authUser->can('ForceDelete:NotaKayu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NotaKayu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NotaKayu');
    }

    public function replicate(AuthUser $authUser, NotaKayu $notaKayu): bool
    {
        return $authUser->can('Replicate:NotaKayu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NotaKayu');
    }

}