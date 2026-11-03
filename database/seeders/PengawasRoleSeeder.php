<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PengawasRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions yang dibutuhkan untuk role pengawas.
        // Format: 'View:PageClassName' untuk pages, 'ViewAny:ModelName' untuk resources.
        $permissions = [
            // Absen dan Gaji
            'View:NewAbsensi',                    // Rekap Absensi Pegawai
            'View:PengawasRekapPotonganGaji',     // Rekap Potongan Gaji

            // Laporan
            'View:LaporanHub',                    // Pusat Laporan
            'View:RekapStokVeneer',               // Rekap Stok Veneer

            // Master
            'View:PantauTarget',                  // Pantau Target

            // Logs - Activities (resource-based, via ActivityPolicy)
            'ViewAny:Activity',                   // Lihat daftar Activity Logs
            'View:Activity',                      // Lihat detail Activity Log
        ];

        // Buat permission jika belum ada
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // Buat role pengawas
        $role = Role::firstOrCreate([
            'name' => 'pengawas',
            'guard_name' => 'web',
        ]);

        // Assign semua permission ke role pengawas
        $role->syncPermissions($permissions);

        // Assign role pengawas ke SEMUA user
        $users = User::all();
        foreach ($users as $user) {
            if (! $user->hasRole('pengawas')) {
                $user->assignRole('pengawas');
            }
        }

        $this->command->info("Role 'pengawas' berhasil dibuat dengan " . count($permissions) . " permissions.");
        $this->command->info("Role 'pengawas' berhasil di-assign ke " . $users->count() . " user.");
    }
}
