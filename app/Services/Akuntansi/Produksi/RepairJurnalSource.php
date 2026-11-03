<?php

namespace App\Services\Akuntansi\Produksi;

use App\Exports\Sheets\JurnalRepairSheetV2;
use App\Filament\Pages\LaporanRepairs\Queries\LoadLaporanRepairs;
use Carbon\Carbon;

/**
 * Jurnal produksi Repair.
 *
 * Data: LoadLaporanRepairs::run (query yang sama dengan Laporan Repair),
 * baris jurnal: App\Exports\Sheets\JurnalRepairSheetV2.
 *
 * Satu jurnal per catatan produksi repair (ProduksiRepair) pada tanggal itu:
 * REP/{Ymd}/{id produksi}. Sheet dijalankan per produksi supaya nomor
 * jurnal tetap menempel ke produksinya.
 *
 * Catatan: tidak memuat LaporanRepairExport.php (file itu mendeklarasikan
 * class "JurnalSheet" yang bentrok dengan LaporanJoinExport.php).
 */
class RepairJurnalSource implements JurnalProduksiSource
{
    public function key(): string
    {
        return 'repair';
    }

    public function label(): string
    {
        return 'Produksi Repair';
    }

    public function build(string $tanggal): array
    {
        $tgl = Carbon::parse($tanggal)->format('Y-m-d');
        $prefix = 'REP/'.str_replace('-', '', $tgl).'/';

        $jurnals = [];

        foreach (LoadLaporanRepairs::run($tgl)->sortBy('id') as $produksi) {
            $sheetRows = (new JurnalRepairSheetV2(collect([$produksi])))->array();
            $noDokumen = $prefix.$produksi->id;

            foreach (JurnalSheetRows::toJurnals($sheetRows, $tgl, fn () => $noDokumen) as $jurnal) {
                $jurnals[] = $jurnal;
            }
        }

        return $jurnals;
    }
}