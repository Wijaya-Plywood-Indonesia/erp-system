<?php

namespace App\Services;

use App\Models\GudangSatuLog;
use App\Models\StokGudangSatu;
use App\Models\JenisKayu; 
use App\Models\SerahTerimaGudangSatu;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;


class StokGudangSatuService
{
    public function tambah(
        int $idJenisKayu,
        float $panjang,
        float $lebar,
        float $tebal,
        string $kwGrade,
        float $lembar,
        float $kubikasi,
        string $keterangan,
        ?Model $referensi = null,
        float $hppPekerja = 0,
        float $hppBahanPenolong = 0,
    ): StokGudangSatu {
        $stok = $this->lockOrCreateStok($idJenisKayu, $panjang, $lebar, $tebal, $kwGrade);

        $stokLembarBefore = $stok->stok_lembar;
        $stokKubikasiBefore = $stok->stok_kubikasi;
        $nilaiStokBefore = $stok->nilai_stok;

        $stok->stok_lembar += $lembar;
        $stok->stok_kubikasi += $kubikasi;
        $stok->save();

        $this->catatLog(
            stok: $stok,
            tipeTransaksi: 'masuk',
            keterangan: $keterangan,
            referensi: $referensi,
            lembar: $lembar,
            kubikasi: $kubikasi,
            hppPekerja: $hppPekerja,
            hppBahanPenolong: $hppBahanPenolong,
            stokLembarBefore: $stokLembarBefore,
            stokKubikasiBefore: $stokKubikasiBefore,
            nilaiStokBefore: $nilaiStokBefore,
        );

        return $stok->fresh();
    }

    public function kurang(
        int $idJenisKayu,
        float $panjang,
        float $lebar,
        float $tebal,
        string $kwGrade,
        float $lembar,
        float $kubikasi,
        string $keterangan,
        ?Model $referensi = null,
    ): StokGudangSatu {
        $stok = $this->lockOrCreateStok($idJenisKayu, $panjang, $lebar, $tebal, $kwGrade);

        // matikan validasi ini kalau minus biarkan aja minus
        // if ($stok->stok_lembar < $lembar) {
        //     throw new \RuntimeException("Stok gudang satu tidak cukup. Tersedia: {$stok->stok_lembar} lembar, diminta: {$lembar} lembar.");
        // }

        $stokLembarBefore = $stok->stok_lembar;
        $stokKubikasiBefore = $stok->stok_kubikasi;
        $nilaiStokBefore = $stok->nilai_stok;

        $stok->stok_lembar -= $lembar;
        $stok->stok_kubikasi -= $kubikasi;
        $stok->save();

        $this->catatLog(
            stok: $stok,
            tipeTransaksi: 'keluar',
            keterangan: $keterangan,
            referensi: $referensi,
            lembar: $lembar,
            kubikasi: $kubikasi,
            hppPekerja: 0,
            hppBahanPenolong: 0,
            stokLembarBefore: $stokLembarBefore,
            stokKubikasiBefore: $stokKubikasiBefore,
            nilaiStokBefore: $nilaiStokBefore,
        );

        return $stok->fresh();
    }

        /**
     * Terima kembali sisa bahan dari Nyusup ke Gudang Satu.
     *
     * Dipanggil saat tombol "Kembalikan Sisa" di tab Detail Barang
     * Dikerjakan (Produksi Nyusup) ditekan, dengan $serahTerima = palet
     * modal (SerahTerimaGudangSatu) yang dipilih dan $jumlah = berapa
     * lembar yang dikembalikan. Method ini bertanggung jawab penuh atas
     * SATU transaksi pengembalian:
     *   1. Kunci baris palet (lockForUpdate) & validasi ulang sisa
     *      (memakai rumus yang sama dengan SerahTerimaGudangSatu::sisa)
     *      supaya tidak kembali lebih dari yang tersedia, dan aman dari
     *      race condition kalau ada 2 orang input barengan.
     *   2. Tambah StokGudangSatu + catat GudangSatuLog lewat tambah().
     *   3. Naikkan `jumlah_dikembalikan` pada PALET itu sendiri — bukan
     *      pada baris DetailBarangDikerjakan manapun, karena sisa yang
     *      belum dipakai adalah properti palet, bukan properti 1 baris
     *      pemakaian tertentu.
     */
    public function kembaliDariNyusup(SerahTerimaGudangSatu $serahTerima, float $jumlah): StokGudangSatu
    {
        if ($jumlah <= 0) {
            throw new \RuntimeException('Jumlah pengembalian harus lebih dari 0.');
        }

        return DB::transaction(function () use ($serahTerima, $jumlah) {
            $serahTerima = SerahTerimaGudangSatu::query()
                ->lockForUpdate()
                ->find($serahTerima->id);

            if (! $serahTerima) {
                throw new \RuntimeException('Data serah terima tidak ditemukan.');
            }

            // Validasi ulang sisa DI DALAM transaksi (bukan cuma di form),
            // memakai rumus yang sama dengan SerahTerimaGudangSatu::sisa.
            $sisaSaatIni = $serahTerima->sisa;

            if ($jumlah > $sisaSaatIni) {
                throw new \RuntimeException("Jumlah melebihi sisa yang tersedia di palet ini ({$sisaSaatIni} lembar).");
            }

            $b = $serahTerima->barangSetengahJadi;

            if (! $b) {
                throw new \RuntimeException('Data barang setengah jadi tidak ditemukan pada palet ini.');
            }

            $panjang = $b->ukuran?->panjang ?? ($b->panjang ?? 0);
            $lebar = $b->ukuran?->lebar ?? ($b->lebar ?? 0);
            $tebal = $b->ukuran?->tebal ?? ($b->tebal ?? 0);
            $kwGrade = $b->grade?->nama_grade ?? ($b->kw_grade ?? '-');
            $namaJenisBarang = $b->jenisBarang?->nama_jenis_barang ?? ($b->jenisKayu?->nama_kayu ?? null);

            $idJenisKayu = JenisKayu::where('nama_kayu', $namaJenisBarang)->value('id');

            if (! $idJenisKayu) {
                throw new \RuntimeException("Jenis kayu \"{$namaJenisBarang}\" tidak ditemukan di master jenis kayu.");
            }

            $kubikasi = ((float) $panjang * (float) $lebar * (float) $tebal * $jumlah) / 10_000_000_000;

            $noPalet = $serahTerima->hasilNyusup?->no_palet;
            $keterangan = $noPalet
                ? "Pengembalian sisa bahan dari Nyusup (Palet {$noPalet}) - ".now()->format('d/m/Y')
                : 'Pengembalian sisa bahan dari Nyusup - '.now()->format('d/m/Y');

            $stok = $this->tambah(
                idJenisKayu: $idJenisKayu,
                panjang: $panjang,
                lebar: $lebar,
                tebal: $tebal,
                kwGrade: $kwGrade,
                lembar: $jumlah,
                kubikasi: $kubikasi,
                keterangan: $keterangan,
                referensi: $serahTerima,
            );

            // Baris DetailBarangDikerjakan TIDAK disentuh — hanya palet
            // yang dicatat sudah menerima pengembalian sebanyak $jumlah lembar.
            $serahTerima->update([
                'jumlah_dikembalikan' => (float) $serahTerima->jumlah_dikembalikan + $jumlah,
            ]);

            return $stok;
        });
    }

    protected function lockOrCreateStok(
        int $idJenisKayu,
        float $panjang,
        float $lebar,
        float $tebal,
        string $kwGrade,
    ): StokGudangSatu {
        $key = [
            'id_jenis_kayu' => $idJenisKayu,
            'panjang' => $panjang,
            'lebar' => $lebar,
            'tebal' => $tebal,
            'kw_grade' => $kwGrade,
        ];

        $stok = StokGudangSatu::where($key)->lockForUpdate()->first();

        if (! $stok) {
            $stok = StokGudangSatu::create(array_merge($key, [
                'stok_lembar' => 0,
                'stok_kubikasi' => 0,
                'nilai_stok' => 0,
                'hpp_average' => 0,
                'hpp_pekerja_last' => 0,
                'hpp_bahan_penolong_last' => 0,
            ]));

            $stok = StokGudangSatu::where('id', $stok->id)->lockForUpdate()->first();
        }

        return $stok;
    }

    protected function catatLog(
        StokGudangSatu $stok,
        string $tipeTransaksi,
        string $keterangan,
        ?Model $referensi,
        float $lembar,
        float $kubikasi,
        float $hppPekerja,
        float $hppBahanPenolong,
        float $stokLembarBefore,
        float $stokKubikasiBefore,
        float $nilaiStokBefore,
    ): void {
        $log = GudangSatuLog::create([
            'id_jenis_kayu' => $stok->id_jenis_kayu,
            'panjang' => $stok->panjang,
            'lebar' => $stok->lebar,
            'tebal' => $stok->tebal,
            'kw_grade' => $stok->kw_grade,
            'tanggal' => now()->toDateString(),
            'tipe_transaksi' => $tipeTransaksi,
            'keterangan' => $keterangan,
            'referensi_type' => $referensi ? get_class($referensi) : null,
            'referensi_id' => $referensi?->id,
            'total_lembar' => $lembar,
            'total_kubikasi' => $kubikasi,
            'hpp_pekerja' => $hppPekerja,
            'hpp_bahan_penolong' => $hppBahanPenolong,
            'hpp_average' => $stok->hpp_average,
            'nilai_stok' => $stok->nilai_stok,
            'stok_lembar_before' => $stokLembarBefore,
            'stok_kubikasi_before' => $stokKubikasiBefore,
            'nilai_stok_before' => $nilaiStokBefore,
            'stok_lembar_after' => $stok->stok_lembar,
            'stok_kubikasi_after' => $stok->stok_kubikasi,
            'nilai_stok_after' => $stok->nilai_stok,
        ]);

        $stok->update(['id_last_log' => $log->id]);
    }
}
