<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AkunSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        $sqlPath = __DIR__ . '/akuntansi.sql';
        if (!file_exists($sqlPath)) {
            throw new \Exception("File akuntansi.sql tidak ditemukan di " . __DIR__);
        }

        // Matikan foreign key checks & hapus constraint sementara agar drop/recreate lancar
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        
        try {
            DB::statement("ALTER TABLE akun_group_anak_akun DROP FOREIGN KEY akun_group_anak_akun_anak_akun_id_foreign;");
        } catch (\Throwable $e) {
            // Abaikan jika table/constraint belum ada (misal di database fresh)
        }

        DB::statement('DROP TABLE IF EXISTS sub_anak_akuns;');
        DB::statement('DROP TABLE IF EXISTS anak_akuns;');
        DB::statement('DROP TABLE IF EXISTS induk_akuns;');

        // Jalankan SQL dump untuk membuat & mengisi kembali tabel induk_akuns, anak_akuns, sub_anak_akuns
        $sql = file_get_contents($sqlPath);
        DB::unprepared($sql);

        // Hidupkan kembali foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        // Buat kembali constraint di pivot table
        try {
            DB::statement("ALTER TABLE akun_group_anak_akun ADD CONSTRAINT akun_group_anak_akun_anak_akun_id_foreign FOREIGN KEY (anak_akun_id) REFERENCES anak_akuns (id) ON DELETE CASCADE;");
        } catch (\Throwable $e) {
            // Abaikan jika table pivot belum ter-migrate
        }
    }
}
