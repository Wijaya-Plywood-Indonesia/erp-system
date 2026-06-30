<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanPressDryerExport implements WithMultipleSheets
{
    protected $dataProduksi;

    public function __construct($dataProduksi)
    {
        $this->dataProduksi = $dataProduksi;
    }

    public function sheets(): array
    {
        return [
            new Sheets\LaporanPressDryerSheet($this->dataProduksi),
            new Sheets\HasilProduksiSheet($this->dataProduksi),
            new Sheets\JurnalSheet($this->dataProduksi),
        ];
    }
}