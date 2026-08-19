<?php

namespace App\Filament\Pages\LaporanJoin\Transformers;

use Carbon\Carbon;
use App\Enums\Mesin;
use App\Actions\HitungPotonganProduksiAction;
use Illuminate\Support\Facades\Log;

class JoinDataMap
{
    /**
     * Jatah istirahat baku yang sudah termasuk dalam rentang jam
     * masuk-pulang (misal 06:00-16:00 = 10 jam kotor, tapi jam kerja
     * bersihnya cuma 9 jam karena 1 jam di antaranya istirahat).
     */
    private const ISTIRAHAT_MENIT = 60;

    public static function make($collection): array
    {
        $result = [];
        $action = new HitungPotonganProduksiAction();

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

            /* ============================================================
             * 1. HITUNG JAM AKTUAL & ORG AKTUAL — SEKALI PER PRODUKSI
             * ------------------------------------------------------------
             * Kru (pegawaiJoint) sama untuk semua meja/ukuran dalam satu
             * produksi ini, jadi org-menit dihitung sekali di sini (bukan
             * diulang tiap modal), lalu dipakai untuk semua grup meja.
             *
             * Sama seperti Dryer: total menit kerja tiap orang dijumlahkan
             * (bukan cuma rentang waktu), supaya pekerja yang durasinya
             * lebih pendek dari yang lain ikut mengecilkan target secara
             * proporsional.
             *
             * ISTIRAHAT TETAP: rentang masuk-pulang (misal 06:00-16:00 = 10
             * jam) sudah termasuk jatah istirahat 1 jam baku, sehingga jam
             * kerja BERSIH = rentang kotor - 60 menit. Ini beda dari Dryer
             * (yang menguranginya pakai downtime kendala mesin) — di Join
             * gak ada kendala/downtime, istirahatnya selalu tetap 60 menit.
             * ============================================================ */

            $tanggalStr       = Carbon::parse($produksi->tanggal_produksi)->format('Y-m-d');
            $totalPersonMenit = 0;
            $jumlahPekerja    = $produksi->pegawaiJoint->count();

            foreach ($produksi->pegawaiJoint as $pj) {
                if (!$pj->masuk || !$pj->pulang) {
                    continue; // tidak ada fallback jam normal di sini (beda mesin, beda ukuran per grup)
                }

                $masuk  = Carbon::parse($pj->masuk);
                $pulang = Carbon::parse($pj->pulang);

                if ($pulang->lessThan($masuk)) {
                    $pulang->addDay();
                }

                $grossMenit = $masuk->diffInMinutes($pulang);
                $netMenit   = max(0, $grossMenit - self::ISTIRAHAT_MENIT);

                $totalPersonMenit += $netMenit;
            }

            $avgMenitPerOrang = $jumlahPekerja > 0 ? $totalPersonMenit / $jumlahPekerja : 0;
            $jamAktual        = (int) floor($avgMenitPerOrang / 60);
            $menitAktual      = $avgMenitPerOrang - ($jamAktual * 60);

            /* ============================================================
             * 2. LOOP TIAP HASIL (KOMBINASI UKURAN + JENIS KAYU + KW)
             * ------------------------------------------------------------
             * PENTING: dulu kode ini loop `modalJoint` (bahan baku veneer
             * SEBELUM di-join, misal 67x68x3.7 + 48x68x3.7). Itu salah —
             * target & potongan harus dihitung dari ukuran HASIL setelah
             * di-join (misal jadi 130x68x3.7), karena itu yang sebenarnya
             * diproduksi dan dibandingkan ke target. `modalJoint` cuma
             * relevan untuk hitung pemakaian bahan baku, bukan di sini.
             *
             * Satu produksi bisa punya beberapa baris hasilJoint dengan
             * kombinasi ukuran+jenis kayu+kw yang SAMA (beda no_palet),
             * jadi kita group dulu sebelum diproses.
             * ============================================================ */

            $hasilGrouped = $produksi->hasilJoint->groupBy(function ($h) {
                return $h->id_ukuran . '|' . $h->id_jenis_kayu . '|' . $h->kw;
            });

            foreach ($hasilGrouped as $hasilRows) {
                $firstHasil     = $hasilRows->first();
                $ukuranModel    = $firstHasil->ukuran;
                $jenisKayuModel = $firstHasil->jenisKayu;
                $kw             = $firstHasil->kw ?? '1';

                // Kode ukuran cuma untuk label tampilan, BUKAN untuk cari
                // Target lagi — itu sekarang lewat Resolver berbasis
                // id_mesin + id_ukuran + id_jenis_kayu.
                if ($ukuranModel && $jenisKayuModel) {
                    $kodeUkuran = 'JOINT' . $ukuranModel->panjang . $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) . $kw .
                        strtolower($jenisKayuModel->kode_kayu ?? 'jnt');
                } else {
                    $kodeUkuran = 'JOINT-NOT-FOUND';
                }

                $idUkuran    = $firstHasil->id_ukuran;
                $idJenisKayu = $firstHasil->id_jenis_kayu;

                // 3. Hasil Grup = total lembar dari semua baris hasilJoint
                // dengan ukuran+jenis kayu+kw yang sama (beda no_palet)
                $hasilGrup = $hasilRows->sum('jumlah');

                // 4. TARGET (ADJUSTED) & POTONGAN — lewat Action/Resolver/Service
                $hitung = null;
                if ($idUkuran && $idJenisKayu) {
                    $hitung = $action->execute(
                        mesin: Mesin::Joint,
                        orgAktual: $jumlahPekerja,
                        jamAktual: (float) $jamAktual,
                        menitAktual: (float) $menitAktual,
                        hasilAktual: (float) $hasilGrup,
                        idUkuran: $idUkuran,
                        idJenisKayu: $idJenisKayu,
                    );
                }

                if (!$hitung) {
                    Log::warning('Target Join tidak ditemukan / data ukuran-jenis kayu tidak lengkap', [
                        'id_produksi'   => $produksi->id,
                        'kode_ukuran'   => $kodeUkuran,
                        'id_ukuran'     => $idUkuran,
                        'id_jenis_kayu' => $idJenisKayu,
                    ]);
                }

                $targetAdjusted   = $hitung?->targetAdjusted ?? 0;
                $potonganTotal    = $hitung?->potongan ?? 0;
                $potonganPerOrang = $hitung?->potonganPerOrang ?? 0;
                $selisih          = $hasilGrup - $targetAdjusted;

                $nomorMeja = null;
                $key       = null;

                // 5. Assign ke tiap pekerja dalam kru ini
                foreach ($produksi->pegawaiJoint as $pj) {
                    if (!$pj->pegawai) {
                        continue;
                    }

                    $nomorMeja = $pj->tugas ?? $pj->nomor_meja ?? '-';
                    $key       = $nomorMeja . '|' . $kodeUkuran;

                    if (!isset($result[$key])) {
                        $result[$key] = [
                            'nomor_meja'      => $nomorMeja,
                            'kode_ukuran'     => $kodeUkuran,
                            'ukuran'          => $ukuranModel->nama_ukuran ?? '-',
                            'jenis_kayu'      => $jenisKayuModel->nama_kayu ?? '-',
                            'kw'              => $kw,
                            'pekerja'         => [],
                            'hasil'           => $hasilGrup,
                            'target'          => $hitung ? null : 0, // target normal tidak lagi disimpan flat; lihat target_adjusted
                            'target_adjusted' => $targetAdjusted,
                            'jam_aktual'      => $jamAktual + ($menitAktual / 60),
                            'jumlah_pekerja'  => $jumlahPekerja,
                            'selisih'         => $selisih,
                            'tanggal'         => $tanggal,
                            'has_target'      => $hitung !== null,
                        ];
                    }

                    $result[$key]['pekerja'][] = [
                        'id'          => $pj->pegawai->kode_pegawai ?? '-',
                        'nama'        => $pj->pegawai->nama_pegawai ?? '-',
                        'jam_masuk'   => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i') : '-',
                        'jam_pulang'  => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i') : '-',
                        'ijin'        => $pj->ijin ?? '-',
                        'keterangan'  => $pj->ket ?? '-',
                        'hasil'       => $hasilGrup,
                        'pot_target'  => $potonganPerOrang,
                    ];
                }
            }
        }

        return array_values($result);
    }
}