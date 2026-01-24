<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class LaporanPotJelekExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    protected Collection $data;

    public function __construct(array $dataProduksi)
    {
        // Data sudah dikelompokkan per individu oleh PotJelekDataMap
        $this->data = collect($dataProduksi);
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->data as $item) {
            // =============================
            // HEADER INFORMASI (IDENTITAS PEGAWAI)
            // =============================
            $rows->push(['PEGAWAI', $item['kode_nama']]);
            $rows->push(['TANGGAL PRODUKSI', $item['tanggal']]);
            $rows->push([]); // Baris kosong pemisah

            // =============================
            // HEADER TABEL PENGERJAAN
            // =============================
            $rows->push([
                'KODE UKURAN',
                'HASIL',
                'JAM MASUK',
                'JAM PULANG',
                'POTONGAN TARGET',
                'IJIN',
                'KETERANGAN / KENDALA',
            ]);

            // =============================
            // DATA PENGERJAAN (RINCIAN UKURAN)
            // =============================
            foreach ($item['rincian'] as $index => $detail) {
                $rows->push([
                    $detail['ukuran_lengkap'],
                    $detail['jumlah'],
                    // Data profil pegawai hanya muncul di baris pertama rincian
                    $index === 0 ? $item['jam_masuk'] : '',
                    $index === 0 ? $item['jam_pulang'] : '',
                    $index === 0 ? ($item['pot_target'] > 0 ? 'Rp ' . number_format($item['pot_target']) : '-') : '',
                    $index === 0 ? $item['ijin'] : '',
                    $index === 0 ? $item['keterangan'] : '',
                ]);
            }

            // =============================
            // FOOTER BLOK (RINGKASAN PERFORMA)
            // =============================
            $rows->push([
                'TOTAL',
                $item['hasil'], // Total akumulasi hasil individu
                'TARGET: ' . number_format($item['target']),
                'SELISIH: ' . ($item['selisih'] >= 0 ? '+' : '') . number_format($item['selisih']),
                'DIPOTONG: ' . ($item['pot_target'] > 0 ? 'Rp ' . number_format($item['pot_target']) : '0'),
                '',
                '',
            ]);

            // SPASI ANTAR BLOK PEGAWAI AGAR MUDAH DIBACA
            $rows->push([]);
            $rows->push([]);
        }

        return $rows;
    }

    public function headings(): array
    {
        // Headings dikelola manual di dalam collection() untuk fleksibilitas blok
        return [];
    }

    public function title(): string
    {
        return 'Laporan Produksi Potong Jelek';
    }
}
