<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class JoinWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            $daftarHasil = $produksi->hasilJoint ?? [];
            $daftarPegawai = $produksi->pegawaiJoint ?? [];
            $jumlahPekerja = count($daftarPegawai); // Mendapatkan jumlah pekerja di grup ini

            foreach ($daftarHasil as $hasil) {
                $ukuranModel = $hasil->ukuran;
                $jenisKayuModel = $hasil->jenisKayu;

                $kwRaw = trim($hasil->kw ?? '');
                $kodeKayu = strtoupper(trim($jenisKayuModel->kode_kayu ?? ''));

                $labelPekerjaan = 'JOINT';

                // --- KONSTRUKSI KODE ---
                $kodeUkuran = 'JOINT-NOT-FOUND';
                if ($ukuranModel && $jenisKayuModel) {
                    $suffixKayu = ($kodeKayu === 'S') ? 'S' : '';
                    $kodeUkuran = 'JOINT' .
                        $ukuranModel->panjang .
                        $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) .
                        $kwRaw .
                        $suffixKayu;
                    $kodeUkuran = str_replace(' ', '', $kodeUkuran);
                }

                // --- PENCARIAN TARGET ---
                $targetModel = Target::where('kode_ukuran', $kodeUkuran)
                    ->where('id_mesin', $produksi->id_mesin ?? null)
                    ->first() ?? Target::where('kode_ukuran', $kodeUkuran)->first();

                $targetWajib = (int) ($targetModel->target ?? 0);
                $potonganPerLembar = (int) ($targetModel->potongan ?? 0);

                foreach ($daftarPegawai as $pj) {
                    if (!$pj->pegawai) continue;

                    // Hitung Hasil Grup
                    $hasilGrup = collect($daftarHasil)
                        ->where('id_ukuran', $hasil->id_ukuran)
                        ->where('kw', $kwRaw)
                        ->sum('jumlah');

                    $selisih = $hasilGrup - $targetWajib;
                    $potonganPerOrang = 0;

                    // --- RUMUS BARU: PEMBAGIAN RATA ---
                    if ($targetWajib > 0 && $selisih < 0 && $potonganPerLembar > 0) {
                        $potonganTotalMeja = abs($selisih) * $potonganPerLembar;

                        if ($jumlahPekerja > 0) {
                            $potonganPerOrangRaw = $potonganTotalMeja / $jumlahPekerja;

                            // Pembulatan 3 Tingkat
                            $ribuan = floor($potonganPerOrangRaw / 1000);
                            $ratusan = (int)$potonganPerOrangRaw % 1000;

                            if ($ratusan < 300) {
                                $potonganPerOrang = $ribuan * 1000;
                            } elseif ($ratusan >= 300 && $ratusan < 800) {
                                $potonganPerOrang = ($ribuan * 1000) + 500;
                            } else {
                                $potonganPerOrang = ($ribuan + 1) * 1000;
                            }
                        }
                    }

                    $results[] = [
                        'kodep' => $pj->pegawai->kode_pegawai ?? '-',
                        'nama' => $pj->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i:s') : '',
                        'pulang' => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i:s') : '',
                        'hasil' => $labelPekerjaan,
                        'ijin' => $pj->ijin ?? '',
                        'potongan_targ' => (int) ($pj->potongan ?? $potonganPerOrang),
                        'keterangan' => $pj->ket ?? '',
                    ];
                }
            }
        }
        return $results;
    }
}
