<?php

namespace App\Services\Akuntansi\Produksi;

use Carbon\Carbon;

/**
 * Jurnal Kayu Keluar (penggunaan kayu habis) pada produksi Rotary.
 *
 * Memakai LaporanProduksiKayuHabisSheetV2, sheet "jurnal produksi v2" di
 * Export Laporan Kayu Keluar. Satu jurnal per tanggal:
 * ROT/{Ymd}/KAYU_KELUAR (akhiran "_V2" dari judul sheet dibuang).
 */
class KayuKeluarJurnalSource implements JurnalProduksiSource
{
    public function key(): string
    {
        return 'kayu_keluar';
    }

    public function label(): string
    {
        return 'Kayu Keluar';
    }

    public function build(string $tanggal): array
    {
        $tgl = Carbon::parse($tanggal)->format('Y-m-d');

        // Class ini tidak punya file sendiri (ada di LaporanKayuKeluarExportV2.php),
        // sama seperti yang dilakukan LaporanKayuKeluarExport.
        require_once app_path('Exports/LaporanKayuKeluarExportV2.php');

        $sheet = new \App\Exports\LaporanProduksiKayuHabisSheetV2($tgl);

        return JurnalBerjudulRows::toJurnals(
            $sheet->collection(),
            $tgl,
            fn (string $no) => preg_replace('/_V2$/', '', $no)
        );
    }
}