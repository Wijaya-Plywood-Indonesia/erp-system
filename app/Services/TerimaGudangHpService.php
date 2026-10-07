<?php

namespace App\Services;

use App\Models\JenisKayu;
use App\Models\SerahTerimaHp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Penerimaan barang ke Gudang Triplek Mentah / Gudang Platform Mentah.
 * Sumber yang didukung:
 *  - Hasil Hotpress Triplek   → terimaTriplek()
 *  - Hasil Hotpress Platform  → terimaPlatform()
 *  - Hasil Graji Triplek      → terimaGraji()
 */
class TerimaGudangHpService
{
    public function terimaTriplek(int $idSerahTerima): void
    {
        $this->terima($idSerahTerima, 'triplek');
    }

    public function terimaPlatform(int $idSerahTerima): void
    {
        $this->terima($idSerahTerima, 'platform');
    }

    /**
     * Terima hasil Graji Triplek ke Gudang Triplek Mentah.
     * SerahTerimaHp harus punya id_hasil_graji_triplek dan tujuan='gudang_triplek_mth'.
     */
    public function terimaGraji(int $idSerahTerima): void
    {
        DB::transaction(function () use ($idSerahTerima) {
            $st = SerahTerimaHp::lockForUpdate()->find($idSerahTerima);

            if (! $st || $st->diterima_gudang_at || $st->ditolak_oleh || $st->diterima_oleh !== '-') {
                throw new \RuntimeException('Barang ini sudah diterima atau sudah ditolak.');
            }

            if (! $st->id_hasil_graji_triplek || $st->tujuan !== 'gudang_triplek_mth') {
                throw new \RuntimeException('Record bukan serahan Graji ke Gudang Triplek Mentah.');
            }

            $hasil = $st->hasilGrajiTriplek()
                ->with('barangSetengahJadiHp.ukuran', 'barangSetengahJadiHp.grade', 'barangSetengahJadiHp.jenisBarang')
                ->first();

            if (! $hasil || ! $hasil->barangSetengahJadiHp) {
                throw new \RuntimeException('Data barang setengah jadi tidak ditemukan.');
            }

            $bsj     = $hasil->barangSetengahJadiHp;
            $ukuran  = $bsj->ukuran;
            $grade   = $bsj->grade;
            $jenisBarang = $bsj->jenisBarang;

            if (! $ukuran || ! $grade || ! $jenisBarang) {
                throw new \RuntimeException('Data ukuran, grade, atau jenis barang tidak lengkap.');
            }

            $jenisKayu = \App\Models\JenisKayu::where('nama_kayu', $jenisBarang->nama_jenis_barang)->first();

            if (! $jenisKayu) {
                throw new \RuntimeException("Jenis kayu \"{$jenisBarang->nama_jenis_barang}\" tidak ditemukan di data Jenis Kayu.");
            }

            $lembar   = (float) $hasil->isi;
            $kubikasi = $lembar * (float) $ukuran->kubikasi / 10000000;

            app(StokTriplekMthService::class)->tambah(
                idJenisKayu: $jenisKayu->id,
                panjang:     $ukuran->panjang,
                lebar:       $ukuran->lebar,
                tebal:       $ukuran->tebal,
                kwGrade:     $grade->nama_grade,
                lembar:      $lembar,
                kubikasi:    $kubikasi,
                keterangan:  'Masuk Gudang Triplek Mentah — dari hasil Graji Triplek (via serah terima #'.$st->id.')',
                referensi:   $st,
            );

            $user = Auth::user()?->name ?? 'System';

            $st->update([
                'diterima_oleh'      => $user.' - Gudang Triplek Mentah',
                'status'             => 'Terima Gudang Triplek Mentah',
                'diterima_gudang_at' => now(),
                'diterima_gudang_oleh' => $user,
            ]);
        });
    }

    private function terima(int $id, string $tipe): void
    {
        DB::transaction(function () use ($id, $tipe) {
            $st = SerahTerimaHp::lockForUpdate()->find($id);

            if (! $st || $st->diterima_gudang_at || $st->ditolak_oleh || $st->diterima_oleh !== '-') {
                throw new \RuntimeException('Barang ini sudah diterima atau sudah ditolak.');
            }

            $relasi = $tipe === 'triplek' ? 'triplekHasilHp' : 'platformHasilHp';
            $hasil = $st->{$relasi}()
                ->with('barangSetengahJadi.ukuran', 'barangSetengahJadi.grade', 'barangSetengahJadi.jenisBarang')
                ->first();

            if (! $hasil || ! $hasil->barangSetengahJadi) {
                throw new \RuntimeException('Data barang setengah jadi tidak ditemukan.');
            }

            $ukuran = $hasil->barangSetengahJadi->ukuran;
            $grade = $hasil->barangSetengahJadi->grade;
            $jenisBarang = $hasil->barangSetengahJadi->jenisBarang;

            if (! $ukuran || ! $grade || ! $jenisBarang) {
                throw new \RuntimeException('Data ukuran, grade, atau jenis barang tidak lengkap.');
            }

            // "Jenis Barang" merepresentasikan jenis kayu — dicocokkan by nama.
            $jenisKayu = JenisKayu::where('nama_kayu', $jenisBarang->nama_jenis_barang)->first();

            if (! $jenisKayu) {
                throw new \RuntimeException("Jenis kayu \"{$jenisBarang->nama_jenis_barang}\" tidak ditemukan di data Jenis Kayu.");
            }

            $lembar = (float) $hasil->isi;
            $kubikasi = $lembar * (float) $ukuran->kubikasi / 10000000;

            $service = $tipe === 'triplek'
                ? app(StokTriplekMthService::class)
                : app(StokPlatformMthService::class);

            $service->tambah(
                idJenisKayu: $jenisKayu->id,
                panjang: $ukuran->panjang,
                lebar: $ukuran->lebar,
                tebal: $ukuran->tebal,
                kwGrade: $grade->nama_grade,
                lembar: $lembar,
                kubikasi: $kubikasi,
                keterangan: ($tipe === 'triplek'
                    ? 'Masuk Gudang Triplek Mentah — dari hotpress'
                    : 'Masuk Gudang Platform Mentah — dari hotpress')
                    .' (via serah terima #'.$st->id.')',
                referensi: $st,
            );

            $namaGudang = $tipe === 'triplek' ? 'Gudang Triplek Mentah' : 'Gudang Platform Mentah';
            $user = Auth::user()?->name ?? 'System';

            $st->update([
                'diterima_oleh' => $user.' - '.$namaGudang,
                'status' => 'Terima '.$namaGudang,
                'diterima_gudang_at' => now(),
                'diterima_gudang_oleh' => $user,
            ]);
        });
    }
}
