<?php

namespace App\Services\Akuntansi\Produksi;

use App\Exports\Sheets\LaporanProduksiHotPressJurnalSheetV2;
use App\Models\ProduksiHp;
use Carbon\Carbon;
use RuntimeException;

/**
 * Jurnal produksi Hot Press.
 *
 * Baris jurnal: App\Exports\Sheets\LaporanProduksiHotPressJurnalSheetV2
 * (sheet yang sama dengan Export Excel Laporan Hot Press), domain diambil dari
 * host request seperti di halaman Laporan Hot Press.
 *
 * Satu jurnal per catatan produksi HP (ProduksiHp, biasanya per shift):
 * HP/{Ymd}/{id produksi}. Sheet menulis satu blok per produksi dan
 * memisahkannya dengan baris kosong, urut sama dengan query
 * ProduksiHp whereDate(tanggal_produksi); blok ke-N dipasangkan dengan
 * produksi ke-N.
 */
class HotPressJurnalSource implements JurnalProduksiSource
{
    public function key(): string
    {
        return 'hotpress';
    }

    public function label(): string
    {
        return 'Produksi Hot Press';
    }

    public function build(string $tanggal): array
    {
        $tgl = Carbon::parse($tanggal)->format('Y-m-d');
        $prefix = 'HP/'.str_replace('-', '', $tgl).'/';

        $ids = ProduksiHp::whereDate('tanggal_produksi', $tgl)->pluck('id')->values()->all();
        if (! $ids) {
            return [];
        }

        $sheetRows = (new LaporanProduksiHotPressJurnalSheetV2($tgl, request()->getHost()))->array();

        // Pecah per blok (dipisah baris kosong), lewati header.
        $blok = [];
        $kini = [];
        foreach (array_slice($sheetRows, 1) as $row) {
            if (trim(implode('', array_map('strval', (array) $row))) === '') {
                $blok[] = $kini;
                $kini = [];
            } else {
                $kini[] = $row;
            }
        }
        if ($kini) {
            $blok[] = $kini;
        }

        if (count($blok) !== count($ids)) {
            throw new RuntimeException('Jumlah blok jurnal Hot Press ('.count($blok).') tidak sama dengan jumlah produksi ('.count($ids).').');
        }

        $jurnals = [];
        foreach ($blok as $i => $rows) {
            if (! $rows) {
                continue;
            }
            $noDokumen = $prefix.$ids[$i];
            foreach (JurnalSheetRows::toJurnals($rows, $tgl, fn () => $noDokumen) as $j) {
                $jurnals[] = $j;
            }
        }

        return $jurnals;
    }
}