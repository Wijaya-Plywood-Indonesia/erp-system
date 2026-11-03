<?php

namespace App\Services\Akuntansi\Produksi;

use App\Exports\Sheets\JurnalDryerSheetV2;
use App\Filament\Pages\LaporanPressDryer\Queries\LoadPressDryer;
use App\Filament\Pages\LaporanPressDryer\Transformers\DryerDataMap;
use Carbon\Carbon;

/**
 * Jurnal produksi Dryer.
 *
 * Memakai alur yang SAMA dengan tombol "Download Excel" di Laporan Produksi
 * Dryer (LoadPressDryer -> DryerDataMap -> JurnalDryerSheetV2), sehingga angka,
 * akun, dan harga identik dengan sheet "jurnal produksi v2" yang selama ini
 * diexport lalu diimport manual.
 *
 * Satu jurnal per shift: DRY/{Ymd}/PAGI dan DRY/{Ymd}/MALAM.
 */
class DryerJurnalSource implements JurnalProduksiSource
{
    public function key(): string
    {
        return 'dryer';
    }

    public function label(): string
    {
        return 'Produksi Dryer';
    }

    public function build(string $tanggal): array
    {
        $tgl = Carbon::parse($tanggal)->format('Y-m-d');

        $dataProduksi = DryerDataMap::make(LoadPressDryer::run($tgl));

        if (empty($dataProduksi)) {
            return [];
        }

        $sheetRows = (new JurnalDryerSheetV2($dataProduksi))->array();
        $prefix = 'DRY/'.str_replace('-', '', $tgl).'/';

        return JurnalSheetRows::toJurnals(
            $sheetRows,
            $tgl,
            function (array $baris, int $urutan) use ($prefix): string {
                // kolom "nama" berisi 'dryer pagi' / 'dryer malam'
                $shift = strtoupper(trim(str_ireplace('dryer', '', $baris['nama'])));

                return $prefix.($shift !== '' ? $shift : 'SHIFT'.($urutan + 1));
            }
        );
    }
}
