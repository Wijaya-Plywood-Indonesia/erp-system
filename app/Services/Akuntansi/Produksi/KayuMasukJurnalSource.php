<?php

namespace App\Services\Akuntansi\Produksi;

use App\Exports\LaporanJurnalKayuMasukSheet2New;
use App\Filament\Pages\LaporanJurnalKayuMasuk;
use Carbon\Carbon;

/**
 * Jurnal Kayu Masuk (pembelian kayu per nota).
 *
 * Data nota dibangun oleh halaman Laporan Jurnal Kayu Masuk (loadData()),
 * lalu diubah jadi baris jurnal oleh LaporanJurnalKayuMasukSheet2New, sheet
 * "jurnal produksi v2" di Export Jurnal Kayu Masuk. Dengan begitu angkanya
 * identik dengan Excel.
 *
 * Tanggal yang dipilih = tanggal nota dilunasi ("Lunas - dd/mm/yyyy"),
 * sama dengan halaman laporannya. Satu jurnal per nota:
 * MASUK/{Ymd}/{no_nota}.
 */
class KayuMasukJurnalSource implements JurnalProduksiSource
{
    public function key(): string
    {
        return 'kayu_masuk';
    }

    public function label(): string
    {
        return 'Kayu Masuk';
    }

    public function build(string $tanggal): array
    {
        $tgl = Carbon::parse($tanggal)->format('Y-m-d');

        $halaman = app(LaporanJurnalKayuMasuk::class);
        $halaman->tanggal = $tgl;
        $halaman->loadData();

        if (empty($halaman->jurnalTables)) {
            return [];
        }

        $sheet = new LaporanJurnalKayuMasukSheet2New($halaman->jurnalTables);

        return JurnalBerjudulRows::toJurnals($sheet->collection(), $tgl);
    }
}