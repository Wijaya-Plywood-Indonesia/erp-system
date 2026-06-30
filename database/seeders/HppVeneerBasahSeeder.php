<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HppVeneerBasahLog;
use App\Models\HppVeneerBasahSummary;
use App\Models\HppVeneerBasahBahanPenolong;

/**
 * HppVeneerBasahSeeder
 *
 * Mengisi stok veneer basah dummy untuk produksi tgl 15/01/2026.
 *
 * Jalankan:
 *   php artisan db:seed --class=HppVeneerBasahSeeder
 *
 * Reset:
 *   App\Models\HppVeneerBasahSummary::truncate();
 *   App\Models\HppVeneerBasahBahanPenolong::truncate();
 *   App\Models\HppVeneerBasahLog::truncate();
 */
class HppVeneerBasahSeeder extends Seeder
{
    /**
     * Data stok veneer basah per kombinasi ukuran + jenis kayu.
     *
     * Produksi tgl 15/01/2026:
     *   - SPINDLESS + MERANTI  → 244×122×0.5  (F/B)  → id_ukuran=1
     *   - SANJI                → 122×244×3.7  (Core) → id_ukuran=4
     *
     * HPP dihitung dari:
     *   hpp_kayu    = totalPoinKayu / kubikasiTotal65%
     *   hpp_pekerja = totalUpah / kubikasiTotal
     *   hpp_mesin   = totalOngkosMesin / kubikasiTotal
     *   hpp_bahan   = totalNilaiBahan / kubikasiTotal
     */
    const TANGGAL = '2026-01-15';

    const DATA = [
        // 244×122×0.5mm — Sengon — semua mesin (SPINDLESS+MERANTI+SANJI) = 1560 lembar
        [
            'id_jenis_kayu' => 1,   // Sengon
            'panjang'       => 244,
            'lebar'         => 122,
            'tebal'         => 0.5,
            'total_lembar'  => 1560, // SPINDLESS(200+150+180) + MERANTI(220+160) + SANJI(300+250+100)
            // HPP dihitung dari data nyata produksi tgl 15/01/2026
            // kubikasi = 244×122×0.5×1560/10.000.000 = 2.321904 m³
            'hpp_kayu'      => 964018,   // hpp_average lahan 130 (Sengon) — upah kayu per m³ veneer
            'hpp_pekerja'   => 0,        // upah pegawai belum diisi di data dummy
            'hpp_mesin'     => 792453,   // (835000+835000+170000) / 2.321904
            'hpp_bahan'     => 36177,    // (2 roll × 42000) / 2.321904
            'bahan'         => [
                // MERANTI: 2 roll Reeling Tape @ 42.000
                ['nama' => 'Reeling Tape', 'jumlah' => 2, 'harga' => 42000],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::DATA as $row) {
            $hppAverage  = $row['hpp_kayu'] + $row['hpp_pekerja'] + $row['hpp_mesin'] + $row['hpp_bahan'];

            // Hitung kubikasi: p × l × t × lembar / 10.000.000
            $kubikasi   = round(($row['panjang'] * $row['lebar'] * $row['tebal'] * $row['total_lembar']) / 10_000_000, 6);
            $nilaiMasuk = round($hppAverage * $kubikasi, 2);

            // Cek summarie existing (untuk moving average)
            $summarie = HppVeneerBasahSummary::firstOrNew([
                'id_jenis_kayu' => $row['id_jenis_kayu'],
                'panjang'       => $row['panjang'],
                'lebar'         => $row['lebar'],
                'tebal'         => $row['tebal'],
            ]);

            $lembarBefore   = (int)   ($summarie->stok_lembar   ?? 0);
            $kubikasiBefore = (float) ($summarie->stok_kubikasi ?? 0);
            $nilaiBefore    = (float) ($summarie->nilai_stok    ?? 0);
            $hppLama        = (float) ($summarie->hpp_average   ?? 0);

            // Moving average
            $nilaiLama      = $hppLama * $kubikasiBefore;
            $hppAverageBaru = ($kubikasiBefore + $kubikasi) > 0
                ? round(($nilaiLama + $nilaiMasuk) / ($kubikasiBefore + $kubikasi), 2)
                : $hppAverage;

            $lembarAfter   = $lembarBefore + $row['total_lembar'];
            $kubikasiAfter = round($kubikasiBefore + $kubikasi, 6);
            $nilaiAfter    = round($hppAverageBaru * $kubikasiAfter, 2);

            // Total nilai bahan untuk hpp_per_m3
            $totalNilaiBahan = array_sum(array_map(
                fn($b) => $b['jumlah'] * $b['harga'],
                $row['bahan']
            ));

            // Insert log
            $log = HppVeneerBasahLog::create([
                'id_jenis_kayu'        => $row['id_jenis_kayu'],
                'panjang'              => $row['panjang'],
                'lebar'                => $row['lebar'],
                'tebal'                => $row['tebal'],
                'tanggal'              => self::TANGGAL,
                'tipe_transaksi'       => 'masuk',
                'keterangan'           => 'Produksi rotary tgl 15/01/2026 (seeder)',
                'referensi_type'       => null,
                'referensi_id'         => null,
                'total_lembar'         => $row['total_lembar'],
                'total_kubikasi'       => $kubikasi,
                'hpp_kayu'             => $row['hpp_kayu'],
                'hpp_pekerja'          => $row['hpp_pekerja'],
                'hpp_mesin'            => $row['hpp_mesin'],
                'hpp_bahan_penolong'   => $row['hpp_bahan'],
                'hpp_average'          => $hppAverageBaru,
                'nilai_stok'           => $nilaiMasuk,
                'stok_lembar_before'   => $lembarBefore,
                'stok_kubikasi_before' => round($kubikasiBefore, 6),
                'nilai_stok_before'    => $nilaiBefore,
                'stok_lembar_after'    => $lembarAfter,
                'stok_kubikasi_after'  => $kubikasiAfter,
                'nilai_stok_after'     => $nilaiAfter,
            ]);

            // Insert breakdown bahan penolong
            foreach ($row['bahan'] as $bahan) {
                // Cari id dari bahan_penolong_produksi via nama
                $master = \App\Models\BahanPenolongProduksi::where('nama_bahan_penolong', $bahan['nama'])->first();

                HppVeneerBasahBahanPenolong::create([
                    'id_log'            => $log->id,
                    'bahan_penolong_id' => $master?->id ?? 1,
                    'nama_bahan'        => $bahan['nama'],
                    'satuan'            => 'roll',
                    'jumlah'            => $bahan['jumlah'],
                    'harga_satuan'      => $bahan['harga'],
                    'nilai_total'       => $bahan['jumlah'] * $bahan['harga'],
                    'hpp_per_m3'        => $kubikasi > 0
                        ? round(($bahan['jumlah'] * $bahan['harga']) / $kubikasi, 4)
                        : 0,
                ]);
            }

            // Upsert summarie
            $summarie->fill([
                'stok_lembar'             => $lembarAfter,
                'stok_kubikasi'           => $kubikasiAfter,
                'nilai_stok'              => $nilaiAfter,
                'hpp_average'             => $hppAverageBaru,
                'hpp_kayu_last'           => $row['hpp_kayu'],
                'hpp_pekerja_last'        => $row['hpp_pekerja'],
                'hpp_mesin_last'          => $row['hpp_mesin'],
                'hpp_bahan_penolong_last' => $row['hpp_bahan'],
                'id_last_log'             => $log->id,
            ])->save();

            $this->command->info(
                "OK  {$row['panjang']}×{$row['lebar']}×{$row['tebal']} | " .
                "{$row['total_lembar']} lbr | {$kubikasi} m³ | hpp " .
                number_format($hppAverageBaru)
            );
        }

        $this->command->newLine();
        $this->command->info('Seeder selesai. Sesuaikan nilai hpp_kayu, hpp_pekerja, hpp_mesin, hpp_bahan dengan data nyata Anda.');
    }
}