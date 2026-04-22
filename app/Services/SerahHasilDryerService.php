<?php

namespace App\Services;

use App\Models\DetailHasil;
use App\Models\DetailMasuk;
use App\Models\HppAverageSummarie;
use App\Models\HppLogHarian;
use App\Models\StokVeneerKering;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SerahHasilDryerService
{
    public function serahkan(DetailHasil $record): void
    {
        DB::transaction(function () use ($record) {
            $ukuran = $record->ukuran;

            if (!$ukuran) {
                throw new \Exception("Gagal: Dimensi ukuran palet tidak ditemukan.");
            }

            $tanggalHariIni = Carbon::today()->toDateString();

            // ── 1. Hitung m3 palet ini ────────────────────────────────────────
            $m3Masuk = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $record->isi)
                / 1_000_000;

            // ── 2. Snapshot stok m3 terakhir dari stok_veneer_kerings ─────────
            $snapshotStok = StokVeneerKering::snapshotTerakhir(
                $record->id_ukuran,
                $record->id_jenis_kayu,
                $record->kw
            );

            $m3Sebelum    = $snapshotStok['stok_m3']     ?? 0.0;
            $nilaiSebelum = $snapshotStok['nilai_stok']  ?? 0.0;
            $hppAverage   = $snapshotStok['hpp_average'] ?? 0.0;

            // ── 3. Ambil saldo lembar terkini (SUM masuk - SUM keluar) ────────
            $lembarSebelum = StokVeneerKering::saldoLembarTerakhir(
                $record->id_ukuran,
                $record->id_jenis_kayu,
                $record->kw
            );
            $lembarSesudah = $lembarSebelum + (int) $record->isi;

            // ── 4. Hitung HPP veneer basah (Opsi C: weighted average modal) ───
            $hppVeneerBasah = $this->hitungHppVeneerBasah($record);

            // ── 5. Bangun keterangan lengkap ──────────────────────────────────
            $shift           = $record->produksiDryer?->shift ?? '-';
            $tanggalProduksi = $record->produksiDryer?->tanggal_produksi
                ? Carbon::parse($record->produksiDryer->tanggal_produksi)->format('d/m/Y')
                : '-';

            $keterangan = "MASUK DARI DRYER: No. Palet {$record->no_palet} | Shift: {$shift} | Tgl Produksi: {$tanggalProduksi}";

            // ── 6. Insert ke stok_veneer_kerings (log per palet) ──────────────
            StokVeneerKering::create([
                'id_detail_hasil_dryer'   => $record->id,
                'id_ukuran'               => $record->id_ukuran,
                'id_jenis_kayu'           => $record->id_jenis_kayu,
                'kw'                      => $record->kw,
                'jenis_transaksi'         => 'masuk',
                'tanggal_transaksi'       => now(),
                'qty'                     => $record->isi,
                'm3'                      => round($m3Masuk, 6),
                // ✅ HPP basah dari weighted average modal
                'hpp_veneer_basah_per_m3' => $hppVeneerBasah,
                // Ongkos dryer & HPP kering = 0 dulu, diisi saat validasi
                'ongkos_dryer_per_m3'     => 0,
                'hpp_kering_per_m3'       => 0,
                'nilai_transaksi'         => 0,
                // Saldo lembar
                'stok_lembar_sebelum'     => $lembarSebelum,
                'stok_lembar_sesudah'     => $lembarSesudah,
                // Saldo m3
                'stok_m3_sebelum'         => round($m3Sebelum, 6),
                'stok_m3_sesudah'         => round($m3Sebelum + $m3Masuk, 6),
                'nilai_stok_sebelum'      => $nilaiSebelum,
                'nilai_stok_sesudah'      => $nilaiSebelum, // belum ada nilai, diisi saat validasi
                'hpp_average'             => 0,             // diisi saat validasi
                'keterangan'              => $keterangan,
            ]);

            // ── 7. UpdateOrCreate hpp_log_veneer_kering (ringkasan harian) ────
            $logHariIni = HppLogHarian::where('id_ukuran', $record->id_ukuran)
                ->where('id_jenis_kayu', $record->id_jenis_kayu)
                ->where('kw', $record->kw)
                ->whereDate('tanggal', $tanggalHariIni)
                ->first();

            if ($logHariIni) {
                $logHariIni->update([
                    'total_lembar_masuk'      => $logHariIni->total_lembar_masuk + (int) $record->isi,
                    'total_m3_masuk'          => round($logHariIni->total_m3_masuk + $m3Masuk, 6),
                    'stok_akhir_lembar'       => $lembarSesudah,
                    'stok_akhir_m3'           => round($m3Sebelum + $m3Masuk, 6),
                    'hpp_veneer_basah_per_m3' => 0,
                    'avg_ongkos_dryer_per_m3' => 0,
                    'hpp_kering_per_m3'       => 0,
                    'hpp_average'             => 0,
                    'nilai_stok_akhir'        => 0,
                ]);
            } else {
                HppLogHarian::create([
                    'tanggal'                 => $tanggalHariIni,
                    'id_ukuran'               => $record->id_ukuran,
                    'id_jenis_kayu'           => $record->id_jenis_kayu,
                    'kw'                      => $record->kw,
                    'stok_awal_lembar'        => $lembarSebelum,
                    'total_lembar_masuk'      => (int) $record->isi,
                    'total_lembar_keluar'     => 0,
                    'stok_akhir_lembar'       => $lembarSesudah,
                    'total_m3_masuk'          => round($m3Masuk, 6),
                    'total_m3_keluar'         => 0,
                    'stok_akhir_m3'           => round($m3Sebelum + $m3Masuk, 6),
                    'hpp_veneer_basah_per_m3' => 0,
                    'avg_ongkos_dryer_per_m3' => 0,
                    'hpp_kering_per_m3'       => 0,
                    'hpp_average'             => 0,
                    'nilai_stok_akhir'        => 0,
                ]);
            }
        });
    }

    // =========================================================================
    // PRIVATE HELPER
    // =========================================================================

    /**
     * Hitung HPP veneer basah menggunakan weighted average dari semua modal
     * (DetailMasuk) dalam produksi dryer yang sama, per kombinasi
     * id_ukuran + id_jenis_kayu.
     *
     * Formula:
     *   weighted_avg = SUM(isi × hpp_basah) / SUM(isi)
     */
    private function hitungHppVeneerBasah(DetailHasil $record): float
    {
        // Ambil semua modal dari produksi ini dengan ukuran + jenis kayu sama
        $modals = DetailMasuk::where('id_produksi_dryer', $record->id_produksi_dryer)
            ->where('id_ukuran', $record->id_ukuran)
            ->where('id_jenis_kayu', $record->id_jenis_kayu)
            ->with('ukuran')
            ->get();

        if ($modals->isEmpty()) {
            Log::warning('SerahHasilDryerService: tidak ada modal ditemukan', [
                'id_produksi_dryer' => $record->id_produksi_dryer,
                'id_ukuran'         => $record->id_ukuran,
                'id_jenis_kayu'     => $record->id_jenis_kayu,
            ]);
            return 0.0;
        }

        $totalIsi    = 0;
        $totalNilai  = 0.0;

        foreach ($modals as $modal) {
            $panjang = $modal->ukuran?->panjang ?? 0;

            // Ambil HPP dari hpp_average_summaries
            // berdasarkan id_jenis_kayu + panjang + grade (kw modal)
            $summary = HppAverageSummarie::where('id_jenis_kayu', $modal->id_jenis_kayu)
                ->where('panjang', $panjang)
                ->where('grade', (string) $modal->kw)
                ->orderByDesc('updated_at')
                ->first();

            $hppBasah = $summary ? (float) $summary->hpp_average : 0.0;
            $isi      = (int) $modal->isi;

            $totalIsi   += $isi;
            $totalNilai += $isi * $hppBasah;
        }

        if ($totalIsi <= 0) {
            return 0.0;
        }

        return round($totalNilai / $totalIsi, 4);
    }
}