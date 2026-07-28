<?php

namespace App\Filament\Pages\LaporanRepairs\Transformers;

use App\Models\Target;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RepairDataMap
{
    public static function make($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal)->format('d/m/Y');
            $kendalaKerjaHariIni = $produksi->kendala ?? '—';

            // Iterasi langsung ke DetailHasilRepair
            foreach ($produksi->detailHasilRepairs as $detail) {

                $jumlahHasil = (int) $detail->jumlah;

                // Abaikan jika tidak ada hasil produksi
                if ($jumlahHasil <= 0) {
                    continue;
                }

                // Ambil spesifikasi ukuran & jenis kayu (Modal / Manual)
                $ukuranModel = $detail->ukuran;
                $jenisKayuModel = $detail->modalRepair?->jenisKayu ?? $detail->jenisKayu;
                $kw = $detail->kw ?? 1;

                // =============================
                // BUILD KODE UKURAN UNTUK TARGET
                // =============================
                if ($ukuranModel && $jenisKayuModel) {
                    $kodeUkuran = 'REPAIR' .
                        $ukuranModel->panjang .
                        $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) .
                        $kw .
                        strtolower($jenisKayuModel->kode_kayu ?? '');
                } else {
                    $kodeUkuran = 'REPAIR-NOT-FOUND';
                }

                // =============================
                // AMBIL DATA TARGET
                // =============================
                $targetLv1 = Target::where('kode_ukuran', $kodeUkuran)
                    ->where('id_mesin', $produksi->id_mesin)
                    ->first();

                $targetLv2 = Target::where('kode_ukuran', $kodeUkuran)->first();

                $targetLv3 = Target::where([
                    'id_mesin' => $produksi->id_mesin,
                    'id_ukuran' => $detail->id_ukuran,
                ])->first();

                $targetModel = $targetLv1 ?? $targetLv2 ?? $targetLv3;

                $targetHarian = (int) ($targetModel->target ?? 0);
                $jamProduksi = (int) ($targetModel->jam ?? 0);
                $potonganPerLembar = (int) ($targetModel->potongan ?? 0);
                $jumlahOrangTarget = (int) ($targetModel->orang ?? 1);

                $nomorMeja = $detail->nomor_meja ?? '-';
                $key = $detail->id; // Gunakan ID DetailHasilRepair sebagai kunci unik

                // Susun struktur baris laporan
                $result[$key] = [
                    'id_detail' => $detail->id,
                    'nomor_meja' => $nomorMeja,
                    'kode_ukuran' => $kodeUkuran,
                    'ukuran' => $ukuranModel->dimensi ?? '-',
                    'jenis_kayu' => $jenisKayuModel->nama_kayu ?? '-',
                    'kw' => $kw,
                    'pekerja' => [],
                    'hasil' => $jumlahHasil,
                    'target' => $targetHarian,
                    'jam_kerja' => $jamProduksi,
                    'jumlah_orang_target' => $jumlahOrangTarget,
                    'selisih' => 0,
                    'tanggal' => $tanggal,
                    'potongan_per_lembar' => $potonganPerLembar,
                    'keterangan_hasil' => $detail->keterangan ?: '—',
                    'keterangan_kerja' => $kendalaKerjaHariIni,
                ];

                // Map daftar pekerja yang terikat di DetailHasilRepair ini
                $pekerjaList = $detail->rencanaPegawais;
                $jumlahPekerja = $pekerjaList->count();

                foreach ($pekerjaList as $rp) {
                    if (! $rp->pegawai) continue;

                    // Bagikan hasil rata ke pekerja untuk keperluan kalkulasi/display
                    $hasilIndividu = $jumlahPekerja > 0 ? floor($jumlahHasil / $jumlahPekerja) : 0;

                    $result[$key]['pekerja'][] = [
                        'id' => $rp->pegawai->kode_pegawai ?? '-',
                        'nama' => $rp->pegawai->nama_pegawai ?? '-',
                        'jam_masuk' => $rp->jam_masuk ? Carbon::parse($rp->jam_masuk)->format('H:i') : '-',
                        'jam_pulang' => $rp->jam_pulang ? Carbon::parse($rp->jam_pulang)->format('H:i') : '-',
                        'ijin' => $rp->ijin ?? '-',
                        'keterangan' => $rp->keterangan ?? '-',
                        'keterangan_hasil' => $detail->keterangan ?: '—',
                        'keterangan_kerja' => $kendalaKerjaHariIni,
                        'nomor_meja' => $nomorMeja,
                        'hasil' => $hasilIndividu,
                        'pot_target' => 0,
                    ];
                }
            }
        }

        // =============================
        // HITUNG SELISIH & POTONGAN PER ORANG
        // =============================
        foreach ($result as &$row) {
            $row['selisih'] = $row['hasil'] - $row['target'];

            if ($row['selisih'] < 0 && $row['potongan_per_lembar'] > 0) {
                $totalDendaMeja = abs($row['selisih']) * $row['potongan_per_lembar'];
                $jumlahPekerja = count($row['pekerja']);

                if ($jumlahPekerja > 0) {
                    $rawPotongan = $totalDendaMeja / $jumlahPekerja;
                    $potonganFinal = self::roundToNearest500($rawPotongan);

                    foreach ($row['pekerja'] as &$p) {
                        $p['pot_target'] = $potonganFinal;
                    }
                }
            }
        }

        return array_values($result);
    }

    private static function roundToNearest500(float $value): int
    {
        $base = floor($value / 1000) * 1000;
        $rest = $value - $base;

        if ($rest < 300) return (int) $base;
        if ($rest < 800) return (int) ($base + 500);

        return (int) ($base + 1000);
    }
}
