<?php

namespace App\Services;

use App\Models\HasilTerimaGudangSatu;
use App\Models\JenisKayu;
use App\Models\ProduksiTerimaGudangSatu;
use App\Models\SerahTerimaGudangSatu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TerimaGudangSatuService
{
    public function kembaliKeGudang(
        SerahTerimaGudangSatu $serah,
        float $jumlah,
        ?ProduksiTerimaGudangSatu $produksi = null
    ): HasilTerimaGudangSatu {
        if ($jumlah <= 0) {
            throw new \RuntimeException(
                'Jumlah pengembalian harus lebih dari 0.'
            );
        }

        return DB::transaction(function () use ($serah, $jumlah, $produksi) {

            /*
             * Ambil ulang data Serah Terima dengan lock
             * agar proses pengembalian aman.
             */
            $serah = SerahTerimaGudangSatu::query()
                ->lockForUpdate()
                ->find($serah->id);

            if (! $serah) {
                throw new \RuntimeException(
                    'Data serah terima gudang satu tidak ditemukan.'
                );
            }

            /*
             * Cek sisa bahan yang masih tersedia.
             */
            $sisaSaatIni = (float) $serah->sisa;

            if ($jumlah > $sisaSaatIni) {
                throw new \RuntimeException(
                    "Jumlah melebihi sisa yang tersedia ({$sisaSaatIni})."
                );
            }

            /*
             * Ambil data barang setengah jadi
             * dari sumber Serah Terima.
             */
            $barang = $serah->barangSetengahJadi;

            if (
                ! $barang ||
                ! $barang->id_jenis_barang ||
                ! $barang->id_ukuran
            ) {
                throw new \RuntimeException(
                    'Data jenis barang/ukuran pada sumber ini tidak lengkap, tidak bisa dikembalikan.'
                );
            }

            /*
             * Produksi tujuan pengembalian harus tersedia.
             */
            if (! $produksi) {
                throw new \RuntimeException(
                    'Produksi Sampling Plywood tujuan pengembalian tidak ditemukan.'
                );
            }

            /*
             * Ambil data jenis barang, ukuran, dan grade.
             */
            $barang->loadMissing([
                'jenisBarang',
                'ukuran',
                'grade',
            ]);

            $jenisBarang = $barang->jenisBarang;
            $ukuran = $barang->ukuran;
            $grade = $barang->grade;

            if (! $jenisBarang) {
                throw new \RuntimeException(
                    'Jenis barang tidak ditemukan.'
                );
            }

            if (! $ukuran) {
                throw new \RuntimeException(
                    'Ukuran barang tidak ditemukan.'
                );
            }

            if (! $grade) {
                throw new \RuntimeException(
                    'Grade barang tidak ditemukan.'
                );
            }

            /*
             * Cari jenis kayu berdasarkan nama jenis barang.
             *
             * Stok Gudang Satu menggunakan id_jenis_kayu,
             * sedangkan barang setengah jadi menggunakan
             * id_jenis_barang.
             */
            $jenisKayu = JenisKayu::query()
                ->where('nama_kayu', $jenisBarang->nama_jenis_barang)
                ->first();

            if (! $jenisKayu) {
                throw new \RuntimeException(
                    "Jenis kayu dengan nama '{$jenisBarang->nama_jenis_barang}' tidak ditemukan."
                );
            }

            /*
             * Ambil dimensi ukuran.
             */
            $panjang = (float) $ukuran->panjang;
            $lebar = (float) $ukuran->lebar;
            $tebal = (float) $ukuran->tebal;

            if (
                $panjang <= 0 ||
                $lebar <= 0 ||
                $tebal <= 0
            ) {
                throw new \RuntimeException(
                    'Data panjang, lebar, atau tebal pada ukuran tidak valid.'
                );
            }

            /*
             * Hitung kubikasi.
             *
             * Dimensi ukuran menggunakan satuan mm,
             * sehingga dibagi 1.000.000.000 untuk menjadi m³.
             */
            $kubikasi = (
                $panjang *
                $lebar *
                $tebal *
                $jumlah
            ) / 1_000_000_000;

            /*
             * Grade yang digunakan oleh Stok Gudang Satu.
             */
            $kwGrade = $grade->nama_grade;

            /*
             * =====================================================
             * 1. UPDATE DATA HASIL TERIMA YANG SUDAH ADA
             * =====================================================
             *
             * Tidak membuat record baru.
             */
            $hasil = HasilTerimaGudangSatu::query()
                ->where(
                    'id_produksi_terima_gudang_satu',
                    $produksi->id
                )
                ->where(
                    'id_grade',
                    $barang->id_grade
                )
                ->where(
                    'id_jenis_barang',
                    $barang->id_jenis_barang
                )
                ->where(
                    'id_ukuran',
                    $barang->id_ukuran
                )
                ->lockForUpdate()
                ->first();

            if (! $hasil) {
                throw new \RuntimeException(
                    'Data Hasil Terima Gudang Satu yang sesuai untuk pengembalian tidak ditemukan.'
                );
            }

            /*
             * Tambahkan jumlah pengembalian ke data yang sudah ada.
             *
             * Contoh:
             * jumlah sebelumnya = 20
             * dikembalikan      = 5
             * jumlah sekarang   = 25
             */
            $hasil->update([
                'jumlah' => (float) $hasil->jumlah + $jumlah,
            ]);

            /*
             * =====================================================
             * 2. TAMBAHKAN STOK GUDANG SATU
             * =====================================================
             *
             * StokGudangSatuService::tambah()
             * sekaligus akan membuat GudangSatuLog.
             */

            $pengembali = Auth::user()?->name ?? 'Tidak diketahui';

            $keterangan = "Pengembalian sisa barang dari Sampling Plywood ke Gudang Satu oleh {$pengembali}";
            
            app(StokGudangSatuService::class)->tambah(
                idJenisKayu: (int) $jenisKayu->id,
                panjang: $panjang,
                lebar: $lebar,
                tebal: $tebal,
                kwGrade: $kwGrade,
                lembar: $jumlah,
                kubikasi: $kubikasi,
                keterangan: $keterangan,
                referensi: $hasil,
            );

            /*
             * =====================================================
             * 3. CATAT JUMLAH YANG SUDAH DIKEMBALIKAN
             * =====================================================
             */
            $serah->update([
                'jumlah_dikembalikan' =>
                    (float) $serah->jumlah_dikembalikan + $jumlah,
            ]);

            /*
             * Kembalikan data hasil yang sudah diperbarui.
             */
            return $hasil->fresh();
        });
    }

    public function kembaliDariNyusup(
        SerahTerimaGudangSatu $serah,
        float $jumlah
    ): SerahTerimaGudangSatu {
        if ($jumlah <= 0) {
            throw new \RuntimeException(
                'Jumlah pengembalian harus lebih dari 0.'
            );
        }

        return DB::transaction(function () use ($serah, $jumlah) {

            $serah = SerahTerimaGudangSatu::query()
                ->lockForUpdate()
                ->find($serah->id);

            if (! $serah) {
                throw new \RuntimeException(
                    'Data serah terima gudang satu tidak ditemukan.'
                );
            }

            $sisaSaatIni = (float) $serah->sisa;

            if ($jumlah > $sisaSaatIni) {
                throw new \RuntimeException(
                    "Jumlah melebihi sisa yang tersedia ({$sisaSaatIni})."
                );
            }

            $barang = $serah->barangSetengahJadi;

            if (
                ! $barang ||
                ! $barang->id_jenis_barang ||
                ! $barang->id_ukuran
            ) {
                throw new \RuntimeException(
                    'Data jenis barang/ukuran pada sumber ini tidak lengkap, tidak bisa dikembalikan.'
                );
            }

            $barang->loadMissing(['jenisBarang', 'ukuran', 'grade']);

            $jenisBarang = $barang->jenisBarang;
            $ukuran = $barang->ukuran;
            $grade = $barang->grade;

            if (! $jenisBarang) {
                throw new \RuntimeException('Jenis barang tidak ditemukan.');
            }
            if (! $ukuran) {
                throw new \RuntimeException('Ukuran barang tidak ditemukan.');
            }
            if (! $grade) {
                throw new \RuntimeException('Grade barang tidak ditemukan.');
            }

            $jenisKayu = JenisKayu::query()
                ->where('nama_kayu', $jenisBarang->nama_jenis_barang)
                ->first();

            if (! $jenisKayu) {
                throw new \RuntimeException(
                    "Jenis kayu dengan nama '{$jenisBarang->nama_jenis_barang}' tidak ditemukan."
                );
            }

            $panjang = (float) $ukuran->panjang;
            $lebar = (float) $ukuran->lebar;
            $tebal = (float) $ukuran->tebal;

            if ($panjang <= 0 || $lebar <= 0 || $tebal <= 0) {
                throw new \RuntimeException(
                    'Data panjang, lebar, atau tebal pada ukuran tidak valid.'
                );
            }

            $kubikasi = ($panjang * $lebar * $tebal * $jumlah) / 1_000_000_000;
            $kwGrade = $grade->nama_grade;

            $pengembali = Auth::user()?->name ?? 'Tidak diketahui';
            $keterangan = "Pengembalian sisa bahan nyusup ke Gudang Satu oleh {$pengembali}";

            // Langsung tambah ke stok Gudang Satu — Nyusup tidak
            // punya tabel "hasil terima" per-production seperti
            // Sampling Plywood, jadi tidak ada step update baris lain.
            app(StokGudangSatuService::class)->tambah(
                idJenisKayu: (int) $jenisKayu->id,
                panjang: $panjang,
                lebar: $lebar,
                tebal: $tebal,
                kwGrade: $kwGrade,
                lembar: $jumlah,
                kubikasi: $kubikasi,
                keterangan: $keterangan,
                referensi: $serah,
            );

            $serah->update([
                'jumlah_dikembalikan' =>
                    (float) $serah->jumlah_dikembalikan + $jumlah,
            ]);

            return $serah->fresh();
        });
    }
}