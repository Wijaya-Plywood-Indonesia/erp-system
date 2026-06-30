<?php

namespace App\Filament\Pages\Absen\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class SandingJoinWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            $daftarHasil = $produksi->hasilSandingJoint ?? [];
            $daftarPegawai = $produksi->pegawaiSandingJoint ?? [];
            $jumlahPekerja = count($daftarPegawai);

            foreach ($daftarHasil as $hasil) {
                $ukuranModel = $hasil->ukuran;
                $kwRaw = trim($hasil->kw ?? '');
                $kwLower = strtolower($kwRaw);

                // Format Kode: SANDING JOINT (spasi) PanjangLebarTebalSuffix
                $suffix = in_array($kwLower, ['afs', 'afm']) ? $kwRaw : '';
                $kodeUkuran = 'SANDING JOINT' .
                    $ukuranModel->panjang .
                    $ukuranModel->lebar .
                    str_replace('.', ',', $ukuranModel->tebal) .
                    $suffix;
                $kodeUkuran = trim($kodeUkuran);

                $targetModel = Target::where('kode_ukuran', $kodeUkuran)
                    ->where('id_mesin', $produksi->id_mesin ?? null)
                    ->first() ?? Target::where('kode_ukuran', $kodeUkuran)->first();

                $targetWajib = (int) ($targetModel->target ?? 0);
                $potonganPerLembar = (float) ($targetModel->potongan ?? 0);

                foreach ($daftarPegawai as $psj) {
                    if (!$psj->pegawai) continue;

                    $hasilGrup = collect($daftarHasil)
                        ->where('id_ukuran', $hasil->id_ukuran)
                        ->where('kw', $kwRaw)
                        ->sum('jumlah');

                    $selisih = $hasilGrup - $targetWajib;
                    $potonganPerOrang = 0;

                    // Logika Pembagian Beban
                    if ($targetWajib > 0 && $selisih < 0 && $potonganPerLembar > 0) {
                        $totalDendaMeja = abs($selisih) * $potonganPerLembar;

                        if ($jumlahPekerja > 0) {
                            $rawPotongan = $totalDendaMeja / $jumlahPekerja;
                            $potonganPerOrang = self::pembulatanTigaTingkat($rawPotongan);
                        }
                    }

                    $results[] = [
                        'kodep' => $psj->pegawai->kode_pegawai ?? '-',
                        'nama' => $psj->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $psj->masuk ? Carbon::parse($psj->masuk)->format('H:i:s') : '',
                        'pulang' => $psj->pulang ? Carbon::parse($psj->pulang)->format('H:i:s') : '',
                        'hasil' => 'SANDING JOINT',
                        'ijin' => $psj->ijin ?? '',
                        'potongan_targ' => (int) ($psj->potongan ?? $potonganPerOrang),
                        'keterangan' => $psj->ket ?? '',
                    ];
                }
            }
        }
        return $results;
    }

    private static function pembulatanTigaTingkat($value): int
    {
        $ribuan = floor($value / 1000);
        $ratusan = $value % 1000;
        if ($ratusan < 300) return (int) ($ribuan * 1000);
        if ($ratusan < 800) return (int) ($ribuan * 1000 + 500);
        return (int) (($ribuan + 1) * 1000);
    }
}
