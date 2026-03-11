<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\GradeRule;
use Illuminate\Auth\Access\HandlesAuthorization;

class GradeRulePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GradeRule');
    }

    public function view(AuthUser $authUser, GradeRule $gradeRule): bool
    {
        return $authUser->can('View:GradeRule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GradeRule');
    }

    public function update(AuthUser $authUser, GradeRule $gradeRule): bool
    {
        return $authUser->can('Update:GradeRule');
    }

    public function delete(AuthUser $authUser, GradeRule $gradeRule): bool
    {
        return $authUser->can('Delete:GradeRule');
    }

    public function restore(AuthUser $authUser, GradeRule $gradeRule): bool
    {
        return $authUser->can('Restore:GradeRule');
    }

    public function forceDelete(AuthUser $authUser, GradeRule $gradeRule): bool
    {
        return $authUser->can('ForceDelete:GradeRule');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GradeRule');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GradeRule');
    }

    public function replicate(AuthUser $authUser, GradeRule $gradeRule): bool
    {
        return $authUser->can('Replicate:GradeRule');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GradeRule');
    }

}