<?php

namespace App\Filament\Pages\LaporanPressDryer\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class DryerDataMap
{
    public static function make($collection)
    {
        $result = [];

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

            $mesinUtamaId = $item->detailMesins->first()?->id_mesin_dryer;

            $shift = strtoupper($item->shift ?? 'PAGI');
            $tanggal = Carbon::parse($item->tanggal_produksi)->format('d/m/Y');

            // ---------------------------------------------------------
            // HITUNG KENDALA / DOWNTIME DARI MODEL BARU (kendalaPressDryers)
            // ---------------------------------------------------------
            $totalKendalaMenit = 0;
            $totalDowntimeMenit = 0;
            $daftarKendala = [];
            $daftarDowntime = [];

            if (!empty($item->kendalaPressDryers) && $item->kendalaPressDryers->count() > 0) {
                foreach ($item->kendalaPressDryers as $knd) {
                    if ($knd->status === 'selesai' && !is_null($knd->durasi_menit)) {
                        $durasiMenit = (int)$knd->durasi_menit;
                        $totalDowntimeMenit += $durasiMenit;

                        $mulai = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $selisihTime = $knd->waktu_selesai ? Carbon::parse($knd->waktu_selesai) : null;

                        $timeStr = ($mulai && $selisihTime) ? ': ' . $mulai->format('H:i') . '-' . $selisihTime->format('H:i') : '';
                        $formattedText = ($knd->kendala ?? 'Tidak disebutkan') . ' (' . $durasiMenit . ' menit' . $timeStr . ')';

                        $daftarKendala[] = [
                            'kendala' => $knd->kendala ?? 'Tidak disebutkan',
                            'keterangan' => '-',
                            'durasi_menit' => $durasiMenit,
                            'jam_mulai' => $mulai ? $mulai->format('H:i') : '-',
                            'jam_selesai' => $selisihTime ? $selisihTime->format('H:i') : '-',
                            'text' => $formattedText,
                        ];
                    } else {
                        // Include pending/in-progress kendalas in the text list so they are visible
                        $mulai = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $timeStr = $mulai ? ' (Mulai: ' . $mulai->format('H:i') . ' - Pending)' : ' (Pending)';
                        $formattedText = ($knd->kendala ?? 'Tidak disebutkan') . $timeStr;

                        $daftarKendala[] = [
                            'kendala' => $knd->kendala ?? 'Tidak disebutkan',
                            'keterangan' => '-',
                            'durasi_menit' => 0,
                            'jam_mulai' => $mulai ? $mulai->format('H:i') : '-',
                            'jam_selesai' => '-',
                            'text' => $formattedText,
                        ];
                    }
                }
            }

            $totalKendalaMenit = $totalDowntimeMenit;
            $daftarDowntime = $daftarKendala;

            $totalDowntimeFormatted = '';
            if ($totalDowntimeMenit >= 60) {
                $jam = floor($totalDowntimeMenit / 60);
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
            $totalHasil = 0;

            $targetHarian = 0;
            $jamKerja = 0;
            $potonganPerLembar = 0;
            $targetPerJam = 0;

            $kodeUkuran = null;
            $ukuranId = null;

            $targetModel = null;


            /* ============================================================
             * 3. CEK DETAIL HASIL & CARI TARGET
             * ============================================================ */

            if ($item->detailHasils->isEmpty()) {

                $ukuranDisplay = 'BELUM INPUT HASIL';
                $totalHasil = 0;

                if ($mesinUtamaId) {

                    if (stripos($namaMesin, 'DRYER') !== false) {
                        if ($shift === 'MALAM') {
                            $targetModel = Target::where('kode_ukuran', 'DRYER MALAM')->first();
                        } else {
                            $targetModel = Target::where('kode_ukuran', 'DRYER PAGI')->first();
                        }
                    } elseif (stripos($namaMesin, 'DRYER 1') !== false || $mesinUtamaId == 17) {
                        $targetModel = Target::where('kode_ukuran', 'DRYER PAGI')->first();
                    } elseif (stripos($namaMesin, 'DRYER 2') !== false || $mesinUtamaId == 18) {
                        $targetModel = Target::where('kode_ukuran', 'DRYER MALAM')->first();
                    } else {
                        $targetModel = Target::where('id_mesin', $mesinUtamaId)
                            ->whereNull('id_ukuran')
                            ->first();
                    }
                }

                Log::warning('PressDryer tanpa detail hasil (target tetap dicari)', [
                    'id_produksi' => $item->id,
                    'mesin' => $namaMesin,
                    'shift' => $shift,
                    'target_ditemukan' => $targetModel !== null,
                    'target' => $targetModel->target ?? 0,
                ]);

            } else {

                /* 3A. Ambil ukuran & total hasil */
                $firstHasil = $item->detailHasils->first();
                $ukuranId = $firstHasil?->id_ukuran ?? null;

                if (stripos($namaMesin, 'DRYER') !== false) {
                    // Dryer uses kubikasi (m3)
                    $totalHasil = $item->detailHasils->sum(function ($dh) {
                        $ukuran = $dh->ukuran ?? null;
                        $panjang = $ukuran?->panjang ?? null;
                        $lebar = $ukuran?->lebar ?? null;
                        $tebal = $ukuran?->tebal ?? null;
                        $isi = $dh->isi ?? 0;

                        if ($panjang && $lebar && $tebal && $isi) {
                            return ($panjang * $lebar * $tebal * $isi) / 10000000;
                        }
                        return 0;
                    });
                    $totalHasil = round($totalHasil, 4);
                } else {
                    $totalHasil = $item->detailHasils->sum('isi') ?? 0;
                }

                /* 3B. Cari target: mesin + ukuran */
                if ($mesinUtamaId) {

                    if (stripos($namaMesin, 'DRYER') !== false) {
                        if ($shift === 'MALAM') {
                            $targetModel = Target::where('kode_ukuran', 'DRYER MALAM')->first();
                        } else {
                            $targetModel = Target::where('kode_ukuran', 'DRYER PAGI')->first();
                        }
                    } elseif (stripos($namaMesin, 'DRYER 1') !== false || $mesinUtamaId == 17) {
                        $targetModel = Target::where('kode_ukuran', 'DRYER PAGI')->first();
                    } elseif (stripos($namaMesin, 'DRYER 2') !== false || $mesinUtamaId == 18) {
                        $targetModel = Target::where('kode_ukuran', 'DRYER MALAM')->first();
                    } else {
                        $targetModel = Target::where('id_mesin', $mesinUtamaId)
                            ->when($ukuranId !== null, function ($q) use ($ukuranId) {
                                return $q->where('id_ukuran', $ukuranId);
                            })
                            ->first();

                        if (!$targetModel) {
                            $targetModel = Target::where('id_mesin', $mesinUtamaId)
                                ->whereNull('id_ukuran')
                                ->first();
                        }
                    }

                }
            }

            /* ============================================================
             * 3C. FALLBACK JIKA TARGET BELUM DITEMUKAN
             * ============================================================ */
            if ($targetModel === null && $mesinUtamaId) {
                if (stripos($namaMesin, 'DRYER') !== false) {
                    if ($shift === 'MALAM') {
                        $targetModel = Target::where('kode_ukuran', 'DRYER MALAM')->first();
                    } else {
                        $targetModel = Target::where('kode_ukuran', 'DRYER PAGI')->first();
                    }
                } elseif (stripos($namaMesin, 'DRYER 1') !== false || $mesinUtamaId == 17) {
                    $targetModel = Target::where('kode_ukuran', 'DRYER PAGI')->first();
                } elseif (stripos($namaMesin, 'DRYER 2') !== false || $mesinUtamaId == 18) {
                    $targetModel = Target::where('kode_ukuran', 'DRYER MALAM')->first();
                } else {
                    $targetModel = Target::where('id_mesin', $mesinUtamaId)
                        ->whereNull('id_ukuran')
                        ->first();
                }
            }

            $targetHarian = $targetModel->target ?? 0;
            $jamKerja = $targetModel->jam ?? 0;
            $potonganPerLembar = $targetModel->potongan ?? 0;
            $kodeUkuran = $targetModel->kode_ukuran ?? null;

            /* ============================================================
             * 3D. FORMAT UKURAN
             * ============================================================ */
            if ($kodeUkuran && $kodeUkuran !== '') {
                $ukuranDisplay = preg_replace(
                    '/^(SPINDLESS|YUEQUN|MERANTI|SANJI|DRYER\s*PAGI|DRYER\s*MALAM|PRESS)\s*/i',
                    '',
                    $kodeUkuran
                );
                $ukuranDisplay = trim($ukuranDisplay) ?: $kodeUkuran;
            } else {
                if ($totalHasil === 0) {
                    $ukuranDisplay = 'BELUM INPUT HASIL';
                } else {
                    $ukuranDisplay = "UKURAN BELUM DISET (id: {$ukuranId})";
                }
            }


            /* ============================================================
             * 4. HITUNG POTONGAN TARGET (PEMBULATAN 3 TINGKAT)
             * ============================================================ */

            $selisihProduksi = $totalHasil - $targetHarian;
            $jumlahPekerja = $item->detailPegawais->count();

            $potonganTotal = 0;
            $potonganPerOrang = 0;

            if ($targetHarian > 0 && $selisihProduksi < 0 && $potonganPerLembar > 0) {
                $potonganTotal = abs($selisihProduksi) * $potonganPerLembar;

                if ($jumlahPekerja > 0) {
                    $potonganPerOrangRaw = $potonganTotal / $jumlahPekerja;

                    $ribuan = floor($potonganPerOrangRaw / 1000);
                    $ratusan = (int) $potonganPerOrangRaw % 1000;

                    if ($ratusan < 300) {
                        $potonganPerOrang = $ribuan * 1000;
                    } elseif ($ratusan >= 300 && $ratusan < 800) {
                        $potonganPerOrang = ($ribuan * 1000) + 500;
                    } else {
                        $potonganPerOrang = ($ribuan + 1) * 1000;
                    }
                }
            }


            /* ============================================================
             * 5. DETAIL PEKERJA
             * ============================================================ */

            $pekerja = $item->detailPegawais->map(function ($det) use ($potonganPerOrang) {
                return [
                    'id' => $det->pegawai->kode_pegawai ?? '-',
                    'nama' => $det->pegawai->nama_pegawai ?? '-',
                    'jam_masuk' => $det->masuk ?? '-',
                    'jam_pulang' => $det->pulang ?? '-',
                    'ijin' => $det->ijin ?? '-',
                    'keterangan' => $det->keterangan ?? '-',
                    'pot_target' => $potonganPerOrang,
                    'pot_target_formatted' => 'Rp ' . number_format($potonganPerOrang, 0, ',', '.'),
                ];
            })->toArray();


            /* ============================================================
             * 5B. DETAIL HASIL PER PALET (untuk sheet Hasil Produksi)
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
             * 5C. DETAIL MASUK (MODAL UNTUK MENGHITUNG KEHILANGAN)
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


            $targetPerJam = $jamKerja > 0 ? round($targetHarian / $jamKerja, 4) : 0;

            /* ============================================================
             * 6. MASUKKAN KE RESULT
             * ============================================================ */

            $result[] = [
                'mesin' => $namaMesin . ' - ' . $shift,
                'mesin_only' => $namaMesin,
                'shift' => $shift,
                'tanggal' => $tanggal,

                'ukuran' => $ukuranDisplay,
                'ukuran_id' => $ukuranId,
                'kode_ukuran_raw' => $kodeUkuran,

                'pekerja' => $pekerja,
                'kendala' => $kendalaText,
                'keterangan_global' => $keteranganText,
                'daftar_kendala' => $daftarKendala,
                'daftar_downtime' => $daftarDowntime,
                'total_downtime_menit' => $totalDowntimeMenit,
                'total_kendala_menit' => $totalKendalaMenit,
                'target_per_jam' => $targetPerJam,

                'jam_kerja' => $jamKerja,
                'target' => $targetHarian,
                'hasil' => $totalHasil,
                'selisih' => $selisihProduksi,

                'potongan_total' => $potonganTotal,
                'potongan_per_orang' => $potonganPerOrang,

                'has_target' => $targetModel !== null,

                'detail_hasils' => $detailHasils,
                'detail_masuks' => $detailMasuks,
                'jumlah_pekerja' => $jumlahPekerja,
            ];

        }

        return $result;
    }
}