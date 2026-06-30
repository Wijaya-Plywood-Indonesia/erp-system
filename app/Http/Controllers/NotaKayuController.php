<?php

namespace App\Http\Controllers;

use App\Models\HargaKayu;
use App\Models\NotaKayu;
use Illuminate\Support\Collection;

class NotaKayuController extends Controller
{
    public function show(NotaKayu $record)
    {
        // Eager load detail turusan untuk mendapatkan snapshot harga per batang
        $record->load([
            'kayuMasuk.detailTurusanKayus.jenisKayu',
            'kayuMasuk.penggunaanSupplier',
        ]);

        $details = $record->kayuMasuk->detailTurusanKayus ?? collect();

        if ($details->isEmpty()) {
            return "Nota ini tidak memiliki detail kayu.";
        }

        // Ambil info dasar dari item pertama untuk filter rentang master (layouting nota)
        $firstItem   = $details->first();
        $jenisKayuId = $firstItem?->jenis_kayu_id ?? 1;
        $grade       = $firstItem?->grade ?? 1;
        $panjang     = $firstItem?->panjang ?? 130;

        /**
         * LOGIKA GROUPING NOTA
         * Rentang diameter dari master HargaKayu hanya digunakan sebagai template baris (layout).
         * Nilai harganya tetap akan mengambil dari snapshot per batang.
         */
        $groupedByDiameter = $this->groupByRentangDiameter(
            $details,
            $jenisKayuId,
            $grade,
            $panjang
        );

        // =========================
        // TOTAL BATANG & KUBIKASI
        // =========================
        $totalBatang = $details->sum('kuantitas');

        // ✅ Kubikasi dipaksa 4 digit desimal untuk presisi akuntansi
        $totalKubikasi = $details->sum(function ($item) {
            return round($item->kubikasi, 4);
        });

        // =========================
        // HITUNG GRAND TOTAL (RUPIAH)
        // =========================
        $grandTotal = 0;

        foreach ($details as $item) {
            /**
             * [PENERTIBAN DATA]
             * Sekarang sistem HANYA membaca dari kolom 'harga' di tabel detail_turusan_kayus.
             * Jika di tabel database harganya 0, maka di nota akan muncul 0.
             * Ini akan memaksa petugas untuk memastikan master harga sudah benar sebelum input.
             */
            $harga = $item->harga ?? 0;

            $kubikasi = round($item->kubikasi, 4);

            // Hitung nilai rupiah (Harga Poin x Kubikasi x 1000)
            $grandTotal += round($harga * $kubikasi * 1000);
        }

        // Pastikan total akhir berupa integer murni
        $grandTotal = (int) round($grandTotal);

        // =========================
        // BIAYA TURUN KAYU & PEMBULATAN
        // =========================
        $pembulatanManual = (int) ($record->adjustment ?? 0);
        $biayaTurunPerM3  = 5000;

        $hasilDasar = round($totalKubikasi * $biayaTurunPerM3);
        $biayaFloor = floor($hasilDasar / 1000) * 1000;

        // Biaya turun dipengaruhi oleh sisa ribuan dari grand total (standard operasional)
        $sisaRibuan = $grandTotal % 1000;
        $biayaTurunKayu = (int) ($biayaFloor + $sisaRibuan + 10000);

        // =========================
        // HARGA AKHIR (NETTO)
        // =========================
        $hargaBeliAkhir = (int) ($grandTotal - $biayaTurunKayu);

        // Tahap 1: Bulatkan ke kelipatan 5.000 terdekat
        $mod = $hargaBeliAkhir % 5000;
        $hargaBeliAkhirBulat = $mod >= 2500
            ? $hargaBeliAkhir + (5000 - $mod)
            : $hargaBeliAkhir - $mod;

        // Tahap 2: Tambahkan penyesuaian manual (Adjustment)
        $totalAkhir = (int) ($hargaBeliAkhirBulat + $pembulatanManual);

        // Tahap 3: Final pembulatan tetap harus kelipatan 5.000
        $modFinal = $totalAkhir % 5000;
        $totalAkhir = $modFinal >= 2500
            ? $totalAkhir + (5000 - $modFinal)
            : $totalAkhir - $modFinal;

        $selisih = (int) ($grandTotal - $totalAkhir);

        return view('nota-kayu.print', [
            'record'            => $record,
            'totalBatang'       => $totalBatang,
            'totalKubikasi'     => round($totalKubikasi, 4),
            'grandTotal'        => $grandTotal,
            'biayaTurunKayu'    => $biayaTurunKayu,
            'pembulatanManual'  => $pembulatanManual,
            'totalAkhir'        => $hargaBeliAkhir,
            'hargaFinal'        => $totalAkhir,
            'selisih'           => $selisih,
            'groupedByDiameter' => $groupedByDiameter,
        ]);
    }

    /**
     * Grouping Rentang Diameter untuk Tampilan Nota.
     */
    public function groupByRentangDiameter($details, $idJenisKayu, $grade, $panjang)
    {
        $rentangList = HargaKayu::where('id_jenis_kayu', $idJenisKayu)
            ->where('grade', $grade)
            ->where('panjang', $panjang)
            ->orderBy('diameter_terkecil')
            ->get();

        $hasil       = collect();
        $terpakaiIds = collect();

        foreach ($rentangList as $rentang) {
            $kelompok = $details->filter(function ($item) use ($rentang) {
                return $item->diameter >= $rentang->diameter_terkecil
                    && $item->diameter <= $rentang->diameter_terbesar;
            });

            if ($kelompok->isNotEmpty()) {
                $totalBatang   = $kelompok->sum('kuantitas');
                $totalKubikasi = $kelompok->sum(fn($item) => round($item->kubikasi, 4));

                /**
                 * PENERTIBAN: Ambil harga snapshot dari baris turusan.
                 * Jika di database 0, maka harga kelompok ini akan 0.
                 */
                $harga = $kelompok->first()->harga ?? 0;

                $totalHarga = round($harga * $totalKubikasi * 1000);

                $hasil->push([
                    'rentang'      => "{$rentang->diameter_terkecil} - {$rentang->diameter_terbesar}",
                    'batang'       => $totalBatang,
                    'kubikasi'     => round($totalKubikasi, 4),
                    'harga_satuan' => $harga,
                    'total_harga'  => $totalHarga,
                ]);

                $terpakaiIds = $terpakaiIds->merge($kelompok->pluck('id'));
            }
        }

        // Penanganan Item Sisa (Di luar rentang master)
        $sisa = $details->whereNotIn('id', $terpakaiIds);
        foreach ($sisa as $item) {
            $hasil->push([
                'rentang'      => "{$item->diameter} (Manual)",
                'batang'       => $item->kuantitas,
                'kubikasi'     => round($item->kubikasi, 4),
                'harga_satuan' => $item->harga ?? 0,
                'total_harga'  => round(($item->harga ?? 0) * round($item->kubikasi, 4) * 1000),
            ]);
        }

        return $hasil->sortBy(function ($i) {
            return (float) explode(' ', $i['rentang'])[0];
        })->values();
    }
}
