<?php

namespace App\Filament\Pages\LaporanProduksi\Transformers;

use App\Actions\HitungPotonganProduksiAction;
use App\DataTransferObjects\PekerjaKerjaInput;
use App\Enums\Mesin;
use App\Models\Target;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProduksiDataMap
{
    public static function make($collection)
    {
        $result = [];
        $action = new HitungPotonganProduksiAction;

        foreach ($collection as $item) {

            $namaMesin = $item->mesin->nama_mesin ?? 'TIDAK ADA MESIN';
            $tanggal = Carbon::parse($item->tgl_produksi)->format('d/m/Y');

            // ---------------------------------------------------------
            // KENDALA / DOWNTIME (tidak berubah dari sebelumnya)
            // ---------------------------------------------------------
            $totalKendalaMenit = 0;
            $totalDowntimeMenit = 0;
            $daftarKendala = [];
            $daftarDowntime = [];

            if (! empty($item->kendalaRotaries) && $item->kendalaRotaries->count() > 0) {
                $intervals = [];

                foreach ($item->kendalaRotaries as $knd) {
                    if ($knd->status === 'selesai' && ! is_null($knd->durasi_menit)) {
                        $durasiMenit = (int) $knd->durasi_menit;

                        $mulai = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $selesai = $knd->waktu_selesai ? Carbon::parse($knd->waktu_selesai) : null;

                        if ($mulai && $selesai) {
                            $startTs = $mulai->timestamp;
                            $endTs = $selesai->timestamp;
                            if ($endTs > $startTs) {
                                $intervals[] = ['start' => $startTs, 'end' => $endTs];
                            }
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
                    usort($intervals, function ($a, $b) {
                        return $a['start'] <=> $b['start'];
                    });

                    $merged = [];
                    foreach ($intervals as $interval) {
                        if (empty($merged)) {
                            $merged[] = $interval;
                        } else {
                            $lastIndex = count($merged) - 1;
                            $last = &$merged[$lastIndex];
                            if ($interval['start'] <= $last['end']) {
                                $last['end'] = max($last['end'], $interval['end']);
                            } else {
                                $merged[] = $interval;
                            }
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

            // ---------------------------------------------------------
            // JAM KERJA EFEKTIF (Jumat -2 jam + downtime) — dipakai SEKALI
            // untuk seluruh kru hari itu, terlepas dari berapa banyak
            // ukuran yang mereka kerjakan (sama seperti pola di JoinDataMap:
            // 1 kru cuma punya 1 "jatah hari kerja").
            // ---------------------------------------------------------
            $jumlahPekerja = $item->detailPegawaiRotary->count();

            // Target "jam" master dipakai untuk basis pengurangan Jumat.
            // Karena bisa ada >1 ukuran (>1 baris target) per produksi,
            // kita ambil jam master dari target ukuran PERTAMA yang
            // ketemu sebagai representasi jam kerja normal hari itu
            // (semua target mesin yang sama biasanya punya jam sama).
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
            $jamKerjaEfektifMenit = max(0, $jamKerjaMenit - $totalKendalaMenit);
            $jamKerjaEfektif = $jamKerjaEfektifMenit / 60;

            // ---------------------------------------------------------
            // KELOMPOKKAN detailPaletRotary PER UKURAN
            // ---------------------------------------------------------
            // PENTING (bug lama): sebelumnya cuma palet PERTAMA yang
            // dipakai untuk cari target & label ukuran, sementara
            // total_lembar dijumlah dari SEMUA palet termasuk ukuran lain
            // → ukuran lain hilang dari laporan & capaian jadi salah.
            //
            // Untuk mesin rotary (Spindless/Meranti/Sanji/Yuequn dll):
            // KW/grade TIDAK diisi di tabel targets (kolom grade selalu
            // NULL), jadi target cuma di-resolve pakai (id_mesin,
            // id_ukuran) — KW diabaikan untuk pencarian target, tapi tetap
            // dicatat untuk tampilan.
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
            // HITUNG TARGET-ADJUSTED, CAPAIAN & POTONGAN — KOLEKTIF LINTAS
            // UKURAN (pola sama seperti JoinDataMap): tiap ukuran dihitung
            // seolah kru kerja PENUH sehari cuma untuk ukuran itu (pakai
            // jam efektif & jumlah pekerja yang SAMA, bukan diulang penuh
            // per ukuran), lalu capaian % dijumlah lintas ukuran, dan
            // potongan final dihitung SEKALI dari kekurangan % itu — bukan
            // dijumlah dari potongan tiap ukuran (supaya tidak dobel
            // hitung kalau kru menghasilkan >1 ukuran di hari yang sama).
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

                $targetAdjusted = round($ratePerOrgPerMenit * $jumlahPekerja * $jamKerjaEfektifMenit);

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

            $potonganTotal = 0;
            $potonganPerPegawai = [];

            if ($hasTarget && $jumlahPekerja > 0) {
                if ($totalHasilSemua <= 0) {
                    // Sama sekali tidak ada hasil: tiap orang kena potongan
                    // penuh sebesar gaji (konsisten dengan KolektifStrategy).
                    $potonganPerOrang = $gajiPerOrang;
                    $potonganTotal = $gajiPerOrang * $jumlahPekerja;
                } else {
                    $kekuranganPersen = max(0, 100 - $capaianGlobal) / 100;
                    $potonganTotal = $kekuranganPersen * $nilaiSatuHariPenuh;
                    $potonganPerOrang = $jumlahPekerja > 0
                        ? round(($potonganTotal / $jumlahPekerja) / 500) * 500
                        : 0;
                }

                foreach ($item->detailPegawaiRotary as $det) {
                    $idPegawai = $det->pegawai->kode_pegawai ?? (string) ($det->id_pegawai ?? $det->id);
                    $potonganPerPegawai[(string) $idPegawai] = $potonganPerOrang;
                }
            } else {
                Log::warning('Tidak ada target ditemukan untuk semua ukuran, potongan tidak dihitung', [
                    'id_produksi' => $item->id,
                    'mesin' => $namaMesin,
                    'id_mesin' => $item->id_mesin,
                ]);
            }

            $pekerja = $item->detailPegawaiRotary->map(function ($det) use ($potonganPerPegawai) {
                $idPegawai = $det->pegawai->kode_pegawai ?? (string) ($det->id_pegawai ?? $det->id);

                return [
                    'id' => $det->pegawai->kode_pegawai ?? '-',
                    'nama' => $det->pegawai->nama_pegawai ?? '-',
                    'jam_masuk' => $det->jam_masuk ?? '-',
                    'jam_pulang' => $det->jam_pulang ?? '-',
                    'ijin' => $det->ijin ?? '-',
                    'keterangan' => $det->keterangan ?? '-',
                    'pot_target' => (int) ($potonganPerPegawai[(string) $idPegawai] ?? 0),
                ];
            })->toArray();

            // Label ukuran ringkas di header kartu: gabungan semua ukuran
            // hari itu (kalau cuma 1 ukuran, hasilnya sama seperti dulu).
            $ukuranDisplayHeader = count($itemsUkuran) > 0
                ? implode(' + ', array_column($itemsUkuran, 'ukuran'))
                : ($item->detailPaletRotary->isEmpty() ? 'BELUM INPUT PALET' : 'TIDAK ADA UKURAN');

            $result[] = [
                'mesin' => $namaMesin,
                'tanggal' => $tanggal,
                'ukuran' => $ukuranDisplayHeader,
                'items' => $itemsUkuran, // breakdown per ukuran
                'pekerja' => $pekerja,
                'kendala' => $kendalaText,
                'daftar_kendala' => $daftarKendala,
                'daftar_downtime' => $daftarDowntime,
                'jam_kerja' => $jamKerja,
                'jam_kerja_efektif' => round($jamKerjaEfektif, 2),
                'total_kendala_menit' => $totalKendalaMenit,
                'total_downtime_menit' => $totalDowntimeMenit,
                'total_downtime_formatted' => $totalDowntimeFormatted,
                'target' => $targetAdjustedTotal, // jumlah target disesuaikan lintas semua ukuran
                'target_normal' => $targetNormalTotal, // jumlah target normal lintas semua ukuran
                'target_per_jam' => $jamKerja > 0 ? round($targetNormalTotal / $jamKerja, 2) : 0,
                'hasil' => $totalHasilSemua,
                'selisih' => $totalHasilSemua - $targetAdjustedTotal,
                'capaian_global_persen' => $capaianGlobal,
                'potongan_total' => $potonganTotal,
                'potongan_per_orang' => $jumlahPekerja > 0 ? (int) round($potonganTotal / $jumlahPekerja) : 0,
                'has_target' => $hasTarget,
            ];

            Log::info('ProduksiDataMap', [
                'mesin' => $namaMesin,
                'jumlah_ukuran' => count($itemsUkuran),
                'target_normal_total' => $targetNormalTotal,
                'target_disesuaikan_total' => $targetAdjustedTotal,
                'capaian_global_persen' => $capaianGlobal,
                'total_kendala_menit' => $totalKendalaMenit,
                'jam_kerja_efektif' => $jamKerjaEfektif,
                'hasil' => $totalHasilSemua,
                'potongan_total' => $potonganTotal,
            ]);
        }

        return $result;
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