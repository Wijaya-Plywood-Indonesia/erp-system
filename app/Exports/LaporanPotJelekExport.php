<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanPotJelekExport implements FromCollection, WithHeadings, WithTitle
{
    protected Collection $data;

    public function __construct(array $dataProduksi)
    {
        // GROUPING: NOMOR MEJA + KODE UKURAN (POT JELEK)
        $this->data = collect($dataProduksi)
            ->groupBy(fn($item) => $item['nomor_meja'] . '|' . $item['kode_ukuran']);
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->data as $groupKey => $items) {

            $first = $items->first();

            $nomorMeja = $first['nomor_meja'];
            $kodeUkuran = $first['kode_ukuran']; // Akan berisi "POT JELEK"
            $tanggal = $first['tanggal'];

            $target = (int) $first['target'];
            $hasil = (int) $first['hasil'];
            $selisih = (int) $first['selisih'];

            $pekerja = $first['pekerja'] ?? [];

            // ==========================================
            // HEADER INFORMASI (BLOK ATAS POT JELEK)
            // ==========================================
            $rows->push(['AREA KERJA', $nomorMeja]);
            $rows->push(['KATEGORI LAPORAN', $kodeUkuran]);
            $rows->push(['TANGGAL PRODUKSI', $tanggal]);
            $rows->push([]); // Spasi kosong

            // =============================
            // HEADER TABEL
            // =============================
            $rows->push([
                'ID PEGAWAI',
                'Nama Lengkap',
                'Jam Masuk',
                'Jam Pulang',
                'Potongan Target',
                'Keterangan',
                '',
                'Target Harian',
                'Hasil Produksi',
                'Selisih',
            ]);

            // =============================
            // DATA PEKERJA (ISI TABEL)
            // =============================
            foreach ($pekerja as $p) {
                $potongan = (int) ($p['pot_target'] ?? 0);

                $rows->push([
                    $p['id'] ?? '-',
                    $p['nama'] ?? '-',
                    $p['jam_masuk'] ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $potongan > 0 ? $potongan : '-',
                    $p['keterangan'] ?? '-',
                    '',
                    $target,
                    $hasil,
                    $selisih >= 0 ? '+' . $selisih : $selisih,
                ]);
            }

            // =============================
            // FOOTER BLOK (TOTAL)
            // =============================
            $totalPotonganGrup = collect($pekerja)->sum('pot_target');

            $rows->push([
                'TOTAL',
                count($pekerja) . ' Orang',
                '',
                '',
                $totalPotonganGrup > 0 ? $totalPotonganGrup : '-',
                '',
                '',
                $target,
                $hasil,
                $selisih >= 0 ? '+' . $selisih : $selisih,
            ]);

            // SPASI ANTAR BLOK PRODUKSI AGAR RAPI DI EXCEL
            $rows->push([]);
            $rows->push([]);
        }

        return $rows;
    }

    public function headings(): array
    {
        // Menggunakan push manual di collection agar bisa kustom per blok
        return [];
    }

    public function title(): string
    {
        return 'Laporan Produksi Potong Jelek';
    }
}
