<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Bk;
use Illuminate\Foundation\Auth\User as AuthUser;

class BkPolicy
{
    public function viewAny(AuthUser $u): bool { return $u->can('ViewAny:Bk'); }
    public function view(AuthUser $u, Bk $r): bool { return $u->can('View:Bk'); }
    public function create(AuthUser $u): bool { return $u->can('Create:Bk'); }
    public function update(AuthUser $u, Bk $r): bool { return $u->can('Update:Bk'); }
    public function delete(AuthUser $u, Bk $r): bool { return $u->can('Delete:Bk'); }
    public function deleteAny(AuthUser $u): bool { return $u->can('Delete:Bk'); }
}
