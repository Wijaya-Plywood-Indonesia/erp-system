<?php

namespace App\Services\Akuntansi\Produksi;

use App\Exports\Sheets\JurnalSheetV2;
use App\Filament\Pages\LaporanJoin\Queries\LoadLaporanJoin;
use Carbon\Carbon;

/**
 * Jurnal produksi Join (nyambung veneer).
 *
 * Data diambil dengan query yang sama dengan Laporan Join
 * (LoadLaporanJoin::run) lalu diubah jadi baris jurnal oleh
 * App\Exports\Sheets\JurnalSheetV2.
 *
 * Satu jurnal per catatan produksi join (ProduksiJoint) pada tanggal itu:
 * JOIN/{Ymd}/{id produksi}. Sheet dijalankan per produksi supaya nomor
 * jurnal tetap menempel ke produksinya walaupun ada produksi yang kosong.
 *
 * PERHATIAN: sengaja memakai App\Exports\Sheets\JurnalSheetV2 (file sendiri),
 * BUKAN class JurnalSheetV2 yang ada di dalam LaporanJoinExport.php. File itu
 * juga mendeklarasikan class "JurnalSheet" yang namanya bentrok dengan
 * LaporanRepairExport.php; memuat keduanya dalam satu request menyebabkan
 * fatal error, padahal menu kirim jurnal memuat semua jenis produksi.
 */
class JoinJurnalSource implements JurnalProduksiSource
{
    public function key(): string
    {
        return 'join';
    }

    public function label(): string
    {
        return 'Produksi Join';
    }

    public function build(string $tanggal): array
    {
        $tgl = Carbon::parse($tanggal)->format('Y-m-d');
        $prefix = 'JOIN/'.str_replace('-', '', $tgl).'/';

        $jurnals = [];

        foreach (LoadLaporanJoin::run($tgl)->sortBy('id') as $produksi) {
            $sheetRows = (new JurnalSheetV2(collect([$produksi])))->array();
            $noDokumen = $prefix.$produksi->id;

            foreach (JurnalSheetRows::toJurnals($sheetRows, $tgl, fn () => $noDokumen) as $jurnal) {
                $jurnals[] = $jurnal; // paling banyak satu per produksi (tidak ada baris pemisah di tengah)
            }
        }

        return $jurnals;
    }
}