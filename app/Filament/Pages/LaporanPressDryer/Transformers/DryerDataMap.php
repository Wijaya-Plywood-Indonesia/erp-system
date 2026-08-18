<?php

namespace App\Filament\Pages\LaporanPressDryer\Transformers;

use Carbon\Carbon;
use App\Enums\Mesin;
use App\Actions\HitungPotonganProduksiAction;
use App\Services\Target\TargetResolverFactory;
use Illuminate\Support\Facades\Log;

class DryerDataMap
{
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

            // Mesin enum untuk shift ini (dipakai Resolver & Action)
            $mesinEnum = $shift === 'MALAM' ? Mesin::DryerMalam : Mesin::DryerPagi;

            // ---------------------------------------------------------
            // HITUNG KENDALA / DOWNTIME DARI MODEL BARU (kendalaPressDryers)
            // ---------------------------------------------------------
            $totalKendalaMenit  = 0;
            $totalDowntimeMenit = 0;
            $daftarKendala      = [];
            $daftarDowntime     = [];

            if (!empty($item->kendalaPressDryers) && $item->kendalaPressDryers->count() > 0) {
                foreach ($item->kendalaPressDryers as $knd) {
                    if ($knd->status === 'selesai' && !is_null($knd->durasi_menit)) {
                        $durasiMenit = (int) $knd->durasi_menit;
                        $totalDowntimeMenit += $durasiMenit;

                        $mulai       = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $selisihTime = $knd->waktu_selesai ? Carbon::parse($knd->waktu_selesai) : null;

                        $timeStr       = ($mulai && $selisihTime) ? ': ' . $mulai->format('H:i') . '-' . $selisihTime->format('H:i') : '';
                        $formattedText = ($knd->kendala ?? 'Tidak disebutkan') . ' (' . $durasiMenit . ' menit' . $timeStr . ')';

                        $daftarKendala[] = [
                            'kendala'      => $knd->kendala ?? 'Tidak disebutkan',
                            'keterangan'   => '-',
                            'durasi_menit' => $durasiMenit,
                            'jam_mulai'    => $mulai ? $mulai->format('H:i') : '-',
                            'jam_selesai'  => $selisihTime ? $selisihTime->format('H:i') : '-',
                            'text'         => $formattedText,
                        ];
                    } else {
                        $mulai   = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $timeStr = $mulai ? ' (Mulai: ' . $mulai->format('H:i') . ' - Pending)' : ' (Pending)';
                        $formattedText = ($knd->kendala ?? 'Tidak disebutkan') . $timeStr;

                        $daftarKendala[] = [
                            'kendala'      => $knd->kendala ?? 'Tidak disebutkan',
                            'keterangan'   => '-',
                            'durasi_menit' => 0,
                            'jam_mulai'    => $mulai ? $mulai->format('H:i') : '-',
                            'jam_selesai'  => '-',
                            'text'         => $formattedText,
                        ];
                    }
                }
            }

            $totalKendalaMenit = $totalDowntimeMenit;
            $daftarDowntime    = $daftarKendala;

            $totalDowntimeFormatted = '';
            if ($totalDowntimeMenit >= 60) {
                $jam   = floor($totalDowntimeMenit / 60);
                $menit = $totalDowntimeMenit % 60;
                $totalDowntimeFormatted = "{$jam} Jam {$menit} Menit";
            } else {
                $totalDowntimeFormatted = "{$totalDowntimeMenit} Menit";
            }

            $kendalaText = '-';
            if (count($daftarKendala) > 0) {
                $kendalaText = implode(', ', array_column($daftarKendala, 'text'));
            }

            $keteranganText = !empty($item->kendala) && $item->kendala !== '-' ? $item->kendala : '-';


            /* ============================================================
             * 2. DEFAULT (WAJIB)
             * ============================================================ */

            $ukuranDisplay = 'TIDAK ADA UKURAN';
            $totalHasil    = 0;

            $kodeUkuran = null;
            $ukuranId   = null;


            /* ============================================================
             * 3. HITUNG TOTAL HASIL (SELALU DALAM M3)
             * ============================================================ */

            if ($item->detailHasils->isEmpty()) {

                $ukuranDisplay = 'BELUM INPUT HASIL';
                $totalHasil    = 0;

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
             * 4. AMBIL TARGET NORMAL (untuk fallback jam & rate)
             * ------------------------------------------------------------
             * Diambil duluan (sebelum hitung jam aktual) karena kita butuh
             * jam normal sebagai fallback untuk pegawai yang datanya masuk/
             * pulang belum diisi.
             * ============================================================ */

            $resolver    = TargetResolverFactory::make($mesinEnum);
            $targetModel = $resolver->resolve($mesinEnum->value);

            if (!$targetModel) {
                Log::warning('Target Dryer tidak ditemukan untuk shift ini', [
                    'id_produksi' => $item->id,
                    'shift'       => $shift,
                ]);
            }

            $targetNormal   = $targetModel->target ?? 0;
            $jamKerjaNormal = $targetModel->jam ?? 0;
            $kodeUkuran     = $targetModel->kode_ukuran ?? null;

            /* ============================================================
             * 5. HITUNG JAM AKTUAL (TOTAL ORG-MENIT, BUKAN RENTANG WAKTU)
             * ------------------------------------------------------------
             * Setiap pegawai punya jam kerja sendiri (masuk-pulang). Kita
             * jumlahkan MENIT KERJA per orang satu-satu (bukan cuma ambil
             * rentang terluas), supaya pegawai yang kerja lebih pendek dari
             * yang lain ikut mengecilkan target secara proporsional.
             *
             * Downtime (kendala mesin, Opsi B) dikurangi dari menit kerja
             * TIAP orang (karena saat mesin berhenti, semua yang sedang
             * kerja ikut terhenti), baru dijumlahkan.
             *
             * Pegawai yang datanya masuk/pulang belum diisi, dianggap kerja
             * penuh sesuai jam normal target (dikurangi downtime juga),
             * supaya tidak "menghilang" dari total org-menit.
             * ============================================================ */

            $tanggalStr        = Carbon::parse($item->tanggal_produksi)->format('Y-m-d');
            $jamNormalMenit    = $jamKerjaNormal * 60;
            $totalPersonMenit  = 0;

            foreach ($item->detailPegawais as $det) {
                $grossMenit = $jamNormalMenit; // fallback kalau data jam kosong

                if (!empty($det->masuk) && !empty($det->pulang)) {
                    $masuk  = Carbon::parse($tanggalStr . ' ' . $det->masuk);
                    $pulang = Carbon::parse($tanggalStr . ' ' . $det->pulang);

                    // Jaga-jaga kalau shift malam lewat tengah malam
                    if ($pulang->lessThan($masuk)) {
                        $pulang->addDay();
                    }

                    $grossMenit = $masuk->diffInMinutes($pulang);
                }

                $netMenitOrang = max(0, $grossMenit - $totalDowntimeMenit);
                $totalPersonMenit += $netMenitOrang;
            }

            $jumlahPekerja = $item->detailPegawais->count();

            // Rata-rata menit efektif per orang (dipakai sebagai menitAktual
            // di Service, dengan orgAktual = jumlah pekerja sebenarnya, agar
            // potongan tetap dibagi rata ke headcount yang benar).
            $avgMenitPerOrang = $jumlahPekerja > 0 ? $totalPersonMenit / $jumlahPekerja : 0;

            $jamAktual   = (int) floor($avgMenitPerOrang / 60);
            $menitAktual = $avgMenitPerOrang - ($jamAktual * 60); // sisa dalam bentuk desimal, tetap valid

            /* ============================================================
             * 6. HITUNG TARGET (ADJUSTED) & POTONGAN
             * ------------------------------------------------------------
             * Lewat HitungPotonganProduksiAction -> TargetResolverFactory
             * (ShiftBasedResolver untuk Dryer) -> TargetPotonganService.
             * Target normal ikut menyusut proporsional sesuai org aktual
             * & total menit kerja aktual (org-menit, sudah dipotong downtime).
             * ============================================================ */

            $hitung = $action->execute(
                mesin: $mesinEnum,
                orgAktual: $jumlahPekerja,
                jamAktual: (float) $jamAktual,
                menitAktual: (float) $menitAktual,
                hasilAktual: $totalHasil,
            );

            $targetAdjusted   = $hitung?->targetAdjusted ?? 0;
            $selisihProduksi  = $totalHasil - $targetAdjusted;
            $potonganTotal    = $hitung?->potongan ?? 0;
            $potonganPerOrang = $hitung?->potonganPerOrang ?? 0;

            /* ============================================================
             * 7. FORMAT UKURAN (label tampilan)
             * ============================================================ */
            if ($kodeUkuran && $kodeUkuran !== '') {
                $ukuranDisplay = preg_replace(
                    '/^(SPINDLESS|YUEQUN|MERANTI|SANJI|DRYER\s*PAGI|DRYER\s*MALAM|PRESS)\s*/i',
                    '',
                    $kodeUkuran
                );
                $ukuranDisplay = trim($ukuranDisplay) ?: $kodeUkuran;
            } else {
                if ($totalHasil == 0) {
                    $ukuranDisplay = 'BELUM INPUT HASIL';
                } else {
                    $ukuranDisplay = "UKURAN BELUM DISET (id: {$ukuranId})";
                }
            }


            /* ============================================================
             * 8. DETAIL PEKERJA
             * ============================================================ */

            $pekerja = $item->detailPegawais->map(function ($det) use ($potonganPerOrang) {
                return [
                    'id'                   => $det->pegawai->kode_pegawai ?? '-',
                    'nama'                 => $det->pegawai->nama_pegawai ?? '-',
                    'jam_masuk'            => $det->masuk ?? '-',
                    'jam_pulang'           => $det->pulang ?? '-',
                    'ijin'                 => $det->ijin ?? '-',
                    'keterangan'           => $det->keterangan ?? '-',
                    'pot_target'           => $potonganPerOrang,
                    'pot_target_formatted' => 'Rp ' . number_format($potonganPerOrang, 0, ',', '.'),
                ];
            })->toArray();


            /* ============================================================
             * 8B. DETAIL HASIL PER PALET (untuk sheet Hasil Produksi)
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
                    'kw1'        => $kw === 1 ? $isi : '',
                    'kw2'        => $kw === 2 ? $isi : '',
                    'kw3'        => $kw === 3 ? $isi : '',
                    'kw4'        => $kw === 4 ? $isi : '',
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


            /* ============================================================
             * 8C. DETAIL MASUK (MODAL UNTUK MENGHITUNG KEHILANGAN)
             * ============================================================ */
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
                    'ukuran'     => [
                        'p' => $panjang,
                        'l' => $lebar,
                        't' => $tebal,
                    ],
                ];
            })->toArray();


            $targetPerJamNormal = $jamKerjaNormal > 0 ? round($targetNormal / $jamKerjaNormal, 4) : 0;
            $jamAktualDecimal   = $jamAktual + ($menitAktual / 60);

            /* ============================================================
             * 9. MASUKKAN KE RESULT
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
                'daftar_downtime'      => $daftarDowntime,
                'total_downtime_menit' => $totalDowntimeMenit,
                'total_kendala_menit'  => $totalKendalaMenit,
                'target_per_jam'       => $targetPerJamNormal,

                // Jam & org normal (dari master Target) vs aktual (dipakai utk penyesuaian)
                'jam_kerja'      => $jamKerjaNormal,   // jam normal (info tampilan)
                'jam_aktual'     => $jamAktualDecimal, // jam aktual setelah dipotong downtime
                'jumlah_pekerja' => $jumlahPekerja,

                'target'          => $targetNormal,    // target normal (info tampilan)
                'target_adjusted' => $targetAdjusted,  // target setelah disesuaikan org & jam aktual
                'hasil'           => $totalHasil,
                'selisih'         => $selisihProduksi, // hasil - target_adjusted

                'potongan_total'    => $potonganTotal,
                'potongan_per_orang' => $potonganPerOrang,

                'has_target' => $targetModel !== null,

                'detail_hasils' => $detailHasils,
                'detail_masuks' => $detailMasuks,
            ];

        }

        return $result;
    }
}