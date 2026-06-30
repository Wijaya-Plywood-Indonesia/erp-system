<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\File;

class GenerateDbml extends Command
{
    /**
     * Nama perintah yang akan diketik di terminal
     */
    protected $signature = 'dbml:generate {--force : Paksa render ulang semua file meskipun sudah ada}';

    /**
     * Deskripsi perintah
     */
    protected $description = 'Render DBML ke PNG menggunakan script worker Node.js (Sharp)';

    public function handle()
    {
        // 1. Tentukan Lokasi Script Worker
        $workerScript = base_path('resources/js/render-worker.js');

        // Cek apakah script worker ada (Safety check)
        if (!File::exists($workerScript)) {
            $this->error("Script worker tidak ditemukan!");
            $this->line("Pastikan file ada di: <comment>$workerScript</comment>");
            $this->line("Silakan buat file tersebut sesuai panduan sebelumnya.");
            return;
        }

        $this->info("🚀 Memulai proses render via Node.js...");

        // 2. Siapkan Perintah
        // Kita menyusun array perintah agar aman dari spasi/karakter aneh
        $command = ['node', $workerScript];

        // Teruskan flag --force jika user mengetiknya di artisan
        if ($this->option('force')) {
            $command[] = '--force';
        }

        // 3. Jalankan Proses (Panggil Node.js)
        // Kita set timeout 120 detik jaga-jaga jika file banyak
        $result = Process::timeout(120)->run($command);

        // 4. Tampilkan Output
        if ($result->successful()) {
            // Tampilkan log yang dikirim dari console.log() di Node.js
            $this->info($result->output());
            $this->newLine();
            $this->info("✅ Semua proses selesai.");
        } else {
            // Jika Node.js error (misal library sharp belum diinstall)
            $this->error("❌ Terjadi kesalahan saat menjalankan worker:");
            $this->error($result->errorOutput());

            // Kadang error Node.js muncul di standard output juga
            $this->line($result->output());
        }
    }
}
