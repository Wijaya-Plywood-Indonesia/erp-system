<?php

namespace App\Filament\Pages\LaporanPotSiku\Transformers;

use App\Models\ProduksiPotSiku;
use App\Enums\Mesin;
use App\Actions\HitungPotonganProduksiAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PotSikuDataMap
{
    /**
     * @return array{
     *   tanggal: string, kendala: string, validasi: ?array,
     *   pegawai_summary: array, items: array
     * }
     */
    public static function make(ProduksiPotSiku $produksi): array
    {
        $action = new HitungPotonganProduksiAction();

        $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
        $details = $produksi->detailBarangDikerjakanPotSiku ?? collect();

        /* ================================================================
         * 1. HITUNG CAPAIAN GLOBAL PER PEGAWAI (INDIVIDUAL)
         * ----------------------------------------------------------------
         * Sama seperti Join: tiap baris Target Pot Siku dirancang dengan
         * basis "1 ORANG kerja 9 jam PENUH khusus untuk 1 ukuran itu"
         * (org=1 di semua baris Target Pot Siku). Karena 1 pegawai bisa
         * kerja beberapa ukuran dalam 1 hari, jam kerjanya otomatis
         * KEBAGI ke semua ukuran itu — sama seperti kasus Join.
         *
         * Makanya dipakai rumus "jumlah persen" (bukan flat 300, bukan
         * rata-rata): tiap ukuran yang DIA kerjakan dihitung capaian-nya
         * sendiri, dijumlah jadi capaian global PER ORANG. >=100% berarti
         * 1 hari kerjanya sudah terpakai penuh secara produktif walau
         * dibagi ke beberapa ukuran — TIDAK dipotong.
         *
         * Target di sini TETAP FLAT terhadap jam kerja aktual pegawai
         * (tidak di-adjust ke jam masuk-pulang) — sesuai keputusan: target
         * per ukuran diambil apa adanya dari tabel Target, bukan dihitung
         * ulang berdasarkan jam kerja aktual hari itu.
         * ================================================================ */

        $porPegawai = $details->groupBy('id_pegawai_pot_siku');

        // Cache rate/target per id_ukuran supaya gak query DB berulang
        // untuk ukuran yang sama dipakai banyak pegawai.
        $targetCache = [];

        $resolveTarget = function (?int $idUkuran) use ($action, &$targetCache) {
            if (!$idUkuran) {
                return null;
            }
            if (!array_key_exists($idUkuran, $targetCache)) {
                // idJenisKayu sengaja null -> UkuranBasedResolver akan skip
                // filter jenis kayu (baris Target Pot Siku tidak dibedakan
                // per jenis kayu, cuma per ukuran).
                $targetCache[$idUkuran] = $action->resolveTargetDanRate(
                    Mesin::PotSiku,
                    $idUkuran,
                    null
                );
            }
            return $targetCache[$idUkuran];
        };

        $potonganPerPegawai = []; // id_pegawai_pot_siku => Rupiah
        $capaianGlobalPerPegawai = []; // id_pegawai_pot_siku => %
        $namaPerPegawai = [];

        foreach ($porPegawai as $idPegawaiPotSiku => $rows) {
            $namaPerPegawai[$idPegawaiPotSiku] = $rows->first()->pegawaiPotSiku?->pegawai?->nama_pegawai
                ?? $rows->first()->pegawaiPotSiku?->pegawai?->nama
                ?? '-';

            $rowsByUkuran = $rows->groupBy('id_ukuran');

            $sumCapaianPersen = 0;
            $sumNilaiTarget   = 0;
            $jumlahUkuranAda  = 0;

            foreach ($rowsByUkuran as $idUkuran => $rowsUkuran) {
                $hasilUkuran = (int) $rowsUkuran->sum(fn ($r) => (int) ($r->tinggi ?? 0));

                $rateInfo = $resolveTarget($idUkuran ? (int) $idUkuran : null);

                if (!$rateInfo) {
                    Log::warning('Target Pot Siku tidak ditemukan untuk ukuran ini', [
                        'id_produksi' => $produksi->id,
                        'id_pegawai_pot_siku' => $idPegawaiPotSiku,
                        'id_ukuran' => $idUkuran,
                    ]);
                    continue;
                }

                $target       = $rateInfo['target'];
                $targetUkuran = (float) $target->target;
                $biayaPerCm   = (float) $target->potongan;

                $capaian = $targetUkuran > 0 ? ($hasilUkuran / $targetUkuran) * 100 : 100.0;
                $nilai   = $targetUkuran * $biayaPerCm;

                $sumCapaianPersen += $capaian;
                $sumNilaiTarget   += $nilai;
                $jumlahUkuranAda  += 1;
            }

            $capaianGlobal = $sumCapaianPersen; // dijumlah, bukan dirata-rata (lihat README Join)
            $capaianGlobalPerPegawai[$idPegawaiPotSiku] = $capaianGlobal;

            if ($jumlahUkuranAda === 0) {
                $potonganPerPegawai[$idPegawaiPotSiku] = 0;
                continue;
            }

            $nilaiSatuHariPenuh = $sumNilaiTarget / $jumlahUkuranAda;
            $kekuranganPersen   = max(0, 100 - $capaianGlobal) / 100;
            $potonganOrang      = $kekuranganPersen * $nilaiSatuHariPenuh;

            $potonganPerPegawai[$idPegawaiPotSiku] = round($potonganOrang / 500) * 500;
        }

        /* ================================================================
         * 2. SUSUN OUTPUT PER PEGAWAI (untuk kartu tampilan — 1 kartu =
         *    1 pegawai, isinya semua ukuran/barang yang dia kerjakan hari
         *    itu, plus 1 angka potongan gabungan di kartu itu).
         * ================================================================ */

        $pekerjaOutput = [];

        foreach ($porPegawai as $idPegawaiPotSiku => $rows) {
            $pps = $rows->first()->pegawaiPotSiku;

            $itemsPegawai = $rows->groupBy('id_ukuran')->map(function ($rowsUkuran) use ($resolveTarget) {
                $first        = $rowsUkuran->first();
                $ukuranModel  = $first->ukuran;
                $ukuranLabel  = $ukuranModel
                    ? "{$ukuranModel->panjang}x{$ukuranModel->lebar}x{$ukuranModel->tebal}"
                    : ($ukuranModel->nama_ukuran ?? '-');

                $rateInfo     = $resolveTarget($first->id_ukuran ? (int) $first->id_ukuran : null);
                $targetUkuran = $rateInfo ? (float) $rateInfo['target']->target : 0;
                $hasilUkuran  = (int) $rowsUkuran->sum(fn ($r) => (int) ($r->tinggi ?? 0));
                $capaian      = $targetUkuran > 0 ? ($hasilUkuran / $targetUkuran) * 100 : ($rateInfo ? 100.0 : null);

                return [
                    'ukuran'         => $ukuranLabel,
                    'jenis_kayu'     => $first->jenisKayu?->nama_kayu ?? $first->jenisKayu?->nama ?? '-',
                    'kw'             => $first->kw ?? '-',
                    'target'         => $targetUkuran,
                    'hasil'          => $hasilUkuran,
                    'selisih'        => $hasilUkuran - $targetUkuran,
                    'capaian_persen' => $capaian,
                    'has_target'     => $rateInfo !== null,
                    'no_palet_list'  => $rowsUkuran->pluck('no_palet')->filter()->implode(', ') ?: '-',
                ];
            })->values()->toArray();

            $pekerjaOutput[] = [
                'id_pegawai_pot_siku'   => $idPegawaiPotSiku,
                'kode_pegawai'          => $pps?->pegawai?->kode_pegawai ?? '-',
                'nama'                  => $namaPerPegawai[$idPegawaiPotSiku] ?? '-',
                'jam_masuk'             => $pps?->masuk ? Carbon::parse($pps->masuk)->format('H:i') : '-',
                'jam_pulang'            => $pps?->pulang ? Carbon::parse($pps->pulang)->format('H:i') : '-',
                'ijin'                  => $pps?->ijin ?? '-',
                'keterangan'            => $pps?->ket ?? '-',
                'total_hasil'           => (int) $rows->sum(fn ($r) => (int) ($r->tinggi ?? 0)),
                'capaian_global_persen' => round($capaianGlobalPerPegawai[$idPegawaiPotSiku] ?? 0, 1),
                'potongan'              => $potonganPerPegawai[$idPegawaiPotSiku] ?? 0,
                'items'                 => $itemsPegawai,
            ];
        }

        return [
            'tanggal'  => $tanggal,
            'kendala'  => $produksi->kendala ?? '-',
            'validasi' => $produksi->validasiTerakhir
                ? [
                    'status' => $produksi->validasiTerakhir->status,
                    'role'   => $produksi->validasiTerakhir->role,
                ]
                : null,
            'pekerja' => $pekerjaOutput,
        ];
    }
}