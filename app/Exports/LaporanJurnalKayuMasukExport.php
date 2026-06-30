<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanJurnalKayuMasukExport implements WithMultipleSheets
{
    protected array $jurnalTables;

    public function __construct(array $jurnalTables)
    {
        $this->jurnalTables = $jurnalTables;
    }

    /**
     * Map multiple worksheets for the Excel download.
     */
    public function sheets(): array
    {
        return [
            new LaporanJurnalKayuMasukSheet2($this->jurnalTables),
            new LaporanJurnalKayuMasukSheet1($this->jurnalTables),
        ];
    }
}
