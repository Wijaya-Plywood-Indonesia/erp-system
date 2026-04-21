<?php

namespace App\Services;

use App\Models\DetailHasil;
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

            Log::info('DEBUG serah palet', [
    'id_ukuran'      => $record->id_ukuran,
    'id_jenis_kayu'  => $record->id_jenis_kayu,
    'kw'             => $record->kw,
    'kw_type'        => gettype($record->kw),
    'lembar_sebelum' => $lembarSebelum,
    'lembar_sesudah' => $lembarSesudah,
    'isi'            => $record->isi,
]);

            // ── 4. Bangun keterangan lengkap ──────────────────────────────────
            $shift           = $record->produksiDryer?->shift ?? '-';
            $tanggalProduksi = $record->produksiDryer?->tanggal_produksi
                ? Carbon::parse($record->produksiDryer->tanggal_produksi)->format('d/m/Y')
                : '-';

            $keterangan = "MASUK DARI DRYER: No. Palet {$record->no_palet} | Shift: {$shift} | Tgl Produksi: {$tanggalProduksi}";

            // ── 5. Insert ke stok_veneer_kerings (log per palet) ──────────────
            StokVeneerKering::create([
                'id_detail_hasil_dryer' => $record->id,
                'id_ukuran'             => $record->id_ukuran,
                'id_jenis_kayu'         => $record->id_jenis_kayu,
                'kw'                    => $record->kw,
                'jenis_transaksi'       => 'masuk',
                'tanggal_transaksi'     => now(),
                'qty'                   => $record->isi,
                'm3'                    => round($m3Masuk, 6),
                // ✅ Saldo lembar sebelum → sesudah
                'stok_lembar_sebelum'   => $lembarSebelum,
                'stok_lembar_sesudah'   => $lembarSesudah,
                // Saldo m3
                'stok_m3_sebelum'       => round($m3Sebelum, 6),
                'stok_m3_sesudah'       => round($m3Sebelum + $m3Masuk, 6),
                'nilai_stok_sebelum'    => $nilaiSebelum,
                'nilai_stok_sesudah'    => $nilaiSebelum,
                'hpp_average'           => $hppAverage,
                'keterangan'            => $keterangan,
            ]);

            // ── 6. UpdateOrCreate hpp_log_veneer_kering (ringkasan harian) ────
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
}