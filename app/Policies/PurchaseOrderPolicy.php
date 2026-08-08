<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PurchaseOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseOrderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PurchaseOrder');
    }

    public function view(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return $authUser->can('View:PurchaseOrder');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PurchaseOrder');
    }

    public function update(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return $authUser->can('Update:PurchaseOrder');
    }

    public function delete(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return $authUser->can('Delete:PurchaseOrder');
    }

    public function restore(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return $authUser->can('Restore:PurchaseOrder');
    }

    public function forceDelete(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return $authUser->can('ForceDelete:PurchaseOrder');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PurchaseOrder');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PurchaseOrder');
    }

    public function replicate(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return $authUser->can('Replicate:PurchaseOrder');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PurchaseOrder');
    }

}