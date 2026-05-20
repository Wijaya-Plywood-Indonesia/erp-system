<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanPressDryerSheet implements FromCollection, WithHeadings, WithTitle
{
    protected $dataProduksi;

    public function __construct($dataProduksi)
    {
        $this->dataProduksi = collect($dataProduksi)->groupBy('mesin');
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->dataProduksi as $mesinNama => $produksiList) {
            $first = $produksiList->first();
            $pekerja = $first['pekerja'] ?? [];
            $kendala = $first['kendala'] ?? 'Tidak ada kendala.';
            $tanggal = $first['tanggal'] ?? '';
            $target = $first['target'] ?? 0;
            $jamKerja = $first['jam_kerja'] ?? 0;
            $hasil = $first['hasil'] ?? 0;
            $selisih = $first['selisih'] ?? 0;

            $rows->push(['MESIN: ' . strtoupper($mesinNama)]);
            $rows->push(['TANGGAL: ' . $tanggal]);
            $rows->push([]);

            $rows->push([
                'ID', 'Nama', 'Masuk', 'Pulang', 'Ijin',
                'Potongan Target', 'Keterangan', '',
                'Target Harian', 'Jam Kerja', 'Hasil', 'Selisih', 'Kendala'
            ]);

            foreach ($pekerja as $p) {
                $potTargetRaw = (float) str_replace('.', '', $p['pot_target'] ?? '0');
                $rows->push([
                    $p['id'] ?? '-',
                    $p['nama'] ?? '-',
                    $p['jam_masuk'] ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin'] ?? '-',
                    $potTargetRaw > 0 ? (int) $potTargetRaw : '-',
                    $p['keterangan'] ?? '-',
                    '',
                    (int) $target,
                    (int) $jamKerja,
                    (int) $hasil,
                    $selisih >= 0 ? '+' . (int) abs($selisih) : (int) $selisih,
                    $kendala
                ]);
            }

            $totalPekerja = count($pekerja);
            $totalPotongan = collect($pekerja)->sum(fn($p) => (float) str_replace('.', '', $p['pot_target'] ?? '0'));

            $rows->push([
                'TOTAL', '', '', '', '',
                $totalPotongan > 0 ? (int) $totalPotongan : '',
                '', '',
                (int) $target,
                (int) $jamKerja,
                (int) $hasil,
                $selisih >= 0 ? '+' . (int) abs($selisih) : (int) $selisih,
                '',
                $totalPekerja . ' pekerja'
            ]);

            $rows->push([]);
            $rows->push([]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'Laporan Press Dryer';
    }
}