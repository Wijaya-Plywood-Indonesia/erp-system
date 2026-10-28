<?php

namespace App\Console\Commands;

use App\Services\ValidasiOtomatisProduksiService;
use Illuminate\Console\Command;

class ValidasiOtomatisProduksiCommand extends Command
{
    protected $signature = 'produksi:validasi-otomatis {--dry : Hanya tampilkan produksi yang akan divalidasi, tanpa menyimpan}';

    protected $description = 'Validasi otomatis semua produksi yang sudah melewati jadwal (jadwal & menu diatur di menu Pengaturan Validasi Otomatis)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $p = ValidasiOtomatisProduksiService::pengaturan();

        if (! $p['aktif']) {
            $this->info('Validasi otomatis sedang nonaktif (atur di menu Pengaturan Validasi Otomatis).');

            return self::SUCCESS;
        }

        $hasil = ValidasiOtomatisProduksiService::jalankan($dry);

        if (! $hasil) {
            $this->info('Tidak ada produksi yang perlu divalidasi saat ini.');

            return self::SUCCESS;
        }

        $this->table(['Menu', 'ID', 'Tanggal produksi'], $hasil);
        $this->info(($dry ? '[DRY] Akan divalidasi: ' : 'Divalidasi: ') . count($hasil) . ' produksi.');

        return self::SUCCESS;
    }
}
