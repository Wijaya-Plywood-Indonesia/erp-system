<?php

namespace App\Filament\Pages\LaporanProduksi\Transformers;

use App\DataTransferObjects\PekerjaKerjaInput;
use App\Models\Target;
use App\Services\Target\Strategies\ProporsionalStrategy;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class ProduksiDataMap
{
    public static function make($collection)
    {
        $result = [];

        foreach ($collection as $item) {

            $namaMesin = $item->mesin->nama_mesin ?? 'TIDAK ADA MESIN';
            $tanggal = Carbon::parse($item->tgl_produksi)->format('d/m/Y');

            // ---------------------------------------------------------
            // KENDALA / DOWNTIME
            // ---------------------------------------------------------
            $totalDowntimeMenit = 0;
            $daftarKendala = [];
            $merged = []; // interval downtime gabungan (timestamp), dipakai juga per pekerja

            if (! empty($item->kendalaRotaries) && $item->kendalaRotaries->count() > 0) {
                $intervals = [];

                foreach ($item->kendalaRotaries as $knd) {
                    if ($knd->status === 'selesai' && ! is_null($knd->durasi_menit)) {
                        $durasiMenit = (int) $knd->durasi_menit;

                        $mulai = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $selesai = $knd->waktu_selesai ? Carbon::parse($knd->waktu_selesai) : null;

                        if ($mulai && $selesai && $selesai->timestamp > $mulai->timestamp) {
                            $intervals[] = ['start' => $mulai->timestamp, 'end' => $selesai->timestamp];
                        }

                        $timeStr = ($mulai && $selesai) ? ': '.$mulai->format('H:i').'-'.$selesai->format('H:i') : '';
                        $formattedText = ($knd->kendala ?? 'Tidak disebutkan').' ('.$durasiMenit.' menit'.$timeStr.')';

                        $daftarKendala[] = [
                            'kendala' => $knd->kendala ?? 'Tidak disebutkan',
                            'keterangan' => '-',
                            'durasi_menit' => $durasiMenit,
                            'jam_mulai' => $mulai ? $mulai->format('H:i') : '-',
                            'jam_selesai' => $selesai ? $selesai->format('H:i') : '-',
                            'text' => $formattedText,
                        ];
                    } else {
                        $mulai = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $timeStr = $mulai ? ' (Mulai: '.$mulai->format('H:i').' - Pending)' : ' (Pending)';
                        $formattedText = ($knd->kendala ?? 'Tidak disebutkan').$timeStr;

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

                if (! empty($intervals)) {
                    usort($intervals, fn ($a, $b) => $a['start'] <=> $b['start']);

                    foreach ($intervals as $interval) {
                        $lastIndex = count($merged) - 1;
                        if ($lastIndex >= 0 && $interval['start'] <= $merged[$lastIndex]['end']) {
                            $merged[$lastIndex]['end'] = max($merged[$lastIndex]['end'], $interval['end']);
                        } else {
                            $merged[] = $interval;
                        }
                    }

                    $totalDowntimeSeconds = 0;
                    foreach ($merged as $interval) {
                        $totalDowntimeSeconds += ($interval['end'] - $interval['start']);
                    }
                    $totalDowntimeMenit = (int) round($totalDowntimeSeconds / 60.0);
                }
            }

            $totalKendalaMenit = $totalDowntimeMenit;
            $daftarDowntime = $daftarKendala;

            if ($totalDowntimeMenit >= 60) {
                $jam = floor($totalDowntimeMenit / 60);
                $menit = $totalDowntimeMenit % 60;
                $totalDowntimeFormatted = "{$jam} Jam {$menit} Menit";
            } else {
                $totalDowntimeFormatted = "{$totalDowntimeMenit} Menit";
            }

            $kendalaText = count($daftarKendala) > 0
                ? implode(', ', array_column($daftarKendala, 'text'))
                : '-';

            // ---------------------------------------------------------
            // JAM KERJA NORMAL HARI INI (batas atas jam hadir per orang)
            // ---------------------------------------------------------
            $jumlahPekerja = $item->detailPegawaiRotary->count();

            $firstPaletForJam = $item->detailPaletRotary->first();
            $jamKerjaMasterModel = $firstPaletForJam
                ? Target::where('id_mesin', $item->id_mesin)
                    ->where('id_ukuran', $firstPaletForJam->id_ukuran)
                    ->first()
                : null;
            if (! $jamKerjaMasterModel) {
                $jamKerjaMasterModel = Target::where('id_mesin', $item->id_mesin)
                    ->whereNull('id_ukuran')
                    ->first();
            }
            $jamKerja = (float) ($jamKerjaMasterModel?->jam ?? 0);

            $jamKerjaNormalHariIni = $jamKerja;
            $prodDate = Carbon::parse($item->tgl_produksi);
            if ($prodDate->isFriday() && $jamKerjaNormalHariIni > 2) {
                $jamKerjaNormalHariIni -= 2;
            }
            $jamKerjaMenit = $jamKerjaNormalHariIni * 60;

            // ---------------------------------------------------------
            // MENIT HADIR REAL PER PEKERJA (jam_masuk/jam_pulang dikurangi
            // downtime yang beririsan dengan jam hadirnya). Dihitung SEKALI
            // di sini, dipakai untuk target, pembagian potongan & tampilan.
            // ---------------------------------------------------------
            $tglBase = Carbon::parse($item->tgl_produksi)->startOfDay();
            $pekerjaInput = [];
            $menitPerPekerja = []; // indeks sama dengan detailPegawaiRotary->values()
            $totalMenitOrang = 0.0;

            foreach ($item->detailPegawaiRotary->values() as $i => $det) {
                $kode = $det->pegawai->kode_pegawai ?? (string) ($det->id_pegawai ?? $det->id);

                $menitHadir = self::hitungMenitHadir($det, $tglBase, $merged, $jamKerjaMenit, $totalDowntimeMenit);

                $menitPerPekerja[$i] = $menitHadir;
                $totalMenitOrang += $menitHadir;
                $pekerjaInput[] = new PekerjaKerjaInput(
                    idPegawai: (string) $kode,
                    menitKerja: (float) $menitHadir
                );
            }

            $jamKerjaEfektif = $jumlahPekerja > 0 ? ($totalMenitOrang / $jumlahPekerja) / 60 : 0;

            // ---------------------------------------------------------
            // KELOMPOKKAN detailPaletRotary PER UKURAN
            // ---------------------------------------------------------
            if ($item->detailPaletRotary->isEmpty()) {
                Log::warning('Produksi tanpa detail palet', [
                    'id_produksi' => $item->id,
                    'mesin' => $namaMesin,
                    'tanggal' => $tanggal,
                ]);

                $ukuranGroups = [];
                $totalHasilSemua = 0;
            } else {
                $paletGrouped = $item->detailPaletRotary->groupBy('id_ukuran');

                $ukuranGroups = [];
                $totalHasilSemua = 0;

                foreach ($paletGrouped as $idUkuran => $paletRows) {
                    $ukuranModel = $paletRows->first()->ukuran;
                    $kwList = $paletRows->pluck('kw')->filter()->unique()->values()->all();
                    $kwDisplay = count($kwList) > 0 ? implode('/', $kwList) : '-';

                    $hasilGrup = (float) $paletRows->sum('total_lembar');
                    $totalHasilSemua += $hasilGrup;

                    $targetModel = Target::where('id_mesin', $item->id_mesin)
                        ->where('id_ukuran', $idUkuran)
                        ->first();

                    if (! $targetModel) {
                        $targetModel = Target::where('id_mesin', $item->id_mesin)
                            ->whereNull('id_ukuran')
                            ->first();
                    }

                    if ($targetModel) {
                        $kodeUkuran = $targetModel->kode_ukuran;
                        $ukuranDisplay = ($kodeUkuran && trim($kodeUkuran) !== '')
                            ? trim(preg_replace('/^(SPINDLESS|YUEQUN|MERANTI|SANJI|DRYER\s*PAGI)/i', '', $kodeUkuran)) ?: $kodeUkuran
                            : self::formatUkuran($ukuranModel, $idUkuran);
                    } elseif ($ukuranModel) {
                        $ukuranDisplay = self::formatUkuran($ukuranModel, $idUkuran);
                    } else {
                        $ukuranDisplay = 'Ukuran ID '.$idUkuran.' (target belum ada di Master Target, data ukuran tidak ditemukan)';
                    }

                    $ukuranGroups[] = [
                        'id_ukuran' => $idUkuran,
                        'ukuran_display' => $ukuranDisplay,
                        'kw' => $kwDisplay,
                        'hasil' => $hasilGrup,
                        'target_model' => $targetModel,
                    ];
                }
            }

            // ---------------------------------------------------------
            // TARGET-ADJUSTED & CAPAIAN (kolektif lintas ukuran)
            // Target disesuaikan ke TOTAL MENIT-ORANG yang benar-benar hadir.
            // ---------------------------------------------------------
            $itemsUkuran = [];
            $sumCapaianPersen = 0;
            $sumNilaiTarget = 0;
            $jumlahUkuranAda = 0;
            $targetNormalTotal = 0;
            $targetAdjustedTotal = 0;
            $gajiPerOrang = 0;

            foreach ($ukuranGroups as $grup) {
                $targetModel = $grup['target_model'];

                if (! $targetModel) {
                    $itemsUkuran[] = [
                        'ukuran' => $grup['ukuran_display'],
                        'kw' => $grup['kw'],
                        'hasil' => $grup['hasil'],
                        'target' => 0,
                        'selisih' => $grup['hasil'],
                        'capaian_persen' => null,
                        'has_target' => false,
                    ];

                    Log::warning('Target Rotary tidak ditemukan untuk ukuran ini', [
                        'id_produksi' => $item->id,
                        'mesin' => $namaMesin,
                        'id_ukuran' => $grup['id_ukuran'],
                    ]);

                    continue;
                }

                $targetNormal = (float) $targetModel->target;
                $orgNormal = (int) $targetModel->orang;
                $jamNormalMenit = (float) $targetModel->jam * 60;
                $biayaPerUnit = (float) ($targetModel->potongan ?? 0);
                $gajiPerOrang = (float) ($targetModel->gaji ?? $gajiPerOrang);

                $ratePerMenit = ($orgNormal > 0 && $jamNormalMenit > 0) ? $targetNormal / $jamNormalMenit : 0;
                $ratePerOrgPerMenit = $orgNormal > 0 ? $ratePerMenit / $orgNormal : 0;

                $targetAdjusted = round($ratePerOrgPerMenit * $totalMenitOrang);

                $capaian = $targetAdjusted > 0 ? ($grup['hasil'] / $targetAdjusted) * 100 : 100.0;
                $nilaiTarget = $targetAdjusted * $biayaPerUnit;

                $sumCapaianPersen += $capaian;
                $sumNilaiTarget += $nilaiTarget;
                $jumlahUkuranAda += 1;
                $targetNormalTotal += $targetNormal;
                $targetAdjustedTotal += $targetAdjusted;

                $itemsUkuran[] = [
                    'ukuran' => $grup['ukuran_display'],
                    'kw' => $grup['kw'],
                    'hasil' => $grup['hasil'],
                    'target' => $targetAdjusted,
                    'target_normal' => $targetNormal,
                    'selisih' => $grup['hasil'] - $targetAdjusted,
                    'capaian_persen' => $capaian,
                    'has_target' => true,
                ];
            }

            $hasTarget = $jumlahUkuranAda > 0;
            $capaianGlobal = $sumCapaianPersen;
            $nilaiSatuHariPenuh = $jumlahUkuranAda > 0 ? ($sumNilaiTarget / $jumlahUkuranAda) : 0;

            // ---------------------------------------------------------
            // POTONGAN — proporsional menit hadir (ProporsionalStrategy)
            // ---------------------------------------------------------
            $potonganTotal = 0;
            $potonganPerPegawai = [];

            if ($hasTarget && $jumlahPekerja > 0 && $totalMenitOrang > 0) {
                if ($totalHasilSemua <= 0) {
                    // Tidak ada hasil sama sekali: tiap orang yang hadir kena
                    // potongan penuh sebesar gaji (konsisten dengan sebelumnya).
                    foreach ($pekerjaInput as $p) {
                        if ($p->menitKerja > 0) {
                            $potonganPerPegawai[$p->idPegawai] = $gajiPerOrang;
                            $potonganTotal += $gajiPerOrang;
                        }
                    }
                } else {
                    $kekuranganPersen = max(0, 100 - $capaianGlobal) / 100;
                    $potonganTotal = $kekuranganPersen * $nilaiSatuHariPenuh;

                    if ($potonganTotal > 0) {
                        $potonganPerPegawai = (new ProporsionalStrategy)->bagikan($pekerjaInput, $potonganTotal);
                    }
                }
            } elseif (! $hasTarget) {
                Log::warning('Tidak ada target ditemukan untuk semua ukuran, potongan tidak dihitung', [
                    'id_produksi' => $item->id,
                    'mesin' => $namaMesin,
                    'id_mesin' => $item->id_mesin,
                ]);
            } elseif ($jumlahPekerja > 0 && $totalMenitOrang <= 0) {
                Log::warning('Total menit hadir pekerja 0, potongan tidak dihitung', [
                    'id_produksi' => $item->id,
                    'mesin' => $namaMesin,
                ]);
            }

            $pekerja = $item->detailPegawaiRotary->values()->map(function ($det, $i) use ($potonganPerPegawai, $menitPerPekerja) {
                $kode = (string) ($det->pegawai->kode_pegawai ?? ($det->id_pegawai ?? $det->id));
                $menitHadir = (float) ($menitPerPekerja[$i] ?? 0);
                $pot = $potonganPerPegawai[$kode] ?? $potonganPerPegawai[ltrim($kode, '0')] ?? 0;

                return [
                    'id' => $det->pegawai->kode_pegawai ?? '-',
                    'nama' => $det->pegawai->nama_pegawai ?? '-',
                    'jam_masuk' => $det->jam_masuk ?? '-',
                    'jam_pulang' => $det->jam_pulang ?? '-',
                    'jam_hadir' => round($menitHadir / 60, 2),
                    'menit_hadir' => round($menitHadir, 1),
                    'ijin' => $det->ijin ?? '-',
                    'keterangan' => $det->keterangan ?? '-',
                    'pot_target' => (int) $pot,
                ];
            })->toArray();

            $potonganDibagikan = (int) round(array_sum($potonganPerPegawai));

            $ukuranDisplayHeader = count($itemsUkuran) > 0
                ? implode(' + ', array_column($itemsUkuran, 'ukuran'))
                : ($item->detailPaletRotary->isEmpty() ? 'BELUM INPUT PALET' : 'TIDAK ADA UKURAN');

            $result[] = [
                'mesin' => $namaMesin,
                'tanggal' => $tanggal,
                'ukuran' => $ukuranDisplayHeader,
                'items' => $itemsUkuran,
                'pekerja' => $pekerja,
                'kendala' => $kendalaText,
                'daftar_kendala' => $daftarKendala,
                'daftar_downtime' => $daftarDowntime,
                'jam_kerja' => $jamKerja,
                // rata-rata jam hadir efektif per orang (sudah dikurangi downtime)
                'jam_kerja_efektif' => round($jamKerjaEfektif, 2),
                'total_menit_orang' => round($totalMenitOrang, 1),
                'total_kendala_menit' => $totalKendalaMenit,
                'total_downtime_menit' => $totalDowntimeMenit,
                'total_downtime_formatted' => $totalDowntimeFormatted,
                'target' => $targetAdjustedTotal,
                'target_normal' => $targetNormalTotal,
                'target_per_jam' => $jamKerja > 0 ? round($targetNormalTotal / $jamKerja, 2) : 0,
                'hasil' => $totalHasilSemua,
                'selisih' => $totalHasilSemua - $targetAdjustedTotal,
                'capaian_global_persen' => $capaianGlobal,
                'potongan_total' => $potonganTotal,
                // rata-rata dari angka yang benar-benar dibebankan ke pekerja,
                // supaya header sama dengan jumlah di tabel
                'potongan_per_orang' => $jumlahPekerja > 0 ? (int) round($potonganDibagikan / $jumlahPekerja) : 0,
                'potongan_dibagikan' => $potonganDibagikan,
                'has_target' => $hasTarget,
            ];

            Log::info('ProduksiDataMap', [
                'mesin' => $namaMesin,
                'jumlah_ukuran' => count($itemsUkuran),
                'jumlah_pekerja' => $jumlahPekerja,
                'total_menit_orang' => $totalMenitOrang,
                'target_normal_total' => $targetNormalTotal,
                'target_disesuaikan_total' => $targetAdjustedTotal,
                'capaian_global_persen' => $capaianGlobal,
                'total_kendala_menit' => $totalKendalaMenit,
                'hasil' => $totalHasilSemua,
                'potongan_total' => $potonganTotal,
            ]);
        }

        return $result;
    }

    /**
     * Menit hadir real satu pekerja: (jam_pulang - jam_masuk), dibatasi jam kerja
     * normal hari itu, dikurangi irisan dengan downtime mesin.
     *
     * Kalau jam_masuk/jam_pulang kosong atau tidak valid, dianggap hadir penuh
     * (jam kerja normal dikurangi total downtime) seperti perilaku lama.
     */
    private static function hitungMenitHadir($det, Carbon $tglBase, array $merged, float $jamKerjaMenit, int $totalDowntimeMenit): float
    {
        $fallback = max(0, $jamKerjaMenit - $totalDowntimeMenit);

        if (empty($det->jam_masuk) || empty($det->jam_pulang)) {
            return (float) $fallback;
        }

        try {
            $in = $tglBase->copy()->setTimeFromTimeString((string) $det->jam_masuk);
            $out = $tglBase->copy()->setTimeFromTimeString((string) $det->jam_pulang);
        } catch (Exception $e) {
            return (float) $fallback;
        }

        if ($out->lessThanOrEqualTo($in)) {
            $out->addDay();
        }

        $menit = ($out->timestamp - $in->timestamp) / 60;

        if ($jamKerjaMenit > 0) {
            $menit = min($menit, $jamKerjaMenit);
        }

        foreach ($merged as $iv) {
            $overlap = min($iv['end'], $out->timestamp) - max($iv['start'], $in->timestamp);
            if ($overlap > 0) {
                $menit -= $overlap / 60;
            }
        }

        return (float) max(0, $menit);
    }

    /**
     * Table `ukurans` cuma punya kolom panjang/lebar/tebal (tidak ada
     * nama_ukuran), jadi label ditampilkan dari dimensinya langsung.
     */
    private static function formatUkuran($ukuranModel, $idUkuran): string
    {
        if (! $ukuranModel) {
            return 'Ukuran ID '.$idUkuran;
        }

        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

        return $fmt($ukuranModel->panjang).' x '.$fmt($ukuranModel->lebar).' x '.$fmt($ukuranModel->tebal);
    }
}
