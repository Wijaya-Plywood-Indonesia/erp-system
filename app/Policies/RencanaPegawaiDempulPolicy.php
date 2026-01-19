<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RencanaPegawaiDempul;
use Illuminate\Auth\Access\HandlesAuthorization;

class RencanaPegawaiDempulPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RencanaPegawaiDempul');
    }

    public function view(AuthUser $authUser, RencanaPegawaiDempul $rencanaPegawaiDempul): bool
    {
        return $authUser->can('View:RencanaPegawaiDempul');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RencanaPegawaiDempul');
    }

    public function update(AuthUser $authUser, RencanaPegawaiDempul $rencanaPegawaiDempul): bool
    {
        return $authUser->can('Update:RencanaPegawaiDempul');
    }

    public function delete(AuthUser $authUser, RencanaPegawaiDempul $rencanaPegawaiDempul): bool
    {
        return $authUser->can('Delete:RencanaPegawaiDempul');
    }

    public function restore(AuthUser $authUser, RencanaPegawaiDempul $rencanaPegawaiDempul): bool
    {
        return $authUser->can('Restore:RencanaPegawaiDempul');
    }

    public function forceDelete(AuthUser $authUser, RencanaPegawaiDempul $rencanaPegawaiDempul): bool
    {
        return $authUser->can('ForceDelete:RencanaPegawaiDempul');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RencanaPegawaiDempul');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RencanaPegawaiDempul');
    }

    public function replicate(AuthUser $authUser, RencanaPegawaiDempul $rencanaPegawaiDempul): bool
    {
        return $authUser->can('Replicate:RencanaPegawaiDempul');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RencanaPegawaiDempul');
    }

}