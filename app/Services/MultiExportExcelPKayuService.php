<?php

namespace App\Services;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MultiExportExcelPKayuService implements WithMultipleSheets
{
    protected array $allData;

    public function __construct(array $allData)
    {
        $this->allData = $allData;
    }

    /**
     * Di sini kita membuat array berisi objek-objek sheet
     */
    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->allData as $namaLahan => $data) {
            $sheets[] = new ExportExcelPersentaseKayuService(
                $data['laporan'], 
                $data['rekap'], 
                $namaLahan, // Nama ini akan jadi nama tab sheet
                $data['date']
            );
        }

        return $sheets;
    }
}