<?php

namespace App\Filament\Pages\LaporanPotSiku\Transformers;

use App\Models\ProduksiPotSiku;
use App\Models\Target;
use Carbon\Carbon;

class PotSikuDataMap
{
    public static function make(ProduksiPotSiku $produksi): array
    {
        /* ===============================
         * BASIC
         * =============================== */
        $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

        /* ===============================
         * TARGET
         * =============================== */
        $target = Target::where('kode_ukuran', 'POT SIKU')->first();
        $targetHarian = $target?->target ?? 0;

        /* ===============================
         * DETAIL PRODUKSI
         * =============================== */
        $details = $produksi->detailBarangDikerjakanPotSiku ?? collect();

        /* ===============================
         * TOTAL HASIL (SUM TINGGI)
         * =============================== */
        $hasilProduksi = $details->sum(fn ($d) => (int) ($d->tinggi ?? 0));

        /* ===============================
         * GROUP BARANG
         * =============================== */
        $grouped = $details->groupBy(function ($item) {
            return implode('|', [
                $item->ukuran?->kode ?? '-',
                $item->jenisKayu?->nama ?? '-',
                $item->kw ?? '-',
            ]);
        });

        $items = [];

        foreach ($grouped as $key => $rows) {
            [$kodeUkuran, $jenisKayu, $kw] = explode('|', $key);

            /* ===============================
             * GROUP PEKERJA
             * =============================== */
            $pekerja = $rows->groupBy('id_pegawai_pot_siku')
                ->map(function ($rowsPegawai) {
                    $pps = $rowsPegawai->first()->PegawaiPotSiku;

                    return [
                        'nama' => $pps?->pegawai?->nama ?? '-',
                        'items' => $rowsPegawai->map(function ($r) {
                            return [
                                'ukuran'     => $r->ukuran?->nama ?? '-',
                                'jenis_kayu' => $r->jenisKayu?->nama ?? '-',
                                'tinggi'     => $r->tinggi ?? 0,
                                'kw'         => $r->kw ?? '-',
                                'no_palet'   => $r->no_palet ?? '-',
                            ];
                        })->values()->toArray(),
                    ];
                })
                ->values()
                ->toArray();

            $items[] = [
                'kode_ukuran' => $kodeUkuran,
                'jenis_kayu'  => $jenisKayu,
                'kw'          => $kw,
                'hasil'       => $rows->sum(fn ($r) => (int) ($r->tinggi ?? 0)),
                'pekerja'     => $pekerja, // 🔥 FIX UTAMA
            ];
        }

        /* ===============================
         * FINAL
         * =============================== */
        return [
            'tanggal' => $tanggal,
            'target'  => $targetHarian,
            'hasil'   => $hasilProduksi,
            'selisih' => $hasilProduksi - $targetHarian,
            'kendala' => $produksi->kendala ?? '-',
            'validasi' => $produksi->validasiTerakhir
                ? [
                    'status' => $produksi->validasiTerakhir->status,
                    'role'   => $produksi->validasiTerakhir->role,
                ]
                : null,
            'items' => $items,
        ];
    }
}
