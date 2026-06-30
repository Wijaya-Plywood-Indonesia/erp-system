<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan role Super Admin ada
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        // Ambil semua permission yang sudah ada di database
        $permissions = Permission::all();

        // Berikan semua permission itu ke role Super Admin
        $role->syncPermissions($permissions);
    }
}

