<?php

namespace App\Services;

use App\Models\HppTriplekJadiLog;
use App\Models\ProduksiHp;
use App\Models\StokTriplekJadi;
use App\Models\TriplekJadiMutasiKeluarPalet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StokTriplekJadiService
{
    /**
     * Tambah stok triplek jadi (transaksi masuk) + catat log HPP.
     * Wajib dipanggil di dalam DB::transaction() milik caller,
     * karena pakai lockForUpdate() supaya aman dari race condition.
     */
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
    ): StokTriplekJadi {
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

    /**
     * Kurangi stok triplek jadi (transaksi keluar) + catat log HPP.
     * Melempar exception kalau stok tidak cukup.
     */
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
    ): StokTriplekJadi {
        $stok = $this->lockOrCreateStok($idJenisKayu, $panjang, $lebar, $tebal, $kwGrade);

        if ($stok->stok_lembar < $lembar) {
            throw new \RuntimeException("Stok triplek jadi tidak cukup. Tersedia: {$stok->stok_lembar} lembar, diminta: {$lembar} lembar.");
        }

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

    protected function lockOrCreateStok(
        int $idJenisKayu,
        float $panjang,
        float $lebar,
        float $tebal,
        string $kwGrade,
    ): StokTriplekJadi {
        $key = [
            'id_jenis_kayu' => $idJenisKayu,
            'panjang' => $panjang,
            'lebar' => $lebar,
            'tebal' => $tebal,
            'kw_grade' => $kwGrade,
        ];

        // Cari + lock dulu. Kalau belum ada row-nya, baru create, lalu lock ulang.
        // Ini supaya baca -> ubah -> simpan atomik dan aman dari transaksi lain
        // yang berjalan bersamaan (lost update).
        $stok = StokTriplekJadi::where($key)->lockForUpdate()->first();

        if (! $stok) {
            $stok = StokTriplekJadi::create(array_merge($key, [
                'stok_lembar' => 0,
                'stok_kubikasi' => 0,
                'nilai_stok' => 0,
                'hpp_average' => 0,
                'hpp_pekerja_last' => 0,
                'hpp_bahan_penolong_last' => 0,
            ]));

            $stok = StokTriplekJadi::where('id', $stok->id)->lockForUpdate()->first();
        }

        return $stok;
    }

    protected function catatLog(
        StokTriplekJadi $stok,
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
        $log = HppTriplekJadiLog::create([
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

    /**
     * Terima kembali sisa triplek jadi dari Hotpress ke Gudang Triplek
     * Jadi. Pola & rumus sisa persis sama dengan
     * StokVeneerJadiService::kembaliDariHotpress() — lihat komentar
     * lengkap di sana.
     */
    public function kembaliDariHotpress(TriplekJadiMutasiKeluarPalet $palet, float $jumlah, ?ProduksiHp $produksiHp = null): StokTriplekJadi
    {
        if ($jumlah <= 0) {
            throw new \RuntimeException('Jumlah pengembalian harus lebih dari 0.');
        }

        return DB::transaction(function () use ($palet, $jumlah, $produksiHp) {
            $palet = TriplekJadiMutasiKeluarPalet::query()
                ->lockForUpdate()
                ->find($palet->id);

            if (! $palet) {
                throw new \RuntimeException('Palet triplek jadi tidak ditemukan.');
            }

            $mutasi = $palet->mutasiKeluar;

            if (! $mutasi) {
                throw new \RuntimeException('Data mutasi keluar triplek jadi tidak ditemukan.');
            }

            // Validasi ulang sisa DI DALAM transaksi, memakai rumus yang
            // sama dengan TriplekJadiMutasiKeluarPalet::sisa.
            $terpakai = $palet->pemakaianHotpress()->sum('isi');
            $sisaSaatIni = (float) $palet->jumlah_lembar - (float) $terpakai - (float) $palet->jumlah_dikembalikan;

            if ($jumlah > $sisaSaatIni) {
                throw new \RuntimeException("Jumlah melebihi sisa yang tersedia di palet ini ({$sisaSaatIni} lembar).");
            }

            $idJenisKayu = (int) $mutasi->id_jenis_kayu;
            $panjang = $mutasi->panjang;
            $lebar = $mutasi->lebar;
            $tebal = $mutasi->tebal;
            $kwGrade = (string) $mutasi->kw_grade;

            if (! $idJenisKayu) {
                throw new \RuntimeException('Data jenis kayu pada mutasi keluar tidak lengkap.');
            }

            $kubikasi = ((float) $panjang * (float) $lebar * (float) $tebal * $jumlah) / 10000000;

            $tanggal = $produksiHp?->tanggal
                ? Carbon::parse($produksiHp->tanggal)->format('d/m/Y')
                : now()->format('d/m/Y');

            $keterangan = "Pengembalian sisa triplek jadi dari Hotpress (Palet {$palet->nomor_palet}) - {$tanggal}";

            $stok = $this->tambah(
                idJenisKayu: $idJenisKayu,
                panjang: $panjang,
                lebar: $lebar,
                tebal: $tebal,
                kwGrade: $kwGrade,
                lembar: $jumlah,
                kubikasi: $kubikasi,
                keterangan: $keterangan,
                referensi: $palet,
            );

            // Baris bahan_hotpress TIDAK disentuh — hanya palet yang dicatat
            // sudah menerima pengembalian sebanyak $jumlah lembar.
            $palet->update([
                'jumlah_dikembalikan' => (float) $palet->jumlah_dikembalikan + $jumlah,
            ]);

            return $stok;
        });
    }
}
