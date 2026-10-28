<?php

namespace App\Exports;

use App\Exports\Sheets\RekapNotaSheetV1;
use App\Exports\Sheets\RekapNotaSheetV2;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RekapNotaExport implements WithMultipleSheets
{
    public int $nomorBerikutnya;

    public function __construct(
        protected string $jenis,
        protected string $tanggal,
        protected ?int $nomorAwal = null,
    ) {
        $this->nomorBerikutnya = $nomorAwal ?? 1;
    }

    public function sheets(): array
    {
        $sheet1 = new RekapNotaSheetV1($this->jenis, $this->tanggal, $this->nomorAwal);
        $sheet2 = new RekapNotaSheetV2($this->jenis, $this->tanggal, $this->nomorAwal);

        return [
            $sheet1,
            $sheet2,
        ];
    }
}
