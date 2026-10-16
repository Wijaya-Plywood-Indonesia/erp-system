<?php

namespace App\Services\Akuntansi\Produksi;

/**
 * Satu jenis produksi yang bisa dikirim ke akuntansi.
 *
 * Menambah produksi baru (kedi, rotary, join, repair, hotpress, ...) =
 * buat 1 class yang mengimplementasikan interface ini, lalu daftarkan di
 * JurnalProduksiRegistry. Tidak ada kode API/UI yang perlu diubah.
 */
interface JurnalProduksiSource
{
    /** Kunci pendek, dipakai di API & log. Contoh: 'dryer'. */
    public function key(): string;

    /** Nama tampilan di menu. Contoh: 'Produksi Dryer'. */
    public function label(): string;

    /**
     * Bangun jurnal untuk satu tanggal produksi, memakai data ERP saat ini.
     *
     * @param  string  $tanggal  Y-m-d
     * @return array<int, array{no_dokumen:string, rows:array}>  kosong = tidak ada produksi
     */
    public function build(string $tanggal): array;
}
