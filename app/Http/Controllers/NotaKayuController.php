<?php

namespace App\Http\Controllers;

use App\Models\HargaKayu;
use App\Models\NotaKayu;

class NotaKayuController extends Controller
{
    public function show(NotaKayu $record)
    {
        $record->load([
            'kayuMasuk.detailTurusanKayus',
            'kayuMasuk.penggunaanSupplier',
        ]);

        $details = $record->kayuMasuk->detailTurusanKayus ?? collect();

        $jenisKayuId = optional($details->first())->jenis_kayu_id
            ?? optional(optional($details->first())->jenisKayu)->id
            ?? 1;

        $grade = optional($details->first())->grade ?? 1;
        $panjang = optional($details->first())->panjang ?? 130;

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

        // ✅ Kubikasi dipaksa 4 digit
        $totalKubikasi = $details->sum(function ($item) {
            return round($item->kubikasi, 4);
        });

        // =========================
        // HITUNG GRAND TOTAL
        // =========================

        $grandTotal = 0;

        foreach ($details as $item) {

            $idJenisKayu = $item->id_jenis_kayu
                ?? optional($item->jenisKayu)->id;

            $harga = HargaKayu::where('id_jenis_kayu', $idJenisKayu)
                ->where('grade', $item->grade)
                ->where('panjang', $item->panjang)
                ->where('diameter_terkecil', '<=', $item->diameter)
                ->where('diameter_terbesar', '>=', $item->diameter)
                ->orderBy('diameter_terkecil', 'desc')
                ->value('harga_beli');

            $kubikasi = round($item->kubikasi, 4);

            // ✅ Poin per record -> rupiah (dibulatkan)
            $grandTotal += round(($harga ?? 0) * $kubikasi * 1000);
        }

        // ✅ PAKSA INTEGER TOTAL
        $grandTotal = (int) round($grandTotal);

        // =========================
        // BIAYA TURUN KAYU
        // =========================

        $pembulatanManual = (int) ($record->adjustment ?? 0);

        $biayaTurunPerM3 = 5000;

        $hasilDasar = round($totalKubikasi * $biayaTurunPerM3);
        $biayaFloor = floor($hasilDasar / 1000) * 1000;

        // ✅ % harus integer
        $sisaRibuan = $grandTotal % 1000;

        $biayaTurunKayu = (int) ($biayaFloor + $sisaRibuan + 10000);

        // =========================
        // HARGA AKHIR
        // =========================

        $hargaBeliAkhir = (int) round($grandTotal - $biayaTurunKayu);

        // ✅ Bulatkan ke 5000
        $mod = $hargaBeliAkhir % 5000;

        $hargaBeliAkhirBulat = $mod >= 2500
            ? $hargaBeliAkhir + (5000 - $mod)
            : $hargaBeliAkhir - $mod;

        // ✅ Tambah pembulatan manual
        $totalAkhir = (int) ($hargaBeliAkhirBulat + $pembulatanManual);

        // ✅ Final tetap kelipatan 5000
        $mod = $totalAkhir % 5000;

        $totalAkhir = $mod >= 2500
            ? $totalAkhir + (5000 - $mod)
            : $totalAkhir - $mod;

        // =========================
        // SELISIH
        // =========================

        $selisih = (int) ($grandTotal - $totalAkhir);

        return view('nota-kayu.print', [
            'record' => $record,
            'totalBatang' => $totalBatang,
            'totalKubikasi' => round($totalKubikasi, 4),
            'grandTotal' => $grandTotal,
            'biayaTurunKayu' => $biayaTurunKayu,
            'pembulatanManual' => $pembulatanManual,
            'totalAkhir' => $hargaBeliAkhir,
            'hargaFinal' => $totalAkhir,
            'selisih' => $selisih,
            'groupedByDiameter' => $groupedByDiameter,
        ]);
    }

    // ==================================================
    // GROUP RENTANG DIAMETER (M³ 4 digit konsisten)
    // ==================================================
    public function groupByRentangDiameter($details, $idJenisKayu, $grade, $panjang)
    {
        $rentangList = HargaKayu::where('id_jenis_kayu', $idJenisKayu)
            ->where('grade', $grade)
            ->where('panjang', $panjang)
            ->orderBy('diameter_terkecil')
            ->get();

        $hasil = collect();
        $terpakaiIds = collect();

        foreach ($rentangList as $rentang) {

            $kelompok = $details->filter(function ($item) use ($rentang) {
                return $item->diameter >= $rentang->diameter_terkecil
                    && $item->diameter <= $rentang->diameter_terbesar;
            });

            if ($kelompok->isNotEmpty()) {

                $totalBatang = $kelompok->sum('kuantitas');

                // ✅ Kubikasi paksa 4 digit
                $totalKubikasi = $kelompok->sum(function ($item) {
                    return round($item->kubikasi, 4);
                });

                $harga = $rentang->harga_beli;

                // ✅ Harga rupiah bulat
                $totalHarga = round($harga * $totalKubikasi * 1000);

                $hasil->push([
                    'rentang' => "{$rentang->diameter_terkecil} - {$rentang->diameter_terbesar}",
                    'batang' => $totalBatang,
                    'kubikasi' => round($totalKubikasi, 4),
                    'harga_satuan' => $harga,
                    'total_harga' => $totalHarga,
                ]);

                $terpakaiIds = $terpakaiIds->merge(
                    $kelompok->pluck('id')
                );
            }
        }

        // Data yang tidak punya harga
        $sisa = $details->whereNotIn('id', $terpakaiIds);

        foreach ($sisa as $item) {
            $hasil->push([
                'rentang' => "{$item->diameter} - {$item->diameter}",
                'batang' => $item->kuantitas,
                'kubikasi' => round($item->kubikasi, 4),
                'harga_satuan' => 0,
                'total_harga' => 0,
            ]);
        }

        return $hasil->sortBy(function ($i) {
            return (float) explode(' ', $i['rentang'])[0];
        })->values();
    }
}
