<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiNyusup;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiNyusupPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiNyusup');
    }

    public function view(AuthUser $authUser, PegawaiNyusup $pegawaiNyusup): bool
    {
        return $authUser->can('View:PegawaiNyusup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiNyusup');
    }

    public function update(AuthUser $authUser, PegawaiNyusup $pegawaiNyusup): bool
    {
        return $authUser->can('Update:PegawaiNyusup');
    }

    public function delete(AuthUser $authUser, PegawaiNyusup $pegawaiNyusup): bool
    {
        return $authUser->can('Delete:PegawaiNyusup');
    }

    public function restore(AuthUser $authUser, PegawaiNyusup $pegawaiNyusup): bool
    {
        return $authUser->can('Restore:PegawaiNyusup');
    }

    public function forceDelete(AuthUser $authUser, PegawaiNyusup $pegawaiNyusup): bool
    {
        return $authUser->can('ForceDelete:PegawaiNyusup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiNyusup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiNyusup');
    }

    public function replicate(AuthUser $authUser, PegawaiNyusup $pegawaiNyusup): bool
    {
        return $authUser->can('Replicate:PegawaiNyusup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiNyusup');
    }

}