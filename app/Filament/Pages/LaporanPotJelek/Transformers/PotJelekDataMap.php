<?php

namespace App\Filament\Pages\LaporanPotJelek\Transformers;

use Carbon\Carbon;
use App\Models\Target;

class PotJelekDataMap
{
    public static function make($collection): array
    {
        $result = [];
        $kodeTargetGlobal = 'POT JELEK';

        $targetModel = Target::where('kode_ukuran', $kodeTargetGlobal)->first();
        $targetPerOrang = (int) ($targetModel->target ?? 0);
        $jamStandarTarget = (float) ($targetModel->jam ?? 0);
        $nilaiPotonganPerLembar = (float) ($targetModel->potongan ?? 0);

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

            foreach ($produksi->detailBarangDikerjakanPotJelek as $detail) {
                $pj = $detail->PegawaiPotJelek;
                if (!$pj || !$pj->pegawai) continue;

                $key = $pj->pegawai->id . '|' . $produksi->id;

                if (!isset($result[$key])) {
                    $result[$key] = [
                        'kode_nama' => ($pj->pegawai->kode_pegawai ?? '-') . ' - ' . ($pj->pegawai->nama_pegawai ?? 'TANPA NAMA'),
                        'jam_masuk' => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i') : '-',
                        'jam_pulang' => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i') : '-',
                        'ijin' => $pj->ijin ?? '-',
                        'keterangan' => $pj->keterangan ?? $produksi->kendala ?? '-',
                        'tanggal' => $tanggal,
                        'target' => $targetPerOrang,
                        'jam_standar' => $jamStandarTarget,
                        'rincian' => [],
                        'hasil' => 0,
                        'selisih' => 0,
                        'pot_target' => 0,
                    ];
                }

                // Format Ukuran: Ukuran . KW . Jenis Kayu
                $namaUkuran = $detail->ukuran->nama_ukuran ?? '-';
                $kw = $detail->kw ?? '-';
                $namaKayu = $detail->jenisKayu->nama_kayu ?? '-';
                $formatUkuran = "{$namaUkuran} . {$kw} . {$namaKayu}";

                $result[$key]['rincian'][] = [
                    'ukuran_lengkap' => $formatUkuran,
                    'jumlah' => $detail->jumlah ?? 1,
                ];

                $result[$key]['hasil'] += $detail->jumlah ?? 1;
            }
        }

        foreach ($result as $k => $data) {
            $selisih = $data['hasil'] - $targetPerOrang;
            $result[$k]['selisih'] = $selisih;

            if ($selisih < 0 && $targetPerOrang > 0) {
                $denda = abs($selisih) * $nilaiPotonganPerLembar;
                $result[$k]['pot_target'] = self::roundToNearest500($denda);
            }
        }

        return array_values($result);
    }

    private static function roundToNearest500(float $value): int
    {
        $ribuan = floor($value / 1000);
        $ratusan = $value % 1000;
        if ($ratusan < 300) return (int) ($ribuan * 1000);
        if ($ratusan < 800) return (int) ($ribuan * 1000 + 500);
        return (int) (($ribuan + 1) * 1000);
    }
}
