<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize; // Tambahan agar kolom rapi

class LaporanKayu implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $query;
    protected array $columns;

    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $columns
     */
    public function __construct($query, array $columns)
    {
        // Query yang masuk sudah membawa filter dari Controller (Request $request)
        $this->query = $query;
        $this->columns = $columns;
    }

    /**
     * Mengambil data dari Query Builder
     */
    public function collection()
    {
        // Mengambil data akhir yang sudah difilter oleh baseQuery()
        return $this->query->get();
    }

    /**
     * Membuat Header Excel berdasarkan label yang didefinisikan di Controller
     */
    public function headings(): array
    {
        return array_map(fn($c) => $c['label'], $this->columns);
    }

    /**
     * Memetakan data baris per baris agar sesuai dengan kolom yang dipilih
     */
    public function map($row): array
    {
        return array_map(function ($col) use ($row) {
            $value = data_get($row, $col['field']);

            // Formatting tambahan jika field adalah 'm3' atau 'poin'
            if ($col['field'] === 'm3') {
                return number_format((float)$value, 4, '.', '');
            }

            if ($col['field'] === 'poin') {
                return (int)$value;
            }

            return $value;
        }, $this->columns);
    }
}
