<?php

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

 // Tambahan agar kolom rapi

class LaporanKayu implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    protected $query;

    protected array $columns;

    /**
     * @param  Builder|Collection  $query
     */
    public function __construct($query, array $columns)
    {
        // Query yang masuk sudah membawa filter dari Controller (Request $request)
        $this->query = $query;
        $this->columns = $columns;
    }

    /**
     * Mengambil data dari Query Builder ATAU Collection.
     *
     * Sebelumnya $query selalu berupa Query Builder, jadi selalu perlu
     * ->get(). Sekarang controller kadang sudah mengirim Collection yang
     * sudah jadi (hasil agregasi PHP) — Collection::get() butuh argumen
     * key, jadi tidak boleh dipanggil tanpa parameter. Maka: kalau sudah
     * Collection, pakai langsung; kalau masih Builder, baru panggil get().
     */
    public function collection()
    {
        if ($this->query instanceof Collection) {
            return $this->query;
        }

        // Mengambil data akhir yang sudah difilter oleh baseQuery()
        return $this->query->get();
    }

    /**
     * Membuat Header Excel berdasarkan label yang didefinisikan di Controller
     */
    public function headings(): array
    {
        return array_map(fn ($c) => $c['label'], $this->columns);
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
                return number_format((float) $value, 4, '.', '');
            }

            if ($col['field'] === 'poin') {
                return (int) $value;
            }

            return $value;
        }, $this->columns);
    }
}
