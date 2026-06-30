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

        $MAX_UNITS_PER_PAGE = 66; // Max units in 3 columns of 22 units each
        $pages = [];
        $currentPageGroups = [];
        $currentUnits = 0;

        foreach ($processedGroups as $group) {
            $rows = $group['rows'];
            $totalRows = count($rows);
            $groupUnits = 5 + $totalRows;

            if ($currentUnits + $groupUnits <= $MAX_UNITS_PER_PAGE) {
                $currentPageGroups[] = [
                    'kodeLahan' => $group['kodeLahan'],
                    'grade' => $group['grade'],
                    'panjang' => $group['panjang'],
                    'jenis' => $group['jenis'],
                    'rows' => $rows,
                    'subBatang' => $group['subBatang'],
                    'is_continued' => false,
                ];
                $currentUnits += $groupUnits;
            } else {
                $spaceLeft = $MAX_UNITS_PER_PAGE - $currentUnits;
                if ($spaceLeft >= 8 && $totalRows > 3) {
                    $rowsForCurrentPage = $spaceLeft - 5;
                    $rowsForCurrentPage = max(2, min($rowsForCurrentPage, $totalRows - 2));

                    $part1Rows = array_slice($rows, 0, $rowsForCurrentPage);
                    $part2Rows = array_slice($rows, $rowsForCurrentPage);

                    $subBatang1 = collect($part1Rows)->sum('batang');
                    $subBatang2 = collect($part2Rows)->sum('batang');

                    $currentPageGroups[] = [
                        'kodeLahan' => $group['kodeLahan'],
                        'grade' => $group['grade'],
                        'panjang' => $group['panjang'],
                        'jenis' => $group['jenis'],
                        'rows' => $part1Rows,
                        'subBatang' => $subBatang1,
                        'is_continued' => false,
                        'show_subtotal' => false,
                    ];

                    $pages[] = $currentPageGroups;

                    $currentPageGroups = [];
                    $currentPageGroups[] = [
                        'kodeLahan' => $group['kodeLahan'],
                        'grade' => $group['grade'],
                        'panjang' => $group['panjang'],
                        'jenis' => $group['jenis'],
                        'rows' => $part2Rows,
                        'subBatang' => $subBatang2,
                        'is_continued' => true,
                        'show_subtotal' => true,
                    ];
                    $currentUnits = 5 + count($part2Rows);
                } else {
                    if (!empty($currentPageGroups)) {
                        $pages[] = $currentPageGroups;
                    }
                    $currentPageGroups = [];
                    $currentPageGroups[] = [
                        'kodeLahan' => $group['kodeLahan'],
                        'grade' => $group['grade'],
                        'panjang' => $group['panjang'],
                        'jenis' => $group['jenis'],
                        'rows' => $rows,
                        'subBatang' => $group['subBatang'],
                        'is_continued' => false,
                    ];
                    $currentUnits = $groupUnits;
                }
            }
        }

        if (!empty($currentPageGroups)) {
            $pages[] = $currentPageGroups;
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