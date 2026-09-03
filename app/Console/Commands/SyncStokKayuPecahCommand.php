<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KayuPecahRotary;
use App\Models\StokKayuPecahRotary;
use App\Models\LogStokKayuPecahRotary;
use App\Services\StokKayuPecahService;
use Illuminate\Support\Facades\DB;

/**
 * Class SyncStokKayuPecahCommand
 *
 * Command Artisan untuk melakukan sinkronisasi data kayu pecah dari record
 * Lahan Rotary atau mengisi stok awal kayu pecah secara manual.
 */
class SyncStokKayuPecahCommand extends Command
{
    /**
     * Nama dan signature dari command terminal.
     *
     * Penggunaan:
     * 1. php artisan stok:sync-kayu-pecah          (Sinkronisasi dari data KayuPecahRotary yang ada)
     * 2. php artisan stok:sync-kayu-pecah --manual   (Pengisian data manual interaktif)
     *
     * @var string
     */
    protected $signature = 'stok:sync-kayu-pecah 
                            {--manual : Mode pengisian stok manual secara interaktif} 
                            {--fresh : Reset total tabel stok & log kayu pecah sebelum sync}';

    /**
     * Deskripsi command yang muncul di php artisan list.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi stok kayu pecah rotary riil atau pengisian stok manual';

    /**
     * Eksekusi logika utama command.
     */
    public function handle(StokKayuPecahService $stokService): int
    {
        $this->info('====================================================');
        $this->info('  COMMAND SINKRONISASI STOK KAYU PECAH ROTARY       ');
        $this->info('====================================================');

        // Opsi fresh: Hapus data stok lama jika flag --fresh digunakan
        if ($this->option('fresh')) {
            if ($this->confirm('⚠️ Apakah Anda yakin ingin MENGHAPUS SEMUA STOK & LOG kayu pecah?', false)) {
                DB::transaction(function () {
                    StokKayuPecahRotary::truncate();
                    LogStokKayuPecahRotary::truncate();
                });
                $this->warn('✓ Tabel stok_kayu_pecah_rotaries & log_stok_kayu_pecah_rotaries telah di-reset!');
            }
        }

        // Jalankan mode manual jika flag --manual dipanggil
        if ($this->option('manual')) {
            return $this->handleManualInput($stokService);
        }

        // Mode Otomatis: Sinkronisasi dari record KayuPecahRotary existing
        return $this->handleAutoSync();
    }

    /**
     * Mode Otomatis: Membaca dari tabel `kayu_pecah_rotaries` yang belum diproses.
     */
    private function handleAutoSync(): int
    {
        $this->info('Mengambil data kayu pecah dari riwayat transaksi lahan...');

        try {
            // Ambil data kayu pecah beserta relasi penggunaanLahan
            $records = KayuPecahRotary::with('penggunaanLahan')
                ->where('status_proses', 'belum_digraji')
                ->get();

            if ($records->isEmpty()) {
                $this->warn('Tidak ditemukan data kayu pecah riil yang belum diproses.');
                $this->comment('Tips: Anda bisa menggunakan `php artisan stok:sync-kayu-pecah --manual` untuk menginput stok.');
                return self::SUCCESS;
            }

            // Kelompokkan data di tingkat Eloquent Collection (mencegah error missing column pada SQL SELECT)
            $rekapKayuPecah = $records->groupBy(function ($item) {
                $idJenis = $item->id_jenis_kayu
                    ?? $item->penggunaanLahan?->id_jenis_kayu
                    ?? 1;
                $panjang = $item->panjang ?? 130;
                return "{$idJenis}_{$panjang}";
            });

            $this->output->progressStart($rekapKayuPecah->count());

            DB::transaction(function () use ($rekapKayuPecah) {
                foreach ($rekapKayuPecah as $groupKey => $items) {
                    $first = $items->first();
                    $idJenisKayu = $first->id_jenis_kayu
                        ?? $first->penggunaanLahan?->id_jenis_kayu
                        ?? 1;
                    $panjang = (int) ($first->panjang ?? 130);
                    $totalBatang = $items->count();

                    // Update atau buat stok aktif
                    $stokRecord = StokKayuPecahRotary::firstOrCreate(
                        [
                            'id_jenis_kayu' => $idJenisKayu,
                            'panjang'       => $panjang,
                        ],
                        [
                            'stok_batang'   => 0,
                        ]
                    );

                    $before = (int) $stokRecord->stok_batang;
                    $after  = $before + $totalBatang;

                    $stokRecord->update(['stok_batang' => $after]);

                    // Catat ke Log
                    LogStokKayuPecahRotary::create([
                        'id_jenis_kayu' => $idJenisKayu,
                        'panjang'       => $panjang,
                        'tipe'          => 'masuk',
                        'jumlah_batang' => $totalBatang,
                        'stok_before'   => $before,
                        'stok_after'    => $after,
                        'keterangan'    => 'Sinkronisasi Otomatis dari Data Lahan Rotary Existing',
                    ]);

                    $this->output->progressAdvance();
                }
            });

            $this->output->progressFinish();
            $this->info('✅ Sinkronisasi stok kayu pecah berhasil diproses!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Gagal melakukan sync otomatis: ' . $e->getMessage());
            $this->comment('Gunakan opsi manual: `php artisan stok:sync-kayu-pecah --manual`');
            return self::FAILURE;
        }
    }

    /**
     * Mode Manual: Menginput stok kayu pecah interaktif via pertanyaan terminal.
     */
    private function handleManualInput(StokKayuPecahService $stokService): int
    {
        $this->info('Mode Pengisian Manual Interaktif:');

        $jenisKayuList = \App\Models\JenisKayu::pluck('nama_kayu', 'id')->toArray();

        if (empty($jenisKayuList)) {
            $this->error('Gagal: Belum ada master Jenis Kayu di database.');
            return self::FAILURE;
        }

        $idJenisKayuChoice = $this->choice(
            'Pilih Jenis Kayu:',
            $jenisKayuList
        );

        // Ambil ID jenis kayu berdasarkan pilihan nama
        $idJenisKayu = array_search($idJenisKayuChoice, $jenisKayuList);

        $panjang = (int) $this->ask('Masukkan Panjang Kayu dalam CM (misal: 130 atau 260)', '130');
        $qty     = (int) $this->ask('Masukkan Jumlah Batang Kayu Pecah yang Ditambahkan', '10');

        if ($qty <= 0 || $panjang <= 0) {
            $this->error('Input tidak valid. Panjang dan QTY harus lebih dari 0.');
            return self::FAILURE;
        }

        // Eksekusi penambahan stok
        $stokService->tambahStok(
            idJenisKayu: $idJenisKayu,
            panjang: $panjang,
            qty: $qty,
            keterangan: 'Penambahan Stok Manual via Command Terminal'
        );

        $this->info("✅ Berhasil menambahkan stok {$qty} batang ({$idJenisKayuChoice} - {$panjang} CM).");

        return self::SUCCESS;
    }
}
