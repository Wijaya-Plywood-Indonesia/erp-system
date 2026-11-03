<?php

namespace App\Services\Akuntansi\Produksi;

use App\Exports\LaporanProduksiJurnalSheetV2;
use Carbon\Carbon;

/**
 * Jurnal produksi Rotary (kupas veneer basah).
 *
 * Memakai LaporanProduksiJurnalSheetV2 (sheet "jurnal produksi v2" di Export
 * Laporan Produksi Rotary). Satu jurnal per mesin: ROT/{Ymd}/{MESIN}.
 * Kalau hasil validasi belum lengkap, jurnal tetap dibangun dari data saat
 * ini dan dikirim ulang (menimpa draft) setelah data lengkap.
 */
class RotaryJurnalSource implements JurnalProduksiSource
{
    public function key(): string
    {
        return 'rotary';
    }

    public function label(): string
    {
        return 'Produksi Rotary';
    }

    public function build(string $tanggal): array
    {
        $tgl = Carbon::parse($tanggal)->format('Y-m-d');

        $rows = (new LaporanProduksiJurnalSheetV2($tgl))->collection();

        return JurnalBerjudulRows::toJurnals($rows, $tgl);
    }
}