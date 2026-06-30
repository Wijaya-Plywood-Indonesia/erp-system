<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\NotaBarangKeluar;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotaBarangKeluarPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NotaBarangKeluar');
    }

    public function view(AuthUser $authUser, NotaBarangKeluar $notaBarangKeluar): bool
    {
        return $authUser->can('View:NotaBarangKeluar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NotaBarangKeluar');
    }

    public function update(AuthUser $authUser, NotaBarangKeluar $notaBarangKeluar): bool
    {
        return $authUser->can('Update:NotaBarangKeluar');
    }

    public function delete(AuthUser $authUser, NotaBarangKeluar $notaBarangKeluar): bool
    {
        return $authUser->can('Delete:NotaBarangKeluar');
    }

    public function restore(AuthUser $authUser, NotaBarangKeluar $notaBarangKeluar): bool
    {
        return $authUser->can('Restore:NotaBarangKeluar');
    }

    public function forceDelete(AuthUser $authUser, NotaBarangKeluar $notaBarangKeluar): bool
    {
        return $authUser->can('ForceDelete:NotaBarangKeluar');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NotaBarangKeluar');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NotaBarangKeluar');
    }

    public function replicate(AuthUser $authUser, NotaBarangKeluar $notaBarangKeluar): bool
    {
        return $authUser->can('Replicate:NotaBarangKeluar');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NotaBarangKeluar');
    }

}