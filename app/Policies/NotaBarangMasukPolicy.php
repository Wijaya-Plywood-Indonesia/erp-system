<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\NotaBarangMasuk;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotaBarangMasukPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NotaBarangMasuk');
    }

    public function view(AuthUser $authUser, NotaBarangMasuk $notaBarangMasuk): bool
    {
        return $authUser->can('View:NotaBarangMasuk');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NotaBarangMasuk');
    }

    public function update(AuthUser $authUser, NotaBarangMasuk $notaBarangMasuk): bool
    {
        return $authUser->can('Update:NotaBarangMasuk');
    }

    public function delete(AuthUser $authUser, NotaBarangMasuk $notaBarangMasuk): bool
    {
        return $authUser->can('Delete:NotaBarangMasuk');
    }

    public function restore(AuthUser $authUser, NotaBarangMasuk $notaBarangMasuk): bool
    {
        return $authUser->can('Restore:NotaBarangMasuk');
    }

    public function forceDelete(AuthUser $authUser, NotaBarangMasuk $notaBarangMasuk): bool
    {
        return $authUser->can('ForceDelete:NotaBarangMasuk');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NotaBarangMasuk');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NotaBarangMasuk');
    }

    public function replicate(AuthUser $authUser, NotaBarangMasuk $notaBarangMasuk): bool
    {
        return $authUser->can('Replicate:NotaBarangMasuk');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NotaBarangMasuk');
    }

}