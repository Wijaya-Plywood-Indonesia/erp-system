<?php

namespace App\Exports;

use App\Models\KontrakKerja;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

class KontrakKerjaExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    protected string $status;
    protected string $karyawan_di;

    public function __construct(string $status, string $karyawan_di = 'all')
    {
        $this->status = $status;
        $this->karyawan_di = $karyawan_di;
    }

    public function query()
    {
        $query = KontrakKerja::query();

        if ($this->status !== 'all') {
            $query->where('status_kontrak', $this->status);
        }

        if ($this->karyawan_di !== 'all') {
            $query->where('karyawan_di', $this->karyawan_di);
        }

        return $query->latest('id');
    }

    public function headings(): array
    {
        return [
            'No Dokumen Kontrak',
            'Kode Pegawai',
            'Nama Pegawai',
            'Jenis Kelamin',
            'Jabatan',
            'Lokasi Karyawan',
            'Mulai Kontrak',
            'Selesai Kontrak',
            'Durasi (hari)',
            'Status Dokumen',
            'Status Kontrak',
        ];
    }

    /**
     * @param KontrakKerja $row
     */
    public function map($row): array
    {
        return [
            $row->no_kontrak,
            $row->kode,
            $row->nama,
            $row->jenis_kelamin,
            $row->jabatan,
            $row->karyawan_di,
            $row->kontrak_mulai ? Carbon::parse($row->kontrak_mulai)->format('d/m/Y') : '-',
            $row->kontrak_selesai ? Carbon::parse($row->kontrak_selesai)->format('d/m/Y') : '-',
            $row->durasi_kontrak,
            ucfirst($row->status_dokumen),
            ucfirst($row->status_kontrak),
        ];
    }
}
