<?php

namespace App\Services;

use App\Models\HppPlywoodSiapJualLog;
use App\Models\StokPlywoodSiapJual;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlywoodMutasiService
{
    public function processStockFromNota($nota): void
    {
        $mutasi = $nota->plywoodMutasi;

        if (! $mutasi || $mutasi->status === 'posted') {
            return;
        }

        DB::transaction(function () use ($mutasi, $nota) {
            $isKeluar = $mutasi->tipe_transaksi === 'keluar';

            foreach ($mutasi->details()->with(['ukuran', 'jenisKayu'])->get() as $detail) {
                $u = $detail->ukuran;

                if (! $u) {
                    throw new \Exception("Data ukuran tidak ditemukan untuk salah satu baris plywood.");
                }

                [$stok, $key] = $this->cariAtauSiapkanStok($detail, $u);

                $lembarBefore = (int) $stok->stok_lembar;
                $m3Before     = (float) $stok->stok_kubikasi;

                if ($isKeluar && $lembarBefore < $detail->qty) {
                    throw new \Exception(
                        "Stok plywood tidak cukup untuk {$u->nama_ukuran} KW {$detail->kw_grade}. ".
                        "Tersedia {$lembarBefore} lembar, diminta {$detail->qty}."
                    );
                }

                $deltaLembar = $isKeluar ? -$detail->qty : $detail->qty;
                $deltaM3     = $isKeluar ? -$detail->m3 : $detail->m3;

                $stok->stok_lembar   = $lembarBefore + $deltaLembar;
                $stok->stok_kubikasi = $m3Before + $deltaM3;
                $stok->save();

                $log = HppPlywoodSiapJualLog::create($key + [
                    'tanggal'              => $mutasi->tanggal,
                    'tipe_transaksi'       => $isKeluar ? 'keluar' : 'masuk',
                    'keterangan'           => $this->buatKeterangan($nota, $mutasi, $detail),
                    'referensi_type'       => get_class($nota),
                    'referensi_id'         => $nota->id,
                    'total_lembar'         => abs($deltaLembar),
                    'total_kubikasi'       => abs($deltaM3),
                    'stok_lembar_before'   => $lembarBefore,
                    'stok_kubikasi_before' => $m3Before,
                    'stok_lembar_after'    => $stok->stok_lembar,
                    'stok_kubikasi_after'  => $stok->stok_kubikasi,
                ]);

                $stok->update(['id_last_log' => $log->id]);
            }

            $mutasi->update(['status' => 'posted']);
        });
    }

    /**
     * Cari baris stok tanpa peduli orientasi panjang/lebar.
     *
     * Tabel stok_plywood_siap_jual tidak konsisten: mayoritas baris menyimpan
     * sisi pendek di kolom `panjang` (122x244), sedangkan master `ukurans`
     * memakai konvensi sebaliknya (244x122). Karena itu pencarian dilakukan
     * dua arah, dan bila ketemu kita IKUTI orientasi baris yang sudah ada
     * supaya tidak lahir baris duplikat untuk barang yang sama.
     *
     * @return array{0: StokPlywoodSiapJual, 1: array}
     */
    protected function cariAtauSiapkanStok($detail, $ukuran): array
    {
        $a = (float) $ukuran->panjang;
        $b = (float) $ukuran->lebar;
        $tebal = (float) $ukuran->tebal;

        $stok = StokPlywoodSiapJual::where('id_jenis_kayu', $detail->id_jenis_kayu)
            ->where('tebal', $tebal)
            ->where('kw_grade', $detail->kw_grade)
            ->where(function ($q) use ($a, $b) {
                $q->where(fn ($s) => $s->where('panjang', $a)->where('lebar', $b))
                    ->orWhere(fn ($s) => $s->where('panjang', $b)->where('lebar', $a));
            })
            ->lockForUpdate()
            ->first();

        if ($stok) {
            $key = [
                'id_jenis_kayu' => $stok->id_jenis_kayu,
                'panjang'       => $stok->panjang,
                'lebar'         => $stok->lebar,
                'tebal'         => $stok->tebal,
                'kw_grade'      => $stok->kw_grade,
            ];

            return [$stok, $key];
        }

        // Baris baru: ikuti konvensi mayoritas tabel — sisi pendek di `panjang`
        $sisi = [$a, $b];
        sort($sisi);

        $key = [
            'id_jenis_kayu' => $detail->id_jenis_kayu,
            'panjang'       => $sisi[0],
            'lebar'         => $sisi[1],
            'tebal'         => $tebal,
            'kw_grade'      => $detail->kw_grade,
        ];

        $stok = new StokPlywoodSiapJual($key + ['stok_lembar' => 0, 'stok_kubikasi' => 0]);

        return [$stok, $key];
    }

    /**
     * Susun keterangan log: NO NOTA: x | DIVALIDASI: y | KET: z
     */
    protected function buatKeterangan($nota, $mutasi, $detail): string
    {
        $validator = $nota->divalidasi_oleh
            ? User::find($nota->divalidasi_oleh)?->name
            : auth()->user()?->name;

        $parts = [
            'NO NOTA: '.($mutasi->no_nota ?? '-'),
            'DIVALIDASI: '.($validator ?? '-'),
        ];

        $ket = $this->ambilKeteranganDetailNota($nota, $detail);

        if (! empty($ket)) {
            $parts[] = 'KET: '.$ket;
        }

        return implode(' | ', $parts);
    }

    /**
     * Ambil keterangan yang diketik user dari baris detail nota yang cocok.
     */
    protected function ambilKeteranganDetailNota($nota, $detail): ?string
    {
        $ukuran    = $detail->ukuran;
        $jenisKayu = $detail->jenisKayu;

        if (! $ukuran || ! $jenisKayu) {
            return null;
        }

        $namaBarang = 'Plywood - '.$ukuran->nama_ukuran
            .' - '.$jenisKayu->nama_kayu
            .' - KW '.$detail->kw_grade;

        return $nota->detail()
            ->where('nama_barang', $namaBarang)
            ->where('jumlah', (int) $detail->qty)
            ->value('keterangan');
    }
}