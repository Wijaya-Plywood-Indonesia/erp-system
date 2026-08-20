<?php

namespace App\Filament\Pages\LaporanSandingJoin\Transformers;

use Carbon\Carbon;
use App\Enums\Mesin;
use App\Actions\HitungPotonganProduksiAction;

class SandingJoinDataMap
{
    public static function make($collection): array
    {
        $result = [];
        $totalDendaHariItu = 0;
        $sudahDihitung = []; // kode_ukuran => true, cegah double-count

        $action = app(HitungPotonganProduksiAction::class);

        // =====================================================================
        // PASS 1: Kumpulkan data pegawai unik hari itu lebih dulu.
        // Data ini (jumlah pegawai, jam kerja aktual, status izin) dibutuhkan
        // SEBELUM menghitung denda per ukuran di Pass 2, karena target harian
        // perlu diprorata berdasarkan jam kerja aktual tim jika ada yang izin.
        // =====================================================================
        $dataPegawai = []; // kode_pegawai => detail pegawai (unik, ambil kemunculan pertama)

        foreach ($collection as $produksi) {
            foreach ($produksi->pegawaiSandingJoint as $pj) {
                if (!$pj->pegawai) {
                    continue;
                }

                $kodePegawai = $pj->pegawai->kode_pegawai;
                if (isset($dataPegawai[$kodePegawai])) {
                    continue; // sudah tercatat, hindari duplikasi
                }

                $durasiRealita = 0;
                if ($pj->masuk && $pj->pulang) {
                    $durasiRealita = round(Carbon::parse($pj->masuk)->diffInMinutes(Carbon::parse($pj->pulang)) / 60, 1);
                }

                $adaIjin = !empty($pj->ijin) && $pj->ijin !== '-';

                $dataPegawai[$kodePegawai] = [
                    'id'         => $kodePegawai ?? '-',
                    'nama'       => $pj->pegawai->nama_pegawai ?? '-',
                    'jam_masuk'  => $pj->masuk  ? Carbon::parse($pj->masuk)->format('H:i')  : '-',
                    'jam_pulang' => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i') : '-',
                    'total_jam'  => $durasiRealita,
                    'ijin'       => $pj->ijin ?? '-',
                    'ada_ijin'   => $adaIjin,
                    'keterangan' => $pj->ket ?? '-',
                ];
            }
        }

        $jumlahOrangRealita = count($dataPegawai);
        $adaPegawaiIjinHariItu = collect($dataPegawai)->contains('ada_ijin', true);

        // =====================================================================
        // PASS 2: Hitung hasil & potongan per ukuran, memakai data pegawai di
        // atas untuk formula: Gaji x max(0, 1-R) x Rj / Ro
        //   R  = hasil ÷ target harian ukuran itu
        //   Rj = jam realita tim ÷ jam produksi normal ukuran itu
        //   Ro = jumlah orang realita ÷ jumlah orang produksi normal ukuran itu
        // =====================================================================
        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

            foreach ($produksi->hasilSandingJoint as $hasil) {
                $ukuranModel = $hasil->ukuran;
                $jenisKayuModel = $hasil->jenisKayu;
                $kwRaw = $hasil->kw ?? '';
                $kwLower = strtolower($kwRaw);

                if ($ukuranModel && $jenisKayuModel) {
                    $kwSuffix = in_array($kwLower, ['afs', 'afm']) ? $kwRaw : '';
                    $kodeUkuran = 'SANDING JOINT' .
                        $ukuranModel->panjang .
                        $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) .
                        $kwSuffix;
                } else {
                    $kodeUkuran = 'SANDING-JOINT-NOT-FOUND';
                }

                if (isset($sudahDihitung[$kodeUkuran])) {
                    continue; // ukuran ini sudah diproses & diakumulasi, lewati duplikat
                }
                $sudahDihitung[$kodeUkuran] = true;

                // Resolve target via Action
                $resolved = $action->resolveTargetDanRate(
                    mesin: Mesin::SandingJoint,
                    idUkuran: $hasil->id_ukuran,
                    idJenisKayu: $hasil->id_jenis_kayu,
                );

                if (!$resolved) {
                    continue; // target tidak ditemukan untuk ukuran ini, lewati
                }

                $targetModel = $resolved['target'];
                $targetHarian = (int) ($targetModel->target ?? 0);
                $jamProduksiNormal = (float) ($targetModel->jam ?? 0);
                $orgProduksiNormal = (int) ($targetModel->orang ?? 0);
                $gajiHarian = (float) ($targetModel->gaji ?? 0);

                $totalHasilGrup = $produksi->hasilSandingJoint
                    ->where('id_ukuran', $hasil->id_ukuran)
                    ->where('kw', $kwRaw)
                    ->sum('jumlah');

                // --- R: rasio capaian, dikunci minimal (1-R) >= 0 (tidak ada bonus) ---
                $rasioCapaian = $targetHarian > 0 ? ($totalHasilGrup / $targetHarian) : 0;
                $kekuranganCapaian = max(0, 1 - $rasioCapaian);

                // --- Rj: rasio jam kerja tim hari itu (per ukuran, karena jam
                //     standar bisa beda antar ukuran) ---
                $jamRealita = $jamProduksiNormal; // default: dianggap sama (tidak ada izin)
                if ($adaPegawaiIjinHariItu && $jamProduksiNormal > 0 && $jumlahOrangRealita > 0) {
                    $totalJamTerkoreksi = 0;
                    foreach ($dataPegawai as $peg) {
                        // Pegawai izin -> pakai jam kerja aktualnya.
                        // Pegawai normal -> dianggap tetap sesuai jam standar.
                        $totalJamTerkoreksi += $peg['ada_ijin'] ? $peg['total_jam'] : $jamProduksiNormal;
                    }
                    $jamRealita = $totalJamTerkoreksi / $jumlahOrangRealita;
                }
                $rasioJam = $jamProduksiNormal > 0 ? ($jamRealita / $jamProduksiNormal) : 0;

                // --- Ro: rasio jumlah orang hadir vs standar ukuran itu ---
                $rasioOrang = $orgProduksiNormal > 0 ? ($jumlahOrangRealita / $orgProduksiNormal) : 0;

                // --- Potongan per orang, kontribusi dari ukuran ini ---
                $potonganUkuranIni = $rasioOrang > 0
                    ? ($gajiHarian * $kekuranganCapaian * $rasioJam / $rasioOrang)
                    : 0;

                $totalDendaHariItu += $potonganUkuranIni;

                $result[$kodeUkuran] = [
                    'kode_ukuran'      => $kodeUkuran,
                    'ukuran'           => $ukuranModel->nama_ukuran ?? '-',
                    'jenis_kayu'       => $jenisKayuModel->nama_kayu ?? '-',
                    'kw'               => $kwRaw ?: '1',
                    'hasil'            => $totalHasilGrup,
                    'target'           => $targetHarian,
                    'rasio_capaian'    => round($rasioCapaian, 3),   // R
                    'rasio_jam'        => round($rasioJam, 3),        // Rj
                    'rasio_orang'      => round($rasioOrang, 3),      // Ro
                    'potongan_ukuran'  => round($potonganUkuranIni),  // kontribusi potongan ukuran ini
                    'selisih'          => $totalHasilGrup - $targetHarian,
                    'tanggal'          => $tanggal,
                    'gaji_harian'      => $gajiHarian,
                ];
            }
        }

        // TAHAP 2: Total potongan per orang hari itu (sudah "per orang" dari formula,
        // jadi tidak perlu dibagi headcount lagi seperti versi sebelumnya).
        $totalHasilSemuaUkuran = array_sum(array_column($result, 'hasil'));
        $gajiHarianReferensi = $result ? reset($result)['gaji_harian'] : 0;

        if ($totalHasilSemuaUkuran <= 0 && $gajiHarianReferensi > 0) {
            $potonganPerOrang = $gajiHarianReferensi;
        } else {
            // Pengaman: potongan sehari tidak melebihi 1 hari gaji penuh.
            // (Asumsi tambahan dari saya — mohon dikonfirmasi apakah ini sesuai.)
            $totalDendaHariItu = $gajiHarianReferensi > 0
                ? min($totalDendaHariItu, $gajiHarianReferensi)
                : $totalDendaHariItu;

            $potonganPerOrang = $totalDendaHariItu > 0
                ? self::roundToNearest500($totalDendaHariItu)
                : 0;
        }

        // TAHAP 3: Bentuk daftar pekerja unik (dari data yang sudah dikumpulkan di Pass 1)
        $daftarPekerja = [];
        foreach ($dataPegawai as $peg) {
            $daftarPekerja[] = [
                'id'         => $peg['id'],
                'nama'       => $peg['nama'],
                'jam_masuk'  => $peg['jam_masuk'],
                'jam_pulang' => $peg['jam_pulang'],
                'total_jam'  => $peg['total_jam'],
                'ijin'       => $peg['ijin'],
                'keterangan' => $peg['keterangan'],
                'pot_target' => $potonganPerOrang,
            ];
        }

        return [
            'per_ukuran' => array_values($result),
            'pekerja'    => $daftarPekerja,
        ];
    }

    private static function roundToNearest500(float $value): int
    {
        $ribuan = floor($value / 1000);
        $ratusan = fmod($value, 1000);

        if ($ratusan < 300) {
            return (int) ($ribuan * 1000);
        } elseif ($ratusan < 800) {
            return (int) (($ribuan * 1000) + 500);
        } else {
            return (int) (($ribuan + 1) * 1000);
        }
    }
}
