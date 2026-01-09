<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class GenerateDbml extends Command
{
    protected $signature = 'dbml:generate {--force : Paksa render ulang semua file meskipun sudah ada}';
    protected $description = 'Render semua file .dbml di folder relations/input menjadi PNG';

    public function handle()
    {
        // 1. Tentukan Direktori
        $inputDir = base_path('relations/input');
        $outputDir = base_path('relations/output');

        // Pastikan folder output ada
        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        // 2. Ambil semua file .dbml
        $files = File::glob($inputDir . '/*.dbml');

        if (empty($files)) {
            $this->error("Tidak ada file .dbml ditemukan di: $inputDir");
            return;
        }

        $this->info("Ditemukan " . count($files) . " file DBML. Memulai proses...");

        foreach ($files as $file) {
            // Dapatkan nama file (misal: 'master')
            $filename = pathinfo($file, PATHINFO_FILENAME);

            // --- KONFIGURASI EKSTENSI ---
            // Kita set ke PNG sesuai permintaan
            $extension = 'png';

            $inputPath = $file;
            // Output path disesuaikan dengan ekstensi
            $outputPath = "$outputDir/$filename.$extension";

            // 3. LOGIKA CEK FILE (SKIP JIKA ADA)
            // Cek apakah file .png sudah ada
            if (File::exists($outputPath) && !$this->option('force')) {
                $this->line("<comment>SKIP</comment>   : $filename.$extension (Sudah ada)");
                continue;
            }

            $this->line("<info>RENDER</info> : $filename.$extension ...");

            // 4. Jalankan Perintah Render
            // Pastikan renderer mendukung output png lewat ekstensi file
            $command = "npx dbml-renderer -i \"$inputPath\" -o \"$outputPath\"";

            $result = Process::run($command);

            if ($result->successful()) {
                $this->info("       ✓ Berhasil");
            } else {
                // Tampilkan error jika gagal
                $this->error("       ✗ Gagal: " . $result->errorOutput());
            }
        }

        $this->newLine();
        $this->info("Selesai.");
    }
}
