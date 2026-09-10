<?php

namespace App\Http\Controllers;

use App\Models\HargaKayu;
use App\Models\NotaKayu;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class NotaKayuTurusController extends Controller
{
    public function show(NotaKayu $record)
    {
        $record->load([
            'kayuMasuk.detailTurusanKayus.jenisKayu',
            'kayuMasuk.penggunaanSupplier',
            'kayuMasuk.penggunaanKendaraanSupplier',
            'kayuMasuk.penggunaanDokumenKayu',
        ]);

        $details = $record->kayuMasuk->detailTurusanKayus ?? collect();

        $firstItem = $details->first();
        $jenisKayuId = $firstItem->jenis_kayu_id 
            ?? optional($firstItem->jenisKayu)->id 
            ?? 1;
        $grade = $firstItem->grade ?? 1;
        $panjang = $firstItem->panjang ?? 130;

        $groupedDetails = $details->groupBy(function($item) {
            $kodeLahan = optional($item->lahan)->kode_lahan ?? '-';
            $grade = $item->grade ?? 0;
            $panjang = $item->panjang ?? '-';
            $jenis = optional($item->jenisKayu)->nama_kayu ?? '-';
            return "{$kodeLahan}|{$grade}|{$panjang}|{$jenis}";
        });

        $totalBatangGlobal = $details->sum('kuantitas');
        
        $totalKubikasiGlobal = $details->sum(function ($item) {
            return round($item->kubikasi, 4);
        });

        $grandTotalRupiah = 0;
        foreach ($details as $item) {
            $idJenis = $item->id_jenis_kayu ?? optional($item->jenisKayu)->id ?? $jenisKayuId;
            $harga = $this->getHargaSatuan($idJenis, $item->grade, $item->panjang, $item->diameter);
            $kubikasi = round($item->kubikasi, 4);
            $grandTotalRupiah += round(($harga ?? 0) * $kubikasi * 1000);
        }
        $grandTotalRupiah = (int) round($grandTotalRupiah);

        $pembulatanManual = (int) ($record->adjustment ?? 0);
        $biayaTurunPerM3 = 5000;

        $hasilDasar = round($totalKubikasiGlobal * $biayaTurunPerM3);
        $biayaFloor = floor($hasilDasar / 1000) * 1000;
        $sisaRibuan = $grandTotalRupiah % 1000;
        
        $biayaTurunKayu = (int) ($biayaFloor + $sisaRibuan + 10000);
        $hargaBeliAkhir = (int) round($grandTotalRupiah - $biayaTurunKayu);

        $mod = $hargaBeliAkhir % 5000;
        $hargaBeliAkhirBulat = $mod >= 2500 ? $hargaBeliAkhir + (5000 - $mod) : $hargaBeliAkhir - $mod;
        $totalAkhir = (int) ($hargaBeliAkhirBulat + $pembulatanManual);
        
        $modFinal = $totalAkhir % 5000;
        $totalAkhir = $modFinal >= 2500 ? $totalAkhir + (5000 - $modFinal) : $totalAkhir - $modFinal;
        $selisih = (int) ($grandTotalRupiah - $totalAkhir);

        return view('nota-kayu.turus', [
            'record'            => $record,
            'groupedDetails'    => $groupedDetails,
            'controller'        => $this,
            'jenisKayuId'       => $jenisKayuId,
            'grade'             => $grade,
            'panjang'           => $panjang,
            'totalBatangGlobal'   => $totalBatangGlobal,
            'totalKubikasiGlobal' => round($totalKubikasiGlobal, 4),
            'grandTotalRupiah'    => $grandTotalRupiah,
            'selisih'             => $selisih,
            'totalAkhir'          => $totalAkhir 
        ]);
    }

    public function show2(NotaKayu $record)
    {
        $record->load([
            'kayuMasuk.detailTurusanKayus.jenisKayu',
            'kayuMasuk.penggunaanSupplier',
            'kayuMasuk.penggunaanKendaraanSupplier',
            'kayuMasuk.penggunaanDokumenKayu',
        ]);

        $details = $record->kayuMasuk->detailTurusanKayus ?? collect();

        $firstItem = $details->first();
        $jenisKayuId = $firstItem->jenis_kayu_id 
            ?? optional($firstItem->jenisKayu)->id 
            ?? 1;
        $grade = $firstItem->grade ?? 1;
        $panjang = $firstItem->panjang ?? 130;

        $groupedDetails = $details->groupBy(function($item) {
            $kodeLahan = optional($item->lahan)->kode_lahan ?? '-';
            $grade = $item->grade ?? 0;
            $panjang = $item->panjang ?? '-';
            $jenis = optional($item->jenisKayu)->nama_kayu ?? '-';
            return "{$kodeLahan}|{$grade}|{$panjang}|{$jenis}";
        });

        $totalBatangGlobal = $details->sum('kuantitas');
        
        $totalKubikasiGlobal = $details->sum(function ($item) {
            return round($item->kubikasi, 4);
        });

        $grandTotalRupiah = 0;
        foreach ($details as $item) {
            $idJenis = $item->id_jenis_kayu ?? optional($item->jenisKayu)->id ?? $jenisKayuId;
            $harga = $this->getHargaSatuan($idJenis, $item->grade, $item->panjang, $item->diameter);
            $kubikasi = round($item->kubikasi, 4);
            $grandTotalRupiah += round(($harga ?? 0) * $kubikasi * 1000);
        }
        $grandTotalRupiah = (int) round($grandTotalRupiah);

        $pembulatanManual = (int) ($record->adjustment ?? 0);
        $biayaTurunPerM3 = 5000;

        $hasilDasar = round($totalKubikasiGlobal * $biayaTurunPerM3);
        $biayaFloor = floor($hasilDasar / 1000) * 1000;
        $sisaRibuan = $grandTotalRupiah % 1000;
        
        $biayaTurunKayu = (int) ($biayaFloor + $sisaRibuan + 10000);
        $hargaBeliAkhir = (int) round($grandTotalRupiah - $biayaTurunKayu);

        $mod = $hargaBeliAkhir % 5000;
        $hargaBeliAkhirBulat = $mod >= 2500 ? $hargaBeliAkhir + (5000 - $mod) : $hargaBeliAkhir - $mod;
        $totalAkhir = (int) ($hargaBeliAkhirBulat + $pembulatanManual);
        
        $modFinal = $totalAkhir % 5000;
        $totalAkhir = $modFinal >= 2500 ? $totalAkhir + (5000 - $modFinal) : $totalAkhir - $modFinal;
        $selisih = (int) ($grandTotalRupiah - $totalAkhir);

        // --- PAGINATION FOR A4 LANDSCAPE (CETAK TURUS 2) ---
        $processedGroups = [];
        foreach ($groupedDetails as $key => $items) {
            [$kodeLahan, $groupGrade, $groupPanjang, $jenis] = explode('|', $key);
            $firstItem = $items->first();
            $idJenis = optional($firstItem->jenisKayu)->id
                ?? $firstItem->id_jenis_kayu
                ?? $jenisKayuId;

            $dataTabel = $this->groupByDiameterSpesifik(
                $items,
                $idJenis,
                $groupGrade,
                $groupPanjang
            );

            $processedGroups[] = [
                'kodeLahan' => $kodeLahan,
                'grade' => $groupGrade,
                'panjang' => $groupPanjang,
                'jenis' => $jenis,
                'rows' => $dataTabel->toArray(),
                'subBatang' => $dataTabel->sum('batang'),
            ];
        }

        // Setiap halaman selalu punya TEPAT 3 kolom. Setiap grup (atau potongannya)
        // dicek muat/tidak terhadap kapasitas KOLOM saat ini (bukan kapasitas halaman),
        // supaya tidak ada grup yang tembus melebihi tinggi kolom 125mm.
        $MAX_UNITS_PER_COL = 22; // ~22 unit (baris + header + subtotal) muat di tinggi kolom 125mm

        $pages = [];
        $currentPage = [[], [], []]; // 3 kolom kosong
        $colIndex = 0;
        $colUnits = 0;

        foreach ($processedGroups as $group) {
            $remainingRows = $group['rows'];
            $isFirstPart = true;

            // Loop ini memastikan grup besar dipotong jadi beberapa bagian
            // sampai semua baris grup tersebut habis ditempatkan.
            do {
                $headerUnits = 5;
                $capacityLeft = $MAX_UNITS_PER_COL - $colUnits;
                $rowsCapacity = $capacityLeft - $headerUnits;

                // Kolom saat ini tidak cukup bahkan untuk header -> pindah kolom/halaman
                if ($rowsCapacity < 1) {
                    $colIndex++;
                    if ($colIndex > 2) {
                        $pages[] = $currentPage;
                        $currentPage = [[], [], []];
                        $colIndex = 0;
                    }
                    $colUnits = 0;
                    continue;
                }

                if (count($remainingRows) <= $rowsCapacity) {
                    $partRows = $remainingRows;
                    $remainingRows = [];
                } else {
                    $partRows = array_slice($remainingRows, 0, $rowsCapacity);
                    $remainingRows = array_slice($remainingRows, $rowsCapacity);
                }

                $isLastPart = empty($remainingRows);

                $currentPage[$colIndex][] = [
                    'kodeLahan'      => $group['kodeLahan'],
                    'grade'          => $group['grade'],
                    'panjang'        => $group['panjang'],
                    'jenis'          => $group['jenis'],
                    'rows'           => $partRows,
                    'subBatang'      => collect($partRows)->sum('batang'),
                    'is_continued'   => !$isFirstPart,
                    'show_subtotal'  => $isLastPart,
                ];

                $colUnits += $headerUnits + count($partRows);
                $isFirstPart = false;

                // Masih ada sisa baris grup ini -> kolom ini dianggap penuh, lanjut ke kolom berikutnya
                if (!$isLastPart) {
                    $colIndex++;
                    if ($colIndex > 2) {
                        $pages[] = $currentPage;
                        $currentPage = [[], [], []];
                        $colIndex = 0;
                    }
                    $colUnits = 0;
                }
            } while (!empty($remainingRows));
        }

        if (!empty($currentPage[0]) || !empty($currentPage[1]) || !empty($currentPage[2])) {
            $pages[] = $currentPage;
        }

        return view('nota-kayu.turus2', [
            'record'            => $record,
            'pages'             => $pages,
            'totalBatangGlobal'   => $totalBatangGlobal,
            'totalKubikasiGlobal' => round($totalKubikasiGlobal, 4),
            'grandTotalRupiah'    => $grandTotalRupiah,
            'selisih'             => $selisih,
            'totalAkhir'          => $totalAkhir 
        ]);
    }

    public function groupByDiameterSpesifik(Collection $items, $idJenisKayu, $grade, $panjang)
    {
        $groups = $items->groupBy('diameter');
        $hasil = collect();

        foreach ($groups as $diameter => $detailItems) {
            $batang = $detailItems->sum('kuantitas');
            $kubikasi = $detailItems->sum(function ($item) {
                return round($item->kubikasi, 4);
            });

            $hargaSatuan = $this->getHargaSatuan($idJenisKayu, $grade, $panjang, $diameter);
            $totalHarga = round($hargaSatuan * $kubikasi * 1000);

            $hasil->push([
                'diameter'      => $diameter,
                'batang'        => $batang,
                'kubikasi'      => $kubikasi,
                'harga_satuan'  => $hargaSatuan,
                'total_harga'   => $totalHarga,
            ]);
        }

        return $hasil->sortBy('diameter')->values();
    }

    private function getHargaSatuan($idJenisKayu, $grade, $panjang, $diameter)
    {
        return HargaKayu::where('id_jenis_kayu', $idJenisKayu)
            ->where('grade', $grade)
            ->where('panjang', $panjang)
            ->where('diameter_terkecil', '<=', $diameter)
            ->where('diameter_terbesar', '>=', $diameter)
            ->orderBy('diameter_terkecil', 'desc')
            ->value('harga_beli') ?? 0;
    }
}