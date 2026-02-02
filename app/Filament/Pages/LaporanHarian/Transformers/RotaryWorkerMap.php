<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;
use App\Models\Target;

class RotaryWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $item) {

            // 1. Label Divisi (Hasil)
            $namaMesin = $item->mesin->nama_mesin ?? 'MESIN ?';
            $labelDivisi = "ROTARY - " . strtoupper($namaMesin);

            // 2. Hitung Downtime (Menit)
            $totalKendalaMenit = 0;
            if ($item->detailGantiPisauRotary) {
                foreach ($item->detailGantiPisauRotary as $ganti) {
                    if (!empty($ganti->jam_selesai_ganti) && $ganti->jam_selesai_ganti !== '-') {
                        try {
                            $mulai = Carbon::parse($ganti->jam_mulai_ganti_pisau);
                            $selesai = Carbon::parse($ganti->jam_selesai_ganti);
                            $totalKendalaMenit += $mulai->diffInMinutes($selesai);
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
            }

            // 3. Hitung Target & Potongan
            $totalHasil = $item->detailPaletRotary->sum('total_lembar') ?? 0;

            $firstPalet = $item->detailPaletRotary->first();
            $ukuranId = $firstPalet?->id_ukuran;

            $targetModel = Target::where('id_mesin', $item->id_mesin)
                ->where('id_ukuran', $ukuranId)
                ->first();

            if (!$targetModel) {
                $targetModel = Target::where('id_mesin', $item->id_mesin)
                    ->whereNull('id_ukuran')
                    ->first();
            }

            $targetHarian = $targetModel?->target ?? 0;
            $jamKerja = $targetModel?->jam ?? 0;
            $potonganPerLembar = $targetModel?->potongan ?? 0;

            $jamKerjaMenit = $jamKerja * 60;
            $jamKerjaEfektifMenit = $jamKerjaMenit - $totalKendalaMenit;

            $targetDisesuaikan = ($jamKerjaMenit > 0)
                ? round(($jamKerjaEfektifMenit / $jamKerjaMenit) * $targetHarian, 2)
                : $targetHarian;

            $selisihProduksi = $totalHasil - $targetDisesuaikan;
            $potonganPerOrang = 0;

            // Logika Potongan
            if ($selisihProduksi < 0 && $potonganPerLembar > 0) {
                $targetPerMenit = $jamKerjaMenit > 0 ? ($targetHarian / $jamKerjaMenit) : 0;
                $kekuranganToleransi = $targetPerMenit * $totalKendalaMenit;

                $kekuranganTotal = abs($selisihProduksi);
                $kekuranganPerforma = $kekuranganTotal - $kekuranganToleransi;

                if ($kekuranganPerforma > 0) {
                    $jumlahPekerja = $item->detailPegawaiRotary->count();
                    if ($jumlahPekerja > 0) {
                        $potonganTotal = $kekuranganPerforma * $potonganPerLembar;
                        $potonganRaw = $potonganTotal / $jumlahPekerja;

                        // Pembulatan
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
            }

            // 4. Mapping Pegawai
            foreach ($item->detailPegawaiRotary as $pegawai) {
                $jamMasuk = $pegawai->jam_masuk ? Carbon::parse($pegawai->jam_masuk)->format('H:i:s') : '';
                $jamPulang = $pegawai->jam_pulang ? Carbon::parse($pegawai->jam_pulang)->format('H:i:s') : '';

                $results[] = [
                    'kodep' => $pegawai->pegawai->kode_pegawai ?? '-',
                    'nama' => $pegawai->pegawai->nama_pegawai ?? 'TANPA NAMA',
                    'masuk' => $jamMasuk,
                    'pulang' => $jamPulang,
                    'hasil' => $labelDivisi,
                    'ijin' => $pegawai->ijin ?? '',
                    'potongan_targ' => $potonganPerOrang,
                    'keterangan' => $pegawai->keterangan ?? '',
                ];
            }
        }

        return $results;
    }
}
