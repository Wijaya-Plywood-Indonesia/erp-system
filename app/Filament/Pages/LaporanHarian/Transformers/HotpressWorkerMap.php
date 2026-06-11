<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;

class HotpressWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        $hotpressMachineIds = \App\Models\Mesin::join('kategori_mesins', 'mesins.kategori_mesin_id', '=', 'kategori_mesins.id')
            ->where('kategori_mesins.nama_kategori_mesin', 'HOTPRESS')
            ->pluck('mesins.id')
            ->toArray();

        if (empty($hotpressMachineIds)) {
            $hotpressMachineIds = [13, 26, 27, 28];
        }

        $targets = \App\Models\Target::whereIn('id_mesin', $hotpressMachineIds)->get();

        foreach ($collection as $produksi) {
            $produksiId = $produksi->id;
            $shift = (strtoupper($produksi->shift ?? '') === 'MALAM') ? 'MALAM' : 'PAGI';
            $labelHasil = "HOT PRESS {$shift}";

            // 1. Calculate actual production by id_ukuran
            $platformActuals = \App\Models\PlatformHasilHp::where('id_produksi_hp', $produksiId)
                ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'platform_hasil_hp.id_barang_setengah_jadi')
                ->selectRaw('barang_setengah_jadi_hp.id_ukuran, SUM(platform_hasil_hp.isi) as total_actual')
                ->groupBy('barang_setengah_jadi_hp.id_ukuran')
                ->get();

            $triplekActuals = \App\Models\TriplekHasilHp::where('id_produksi_hp', $produksiId)
                ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'triplek_hasil_hp.id_barang_setengah_jadi')
                ->selectRaw('barang_setengah_jadi_hp.id_ukuran, SUM(triplek_hasil_hp.isi) as total_actual')
                ->groupBy('barang_setengah_jadi_hp.id_ukuran')
                ->get();

            $combinedActuals = [];
            foreach ($platformActuals as $act) {
                $combinedActuals[$act->id_ukuran] = (int) $act->total_actual;
            }
            foreach ($triplekActuals as $act) {
                if (isset($combinedActuals[$act->id_ukuran])) {
                    $combinedActuals[$act->id_ukuran] += (int) $act->total_actual;
                } else {
                    $combinedActuals[$act->id_ukuran] = (int) $act->total_actual;
                }
            }

            // 2. Calculate deficit and total denda for this session
            $totalDenda = 0;
            foreach ($combinedActuals as $id_ukuran => $actual) {
                $tgt = $targets->first(function ($t) use ($id_ukuran) {
                    return $t->id_ukuran == $id_ukuran;
                });

                // FALLBACK TO WILDCARD (size 33 / '0x0x0') IF NOT FOUND
                if (!$tgt) {
                    $tgt = $targets->first(function ($t) {
                        return $t->id_ukuran == 33;
                    });
                }

                if ($tgt) {
                    $targetVal = (float) $tgt->target;
                    $potonganPerPcs = (float) $tgt->potongan;

                    $deficit = $targetVal - $actual;
                    if ($deficit > 0 && $potonganPerPcs > 0) {
                        $totalDenda += $deficit * $potonganPerPcs;
                    }
                }
            }

            // 3. Share deduction among workers in this session
            $potonganPerOrang = 0;
            if ($totalDenda > 0) {
                $jumlahPekerja = $produksi->detailPegawaiHp ? $produksi->detailPegawaiHp->count() : 0;
                if ($jumlahPekerja > 0) {
                    $potonganRaw = $totalDenda / $jumlahPekerja;

                    // --- RUMUS PEMBULATAN KHUSUS (0, 500, 1000) ---
                    $ribuan = floor($potonganRaw / 1000);
                    $ratusan = $potonganRaw % 1000;

                    if ($ratusan < 300) {
                        $potonganPerOrang = $ribuan * 1000;
                    } elseif ($ratusan < 800) {
                        $potonganPerOrang = ($ribuan * 1000) + 500;
                    } else {
                        $potonganPerOrang = ($ribuan + 1) * 1000;
                    }
                }
            }

            // 4. Mapping Pegawai
            if ($produksi->detailPegawaiHp) {
                foreach ($produksi->detailPegawaiHp as $dp) {
                    if (!$dp->pegawaiHp) continue;

                    $jamMasuk = $dp->masuk ? Carbon::parse($dp->masuk)->format('H:i:s') : '-';
                    $jamPulang = $dp->pulang ? Carbon::parse($dp->pulang)->format('H:i:s') : '-';

                    $results[] = [
                        'kodep' => $dp->pegawaiHp->kode_pegawai ?? '-',
                        'nama' => $dp->pegawaiHp->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelHasil,
                        'ijin' => $dp->ijin ?? '-',
                        'potongan_targ' => (int) $potonganPerOrang,
                        'keterangan' => $dp->ket ?? $produksi->kendala ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
