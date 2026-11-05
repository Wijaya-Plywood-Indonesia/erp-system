<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Bm;
use Illuminate\Foundation\Auth\User as AuthUser;

class BmPolicy
{
    public function viewAny(AuthUser $u): bool { return $u->can('ViewAny:Bm'); }
    public function view(AuthUser $u, Bm $r): bool { return $u->can('View:Bm'); }
    public function create(AuthUser $u): bool { return $u->can('Create:Bm'); }
    public function update(AuthUser $u, Bm $r): bool { return $u->can('Update:Bm'); }
    public function delete(AuthUser $u, Bm $r): bool { return $u->can('Delete:Bm'); }
    public function deleteAny(AuthUser $u): bool { return $u->can('Delete:Bm'); }
}
