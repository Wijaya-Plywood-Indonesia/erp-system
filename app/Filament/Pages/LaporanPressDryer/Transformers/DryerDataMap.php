<?php

namespace App\Filament\Pages\LaporanPressDryer\Transformers;

use Carbon\Carbon;
use App\Enums\Mesin;
use App\Enums\StrategiPembagian;
use App\Actions\HitungPotonganProduksiAction;
use App\DataTransferObjects\PekerjaKerjaInput;
use Illuminate\Support\Facades\Log;

class DryerDataMap
{
    /**
     * Jatah istirahat baku yang sudah termasuk dalam rentang jam
     * masuk-pulang — HANYA berlaku untuk shift MALAM. Shift PAGI tidak
     * ada potongan istirahat sama sekali (cuma downtime kendala mesin).
     */
    private const ISTIRAHAT_MENIT = 60;

    public static function make($collection)
    {
        $result = [];
        $action = new HitungPotonganProduksiAction();

        foreach ($collection as $item) {

            /* ============================================================
             * 1. MESIN, SHIFT, TANGGAL
             * ============================================================ */

            $mesinList = $item->detailMesins
                ->pluck('mesin.nama_mesin')
                ->filter()
                ->unique();

            $namaMesin = $mesinList->isNotEmpty()
                ? $mesinList->implode(' & ')
                : 'TIDAK ADA MESIN';

            $shift   = strtoupper($item->shift ?? 'PAGI');
            $tanggal = Carbon::parse($item->tanggal_produksi)->format('d/m/Y');

            $mesinEnum = $shift === 'MALAM' ? Mesin::DryerMalam : Mesin::DryerPagi;

            // ---------------------------------------------------------
            // KENDALA / DOWNTIME
            // ---------------------------------------------------------
            $totalDowntimeMenit = 0;
            $daftarKendala      = [];

            if (!empty($item->kendalaPressDryers) && $item->kendalaPressDryers->count() > 0) {
                foreach ($item->kendalaPressDryers as $knd) {
                    if ($knd->status === 'selesai' && !is_null($knd->durasi_menit)) {
                        $durasiMenit = (int) $knd->durasi_menit;
                        $totalDowntimeMenit += $durasiMenit;

                        $mulai   = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $selesai = $knd->waktu_selesai ? Carbon::parse($knd->waktu_selesai) : null;
                        $timeStr = ($mulai && $selesai) ? ': ' . $mulai->format('H:i') . '-' . $selesai->format('H:i') : '';

                        $daftarKendala[] = [
                            'kendala'      => $knd->kendala ?? 'Tidak disebutkan',
                            'durasi_menit' => $durasiMenit,
                            'jam_mulai'    => $mulai ? $mulai->format('H:i') : '-',
                            'jam_selesai'  => $selesai ? $selesai->format('H:i') : '-',
                            'text'         => ($knd->kendala ?? 'Tidak disebutkan') . ' (' . $durasiMenit . ' menit' . $timeStr . ')',
                        ];
                    } else {
                        $mulai   = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $timeStr = $mulai ? ' (Mulai: ' . $mulai->format('H:i') . ' - Pending)' : ' (Pending)';

                        $daftarKendala[] = [
                            'kendala'      => $knd->kendala ?? 'Tidak disebutkan',
                            'durasi_menit' => 0,
                            'jam_mulai'    => $mulai ? $mulai->format('H:i') : '-',
                            'jam_selesai'  => '-',
                            'text'         => ($knd->kendala ?? 'Tidak disebutkan') . $timeStr,
                        ];
                    }
                }
            }

            $kendalaText    = count($daftarKendala) > 0 ? implode(', ', array_column($daftarKendala, 'text')) : '-';
            $keteranganText = !empty($item->kendala) && $item->kendala !== '-' ? $item->kendala : '-';

            /* ============================================================
             * 2. HITUNG TOTAL HASIL (SELALU DALAM M3)
             * ============================================================ */

            $ukuranDisplay = 'TIDAK ADA UKURAN';
            $totalHasil    = 0;
            $kodeUkuran    = null;
            $ukuranId      = null;

            if ($item->detailHasils->isEmpty()) {
                $ukuranDisplay = 'BELUM INPUT HASIL';

                Log::warning('PressDryer tanpa detail hasil', [
                    'id_produksi' => $item->id,
                    'mesin'       => $namaMesin,
                    'shift'       => $shift,
                ]);
            } else {
                $firstHasil = $item->detailHasils->first();
                $ukuranId   = $firstHasil?->id_ukuran ?? null;

                $totalHasil = $item->detailHasils->sum(function ($dh) {
                    $ukuran  = $dh->ukuran ?? null;
                    $panjang = $ukuran?->panjang ?? null;
                    $lebar   = $ukuran?->lebar ?? null;
                    $tebal   = $ukuran?->tebal ?? null;
                    $isi     = $dh->isi ?? 0;

                    if ($panjang && $lebar && $tebal && $isi) {
                        return ($panjang * $lebar * $tebal * $isi) / 10000000;
                    }
                    return 0;
                });
                $totalHasil = round($totalHasil, 4);
            }

            /* ============================================================
             * 3. SUSUN DATA JAM KERJA TIAP PEGAWAI (PekerjaKerjaInput[])
             * ------------------------------------------------------------
             * Beda dari versi lama: TIDAK di-average jadi 1 angka jam/menit
             * kolektif — cukup kasih daftar menit kerja BERSIH tiap orang
             * (sudah dikurangi downtime kendala mesin) ke Action. Strategi
             * Kolektif (di dalam TargetPotonganService) yang menjumlahkan
             * & membagi rata potongannya.
             *
             * Pegawai tanpa data masuk/pulang dianggap kerja penuh sesuai
             * jam normal target (dikurangi downtime juga), supaya tidak
             * "menghilang" dari total menit kerja tim. Jam normal diambil
             * dari target lewat resolveTargetDanRate DULU sebelum loop ini,
             * jadi kita cek nilainya di bawah.
             * ============================================================ */

            $rateInfo = $action->resolveTargetDanRate($mesinEnum);

            if (!$rateInfo) {
                Log::warning('Target Dryer tidak ditemukan untuk shift ini', [
                    'id_produksi' => $item->id,
                    'shift'       => $shift,
                ]);
            }

            $targetModel    = $rateInfo['target'] ?? null;
            $jamKerjaNormal = $targetModel->jam ?? 0;
            $kodeUkuran     = $targetModel->kode_ukuran ?? null;

            $tanggalStr     = Carbon::parse($item->tanggal_produksi)->format('Y-m-d');
            $jamNormalMenit = $jamKerjaNormal * 60;
            $pekerjaInput   = [];

            foreach ($item->detailPegawais as $det) {
                $adaDataJam = !empty($det->masuk) && !empty($det->pulang);
                $grossMenit = $jamNormalMenit; // fallback kalau data jam kosong

                if ($adaDataJam) {
                    $masuk  = Carbon::parse($tanggalStr . ' ' . $det->masuk);
                    $pulang = Carbon::parse($tanggalStr . ' ' . $det->pulang);

                    if ($pulang->lessThan($masuk)) {
                        $pulang->addDay();
                    }

                    $grossMenit = $masuk->diffInMinutes($pulang);
                }

                // Istirahat cuma berlaku untuk SHIFT MALAM (dan cuma kalau
                // datanya dari masuk/pulang RIIL, bukan fallback jam normal
                // yang sudah basis bersih). Shift PAGI tidak ada potongan
                // istirahat sama sekali — cuma downtime kendala.
                $istirahat     = ($shift === 'MALAM' && $adaDataJam) ? self::ISTIRAHAT_MENIT : 0;
                $netMenitOrang = max(0, $grossMenit - $istirahat - $totalDowntimeMenit);
                $idPegawai     = (string) ($det->id_pegawai ?? $det->pegawai?->id ?? $det->id);

                $pekerjaInput[] = new PekerjaKerjaInput(
                    idPegawai: $idPegawai,
                    menitKerja: (float) $netMenitOrang,
                );
            }

            $jumlahPekerja = $item->detailPegawais->count();

            /* ============================================================
             * 4. HITUNG TARGET (ADJUSTED) & POTONGAN — STRATEGI KOLEKTIF
             * ------------------------------------------------------------
             * Lewat HitungPotonganProduksiAction -> ShiftBasedResolver ->
             * TargetPotonganService -> KolektifStrategy (default untuk
             * Dryer): 1 target buat 1 shift, potongan dibagi RATA ke semua
             * pekerja shift itu.
             * ============================================================ */

            $hitung = $action->execute(
                mesin: $mesinEnum,
                strategi: StrategiPembagian::Kolektif,
                pekerja: $pekerjaInput,
                hasilAktual: $totalHasil,
            );

            $targetNormal     = $targetModel->target ?? 0;
            $targetAdjusted   = $hitung?->targetAdjusted ?? 0;
            $selisihProduksi  = $totalHasil - $targetAdjusted;
            $potonganTotal    = $hitung?->potongan ?? 0;

            $totalMenitTim = array_sum(array_map(fn($p) => $p->menitKerja, $pekerjaInput));
            $jamAktualRata = $jumlahPekerja > 0 ? ($totalMenitTim / $jumlahPekerja) / 60 : 0;

            /* ============================================================
             * 5. FORMAT UKURAN (label tampilan)
             * ============================================================ */
            if ($kodeUkuran && $kodeUkuran !== '') {
                $ukuranDisplay = preg_replace(
                    '/^(SPINDLESS|YUEQUN|MERANTI|SANJI|DRYER\s*PAGI|DRYER\s*MALAM|PRESS)\s*/i',
                    '',
                    $kodeUkuran
                );
                $ukuranDisplay = trim($ukuranDisplay) ?: $kodeUkuran;
            } elseif ($totalHasil == 0) {
                $ukuranDisplay = 'BELUM INPUT HASIL';
            } else {
                $ukuranDisplay = "UKURAN BELUM DISET (id: {$ukuranId})";
            }

            /* ============================================================
             * 6. DETAIL PEKERJA (potongan diambil dari map per-pegawai)
             * ============================================================ */

            $pekerja = $item->detailPegawais->map(function ($det) use ($hitung) {
                $idPegawai  = (string) ($det->id_pegawai ?? $det->pegawai?->id ?? $det->id);
                $potPegawai = $hitung?->potonganPerPegawai[$idPegawai] ?? 0;

                return [
                    'id'                   => $det->pegawai->kode_pegawai ?? '-',
                    'nama'                 => $det->pegawai->nama_pegawai ?? '-',
                    'jam_masuk'            => $det->masuk ?? '-',
                    'jam_pulang'           => $det->pulang ?? '-',
                    'ijin'                 => $det->ijin ?? '-',
                    'keterangan'           => $det->keterangan ?? '-',
                    'pot_target'           => $potPegawai,
                    'pot_target_formatted' => 'Rp ' . number_format($potPegawai, 0, ',', '.'),
                ];
            })->toArray();

            /* ============================================================
             * 7. DETAIL HASIL PER PALET
             * ============================================================ */

            $detailHasils = $item->detailHasils->map(function ($dh) {
                $ukuran  = $dh->ukuran ?? null;
                $panjang = $ukuran?->panjang ?? null;
                $lebar   = $ukuran?->lebar   ?? null;
                $tebal   = $ukuran?->tebal   ?? null;
                $isi     = $dh->isi ?? 0;

                $m3 = null;
                if ($panjang && $lebar && $tebal && $isi) {
                    $m3 = round(($panjang * $lebar * $tebal * $isi) / 10000000, 4);
                }

                $jenisKayu = $dh->jenisKayu?->kode_kayu ?? '-';
                $kw = (int) ($dh->kw ?? 0);

                return [
                    'no_palet'   => $dh->no_palet ?? '-',
                    'isi'        => $isi,
                    'kw'         => $kw,
                    'jenis_kayu' => $jenisKayu,
                    'm3'         => $m3,
                    'ukuran'     => [
                        'p'     => $panjang,
                        'l'     => $lebar,
                        't'     => $tebal,
                        'label' => $panjang && $lebar && $tebal ? "{$panjang}x{$lebar}x{$tebal}" : '-',
                    ],
                ];
            })->toArray();

            $detailMasuks = $item->detailMasuks->map(function ($dm) {
                $ukuran  = $dm->ukuran ?? null;
                $panjang = $ukuran?->panjang ?? null;
                $lebar   = $ukuran?->lebar   ?? null;
                $tebal   = $ukuran?->tebal   ?? null;
                $isi     = $dm->isi ?? 0;

                $m3 = null;
                if ($panjang && $lebar && $tebal && $isi) {
                    $m3 = round(($panjang * $lebar * $tebal * $isi) / 10000000, 8);
                }

                return [
                    'isi'        => $isi,
                    'm3'         => $m3,
                    'jenis_kayu' => $dm->jenisKayu?->kode_kayu ?? '-',
                    'ukuran'     => ['p' => $panjang, 'l' => $lebar, 't' => $tebal],
                ];
            })->toArray();

            $targetPerJamNormal = $jamKerjaNormal > 0 ? round($targetNormal / $jamKerjaNormal, 4) : 0;

            /* ============================================================
             * 8. MASUKKAN KE RESULT
             * ============================================================ */

            $result[] = [
                'mesin'      => $namaMesin . ' - ' . $shift,
                'mesin_only' => $namaMesin,
                'shift'      => $shift,
                'tanggal'    => $tanggal,

                'ukuran'          => $ukuranDisplay,
                'ukuran_id'       => $ukuranId,
                'kode_ukuran_raw' => $kodeUkuran,

                'pekerja'              => $pekerja,
                'kendala'              => $kendalaText,
                'keterangan_global'    => $keteranganText,
                'daftar_kendala'       => $daftarKendala,
                'total_downtime_menit' => $totalDowntimeMenit,
                'target_per_jam'       => $targetPerJamNormal,

                'jam_kerja'      => $jamKerjaNormal,   // jam normal (info tampilan)
                'jam_aktual'     => $jamAktualRata,     // rata-rata jam aktual bersih per orang
                'jumlah_pekerja' => $jumlahPekerja,

                'target'          => $targetNormal,
                'target_adjusted' => $targetAdjusted,
                'hasil'           => $totalHasil,
                'selisih'         => $selisihProduksi,

                'potongan_total' => $potonganTotal,

                'has_target' => $targetModel !== null,

                'detail_hasils' => $detailHasils,
                'detail_masuks' => $detailMasuks,
            ];
        }

        return $result;
    }
}