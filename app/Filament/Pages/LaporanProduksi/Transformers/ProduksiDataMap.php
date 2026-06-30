<?php

namespace App\Filament\Pages\LaporanProduksi\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class ProduksiDataMap
{
    public static function make($collection)
    {
        $result = [];

        foreach ($collection as $item) {

            $namaMesin = $item->mesin->nama_mesin ?? 'TIDAK ADA MESIN';
            $tanggal = Carbon::parse($item->tgl_produksi)->format('d/m/Y');

            // DEFAULT VALUE – WAJIB ada sebelum if-else
            $ukuranDisplay = 'TIDAK ADA UKURAN';
            $totalHasil = 0;
            $targetHarian = 0;
            $jamKerja = 0;
            $potonganPerLembar = 0;
            $kodeUkuran = null;
            $targetModel = null;
            $ukuranId = null;

            // ---------------------------------------------------------
            // HITUNG KENDALA / DOWNTIME DARI MODEL BARU (kendalaRotaries)
            // ---------------------------------------------------------
            $totalKendalaMenit = 0;
            $totalDowntimeMenit = 0;
            $daftarKendala = [];
            $daftarDowntime = [];

            // 1. KENDALA: Dari Kendala Rotary Baru (kendalaRotaries)
            if (!empty($item->kendalaRotaries) && $item->kendalaRotaries->count() > 0) {
                $intervals = [];

                foreach ($item->kendalaRotaries as $knd) {
                    if ($knd->status === 'selesai' && !is_null($knd->durasi_menit)) {
                        $durasiMenit = (int)$knd->durasi_menit;

                        $mulai = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $selesai = $knd->waktu_selesai ? Carbon::parse($knd->waktu_selesai) : null;

                        if ($mulai && $selesai) {
                            $startTs = $mulai->timestamp;
                            $endTs = $selesai->timestamp;
                            if ($endTs > $startTs) {
                                $intervals[] = ['start' => $startTs, 'end' => $endTs];
                            }
                        }

                        $timeStr = ($mulai && $selesai) ? ': ' . $mulai->format('H:i') . '-' . $selesai->format('H:i') : '';
                        $formattedText = ($knd->kendala ?? 'Tidak disebutkan') . ' (' . $durasiMenit . ' menit' . $timeStr . ')';

                        $daftarKendala[] = [
                            'kendala' => $knd->kendala ?? 'Tidak disebutkan',
                            'keterangan' => '-',
                            'durasi_menit' => $durasiMenit,
                            'jam_mulai' => $mulai ? $mulai->format('H:i') : '-',
                            'jam_selesai' => $selesai ? $selesai->format('H:i') : '-',
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

                // Merge overlapping intervals to calculate totalDowntimeMenit
                if (!empty($intervals)) {
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

            // Format total downtime (sama dengan summarizer di table)
            $totalDowntimeFormatted = '';
            if ($totalDowntimeMenit >= 60) {
                $jam = floor($totalDowntimeMenit / 60);
                $menit = $totalDowntimeMenit % 60;
                $totalDowntimeFormatted = "{$jam} Jam {$menit} Menit";
            } else {
                $totalDowntimeFormatted = "{$totalDowntimeMenit} Menit";
            }

            // Format kendala untuk ditampilkan
            $kendalaText = '-';
            if (count($daftarKendala) > 0) {
                $kendalaText = implode(', ', array_column($daftarKendala, 'text'));
            }

            // ---------------------------------------------------------
            // 2. CEK DETAIL PALET
            // ---------------------------------------------------------
            if ($item->detailPaletRotary->isEmpty()) {

                $ukuranDisplay = 'BELUM INPUT PALET';

                Log::warning('Produksi tanpa detail palet', [
                    'id_produksi' => $item->id,
                    'mesin' => $namaMesin,
                    'tanggal' => $tanggal,
                ]);

            } else {
                $firstPalet = $item->detailPaletRotary->first();
                $ukuranId = $firstPalet?->id_ukuran;

                $totalHasil = $item->detailPaletRotary->sum('total_lembar') ?? 0;

                // Cari target
                $targetModel = Target::where('id_mesin', $item->id_mesin)
                    ->where('id_ukuran', $ukuranId)
                    ->first();

                if (!$targetModel) {
                    $targetModel = Target::where('id_mesin', $item->id_mesin)
                        ->whereNull('id_ukuran')
                        ->first();
                }

                $targetHarian = $targetModel?->target;
                $jamKerja = $targetModel?->jam;
                $potonganPerLembar = $targetModel?->potongan ?? 0;
                $kodeUkuran = $targetModel?->kode_ukuran;

                // If Friday, reduce target and jam kerja by 2 hours
                $prodDate = Carbon::parse($item->tgl_produksi);
                if ($prodDate->isFriday() && $targetHarian > 0 && $jamKerja > 2) {
                    $targetPerJamBase = $targetHarian / $jamKerja;
                    $targetHarian = $targetHarian - (2 * $targetPerJamBase);
                    $jamKerja = $jamKerja - 2;
                }

                // Format kode ukuran
                if ($kodeUkuran && trim($kodeUkuran) !== '') {
                    $ukuranDisplay = preg_replace('/^(SPINDLESS|YUEQUN|MERANTI|SANJI|DRYER\s*PAGI)/i', '', $kodeUkuran);
                    $ukuranDisplay = trim($ukuranDisplay) ?: $kodeUkuran;
                } else {
                    $ukuranDisplay = 'UKURAN BELUM DISET (id: ' . $ukuranId . ')';
                }
            }

            // ========================================
            // PERHITUNGAN TARGET & JAM KERJA DENGAN KENDALA
            // ========================================

            // Konversi jam kerja ke menit
            $jamKerjaMenit = $jamKerja * 60;

            // Jam kerja efektif (setelah dikurangi kendala)
            $jamKerjaEfektifMenit = $jamKerjaMenit - $totalKendalaMenit;
            $jamKerjaEfektif = $jamKerjaEfektifMenit / 60; // dalam jam

            // Target per jam (normal)
            $targetPerJam = $jamKerja > 0 ? round($targetHarian / $jamKerja, 2) : 0;

            // Target per menit (normal)
            $targetPerMenit = $jamKerjaMenit > 0 ? round($targetHarian / $jamKerjaMenit, 4) : 0;

            // Jam downtime dalam bentuk float
            $downtimeHours = $totalKendalaMenit / 60.0;
            $targetReduction = $downtimeHours * $targetPerJam;

            // Target disesuaikan: target harian dikurangi target selama downtime
            $targetDisesuaikan = (int) max(0, round($targetHarian - $targetReduction));

            // Selisih berdasarkan target yang telah disesuaikan dengan downtime
            $selisihProduksi = $totalHasil - $targetDisesuaikan;

            $jumlahPekerja = $item->detailPegawaiRotary->count();
            $potonganTotal = 0;
            $potonganPerOrang = 0;

            // Jika hasil produksi kurang dari target
            if ($selisihProduksi < 0 && $potonganPerLembar > 0) {
                $potonganTotal = abs($selisihProduksi) * $potonganPerLembar;

                if ($jumlahPekerja > 0) {
                    $potonganPerOrangRaw = $potonganTotal / $jumlahPekerja;

                    // Logika Pembulatan Bertingkat: 0-299 -> 0 | 300-799 -> 500 | 800+ -> 1000
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
            // -------------------------------------------------------

            $pekerja = $item->detailPegawaiRotary->map(function ($det) use ($potonganPerOrang) {
                return [
                    'id' => $det->pegawai->kode_pegawai ?? '-',
                    'nama' => $det->pegawai->nama_pegawai ?? '-',
                    'jam_masuk' => $det->jam_masuk ?? '-',
                    'jam_pulang' => $det->jam_pulang ?? '-',
                    'ijin' => $det->ijin ?? '-',
                    'keterangan' => $det->keterangan ?? '-',
                    // Tampilkan hasil yang sudah dibulatkan
                    'pot_target' => $potonganPerOrang,
                ];
            })->toArray();

            $result[] = [
                'mesin' => $namaMesin,
                'tanggal' => $tanggal,
                'ukuran' => $ukuranDisplay,
                'pekerja' => $pekerja,
                'kendala' => $kendalaText,
                'daftar_kendala' => $daftarKendala,
                'daftar_downtime' => $daftarDowntime,
                'jam_kerja' => $jamKerja,
                'jam_kerja_efektif' => round($jamKerjaEfektif, 2),
                'total_kendala_menit' => $totalKendalaMenit,
                'total_downtime_menit' => $totalDowntimeMenit,
                'total_downtime_formatted' => $totalDowntimeFormatted, // INI YANG KURANG!
                'target' => $targetDisesuaikan, // Target yang sudah disesuaikan
                'target_normal' => $targetHarian, // Target normal tanpa penyesuaian
                'target_per_jam' => $targetPerJam,
                'target_per_menit' => $targetPerMenit,
                'hasil' => $totalHasil,
                'selisih' => $selisihProduksi,
                'potongan_total' => $potonganTotal,
                'potongan_per_orang' => $potonganPerOrang, // Sudah integer hasil custom round
                'has_target' => $targetModel !== null,
                'kode_ukuran_raw' => $kodeUkuran,
                'ukuran_id' => $ukuranId,
            ];

            Log::info('ProduksiDataMap', [
                'mesin' => $namaMesin,
                'ukuran_id' => $ukuranId,
                'kode_ukuran' => $ukuranDisplay,
                'target_normal' => $targetHarian,
                'target_disesuaikan' => $targetDisesuaikan,
                'total_kendala_menit' => $totalKendalaMenit,
                'total_downtime_formatted' => $totalDowntimeFormatted,
                'jumlah_kendala' => count($daftarKendala),
                'jam_kerja_efektif' => $jamKerjaEfektif,
                'hasil' => $totalHasil,
                'selisih' => $selisihProduksi,
                'potongan_per_orang' => $potonganPerOrang,
            ]);
        }

        return $result;
    }
}