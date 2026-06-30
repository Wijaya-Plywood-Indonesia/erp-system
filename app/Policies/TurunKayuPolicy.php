<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TurunKayu;
use Illuminate\Auth\Access\HandlesAuthorization;

class TurunKayuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TurunKayu');
    }

    public function view(AuthUser $authUser, TurunKayu $turunKayu): bool
    {
        return $authUser->can('View:TurunKayu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TurunKayu');
    }

    public function update(AuthUser $authUser, TurunKayu $turunKayu): bool
    {
        return $authUser->can('Update:TurunKayu');
    }

    public function delete(AuthUser $authUser, TurunKayu $turunKayu): bool
    {
        return $authUser->can('Delete:TurunKayu');
    }

    public function restore(AuthUser $authUser, TurunKayu $turunKayu): bool
    {
        return $authUser->can('Restore:TurunKayu');
    }

    public function forceDelete(AuthUser $authUser, TurunKayu $turunKayu): bool
    {
        return $authUser->can('ForceDelete:TurunKayu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TurunKayu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TurunKayu');
    }

    public function replicate(AuthUser $authUser, TurunKayu $turunKayu): bool
    {
        return $authUser->can('Replicate:TurunKayu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TurunKayu');
    }

}