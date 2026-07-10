<?php

namespace App\Services;

use App\Models\GudangSatuLog;
use App\Models\StokGudangSatu;
use Illuminate\Database\Eloquent\Model;

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
