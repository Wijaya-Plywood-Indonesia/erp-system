<?php

namespace App\Services;

use App\Models\HppPlywoodSiapJualLog;
use App\Models\StokPlywoodSiapJual;
use Illuminate\Database\Eloquent\Model;

class StokPlywoodSiapJualService
{
    /**
     * Tambah stok plywood siap jual (transaksi masuk) + catat log.
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
        string $keterangan,
        ?Model $referensi = null,
    ): StokPlywoodSiapJual {
        $stok = $this->lockOrCreateStok($idJenisKayu, $panjang, $lebar, $tebal, $kwGrade);

        $kubikasi = $this->hitungKubikasi($panjang, $lebar, $tebal, $lembar);

        $stokLembarBefore = $stok->stok_lembar;
        $stokKubikasiBefore = $stok->stok_kubikasi;

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
            stokLembarBefore: $stokLembarBefore,
            stokKubikasiBefore: $stokKubikasiBefore,
        );

        return $stok->fresh();
    }

    /**
     * Kurangi stok plywood siap jual (transaksi keluar) + catat log.
     * Melempar exception kalau stok tidak cukup.
     */
    public function kurang(
        int $idJenisKayu,
        float $panjang,
        float $lebar,
        float $tebal,
        string $kwGrade,
        float $lembar,
        string $keterangan,
        ?Model $referensi = null,
    ): StokPlywoodSiapJual {
        $stok = $this->lockOrCreateStok($idJenisKayu, $panjang, $lebar, $tebal, $kwGrade);

        if ($stok->stok_lembar < $lembar) {
            throw new \RuntimeException("Stok plywood siap jual tidak cukup. Tersedia: {$stok->stok_lembar} lembar, diminta: {$lembar} lembar.");
        }

        $kubikasi = $this->hitungKubikasi($panjang, $lebar, $tebal, $lembar);

        $stokLembarBefore = $stok->stok_lembar;
        $stokKubikasiBefore = $stok->stok_kubikasi;

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
            stokLembarBefore: $stokLembarBefore,
            stokKubikasiBefore: $stokKubikasiBefore,
        );

        return $stok->fresh();
    }

    /**
     * Hitung kubikasi (m³) dari dimensi (cm) x jumlah lembar.
     * Rumus: panjang x lebar x tebal x lembar / 10.000.000
     */
    protected function hitungKubikasi(float $panjang, float $lebar, float $tebal, float $lembar): float
    {
        return ($panjang * $lebar * $tebal * $lembar) / 10000000;
    }

    protected function lockOrCreateStok(
        int $idJenisKayu,
        float $panjang,
        float $lebar,
        float $tebal,
        string $kwGrade,
    ): StokPlywoodSiapJual {
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
        $stok = StokPlywoodSiapJual::where($key)->lockForUpdate()->first();

        if (! $stok) {
            $stok = StokPlywoodSiapJual::create(array_merge($key, [
                'stok_lembar' => 0,
                'stok_kubikasi' => 0,
            ]));

            $stok = StokPlywoodSiapJual::where('id', $stok->id)->lockForUpdate()->first();
        }

        return $stok;
    }

    protected function catatLog(
        StokPlywoodSiapJual $stok,
        string $tipeTransaksi,
        string $keterangan,
        ?Model $referensi,
        float $lembar,
        float $kubikasi,
        float $stokLembarBefore,
        float $stokKubikasiBefore,
    ): void {
        $log = HppPlywoodSiapJualLog::create([
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
            'stok_lembar_before' => $stokLembarBefore,
            'stok_kubikasi_before' => $stokKubikasiBefore,
            'stok_lembar_after' => $stok->stok_lembar,
            'stok_kubikasi_after' => $stok->stok_kubikasi,
        ]);

        $stok->update(['id_last_log' => $log->id]);
    }
}
