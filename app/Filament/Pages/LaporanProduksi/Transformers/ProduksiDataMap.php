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
                        // Include pending/in-progress kendalas in the text list so they are visible
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

                // Merge overlapping intervals to calculate totalDowntimeMenit
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

                if (! $targetModel) {
                    $targetModel = Target::where('id_mesin', $item->id_mesin)
                        ->whereNull('id_ukuran')
                        ->first();
                }

                $targetHarian = $targetModel?->target;
                $jamKerja = $targetModel?->jam;
                $potonganPerLembar = $targetModel?->potongan ?? 0;
                $kodeUkuran = $targetModel?->kode_ukuran;

                // Format kode ukuran
                if ($kodeUkuran && trim($kodeUkuran) !== '') {
                    $ukuranDisplay = preg_replace('/^(SPINDLESS|YUEQUN|MERANTI|SANJI|DRYER\s*PAGI)/i', '', $kodeUkuran);
                    $ukuranDisplay = trim($ukuranDisplay) ?: $kodeUkuran;
                } else {
                    $ukuranDisplay = 'UKURAN BELUM DISET (id: '.$ukuranId.')';
                }
            }

            // ========================================
            // JAM KERJA EFEKTIF (Jumat -2 jam + downtime)
            // ========================================
            // Catatan: pengurangan Jumat & downtime SEKARANG hanya memengaruhi
            // menit kerja aktual yang dikirim ke HitungPotonganProduksiAction,
            // BUKAN target/jam master. TargetHitungResult (via KolektifStrategy)
            // yang menghitung targetAdjusted secara proporsional dari total
            // menit kerja aktual seluruh pekerja, termasuk otomatis menyesuaikan
            // skala target kalau jumlah pekerja aktual beda dari `orang` master.
            $jamKerjaNormalHariIni = (float) $jamKerja;
            $prodDate = Carbon::parse($item->tgl_produksi);
            if ($prodDate->isFriday() && $targetHarian > 0 && $jamKerjaNormalHariIni > 2) {
                $jamKerjaNormalHariIni -= 2;
            }

            $jamKerjaMenit = $jamKerjaNormalHariIni * 60;
            $jamKerjaEfektifMenit = max(0, $jamKerjaMenit - $totalKendalaMenit);
            $jamKerjaEfektif = $jamKerjaEfektifMenit / 60;

            // Target per jam (informasi tampilan saja, tidak dipakai untuk hitung potongan)
            $targetPerJam = $jamKerja > 0 ? round($targetHarian / $jamKerja, 2) : 0;
            $targetPerMenit = $jamKerjaMenit > 0 ? round($targetHarian / $jamKerjaMenit, 4) : 0;

            $jumlahPekerja = $item->detailPegawaiRotary->count();

            // ========================================
            // HITUNG POTONGAN via HitungPotonganProduksiAction
            // ========================================
            $targetDisesuaikan = 0;
            $selisihProduksi = $totalHasil;
            $potonganTotal = 0;
            $potonganPerPegawai = [];

            if ($targetModel && $jumlahPekerja > 0) {
                $pekerjaInput = $item->detailPegawaiRotary->map(function ($det) use ($jamKerjaEfektifMenit) {
                    $idPegawai = $det->pegawai->kode_pegawai ?? (string) ($det->id_pegawai ?? $det->id);

                    return new PekerjaKerjaInput(
                        idPegawai: (string) $idPegawai,
                        menitKerja: $jamKerjaEfektifMenit,
                        hasilIndividu: 0,
                    );
                })->values()->all();

                $mesinEnum = Mesin::tryFrom((int) $item->id_mesin);

                if ($mesinEnum) {
                    $hitung = $action->execute(
                        mesin: $mesinEnum,
                        strategi: $mesinEnum->strategiPembagian(),
                        pekerja: $pekerjaInput,
                        hasilAktual: (float) $totalHasil,
                        idUkuran: $ukuranId,
                        idJenisKayu: null,
                        grade: null,
                        customTarget: $targetModel,
                    );

                    if ($hitung) {
                        $targetDisesuaikan = (int) round($hitung->targetAdjusted);
                        $selisihProduksi = $totalHasil - $hitung->targetAdjusted;
                        $potonganTotal = $hitung->potongan;
                        $potonganPerPegawai = $hitung->potonganPerPegawai;
                    } else {
                        Log::warning('HitungPotonganProduksiAction gagal resolve target (fallback ke 0)', [
                            'id_produksi' => $item->id,
                            'mesin' => $namaMesin,
                            'id_mesin' => $item->id_mesin,
                            'ukuran_id' => $ukuranId,
                        ]);
                    }
                } else {
                    Log::warning('Mesin enum tidak ditemukan untuk id_mesin, potongan tidak dihitung', [
                        'id_produksi' => $item->id,
                        'id_mesin' => $item->id_mesin,
                    ]);
                }
            }
            // -------------------------------------------------------

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
                'total_downtime_formatted' => $totalDowntimeFormatted,
                'target' => $targetDisesuaikan, // Target yang sudah disesuaikan (dari service)
                'target_normal' => $targetHarian, // Target normal tanpa penyesuaian
                'target_per_jam' => $targetPerJam,
                'target_per_menit' => $targetPerMenit,
                'hasil' => $totalHasil,
                'selisih' => $selisihProduksi,
                'potongan_total' => $potonganTotal,
                'potongan_per_orang' => $jumlahPekerja > 0 ? (int) round($potonganTotal / $jumlahPekerja) : 0,
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
                'potongan_total' => $potonganTotal,
            ]);
        }

        return $result;
    }
}
