<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Services\NewRekapAbsensiPegawaiService;

class RumusGajiWijayaMingguanExport implements WithMultipleSheets
{
    use Exportable;

    protected string $tanggalDipilih;

    public function __construct(string $tanggalDipilih)
    {
        $this->tanggalDipilih = $tanggalDipilih;
    }

    public function sheets(): array
    {
        $acuan = Carbon::parse($this->tanggalDipilih)->startOfDay();
        
        $jumatAwal = $acuan->copy();
        while (! $jumatAwal->isFriday()) {
            $jumatAwal->subDay();
        }

        $kamisAkhir = $jumatAwal->copy()->addDays(6);
        
        $tanggalSekarang = $jumatAwal->copy();
        $tanggalAkhir = $kamisAkhir->copy();

        $sheets = [];

        $hariIndo = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        while ($tanggalSekarang->lessThanOrEqualTo($tanggalAkhir)) {
            $tglStr = $tanggalSekarang->toDateString();
            $rekap = app(NewRekapAbsensiPegawaiService::class)->getRekap($tglStr);
            
            $sheetName = $hariIndo[$tanggalSekarang->format('l')];
            $sheet = new RumusGajiWijayaExport($rekap, $tglStr, $sheetName);
            
            $sheets[] = $sheet;

            $tanggalSekarang->addDay();
        }

        return $sheets;
    }
}
