<?php

namespace App\Services\BkBm;

use App\Models\Bk;
use App\Models\BkDetail;
use App\Models\Bm;
use App\Models\BmDetail;
use App\Models\JenisKayu;
use App\Models\Ukuran;
use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use App\Services\HppDryerService;
use App\Services\VeneerMutasiService;
use Illuminate\Support\Facades\DB;

/**
 * Barang veneer pada BK/BM baru.
 * Perhitungan stok dan HPP memakai VeneerMutasiService yang sudah ada (tidak diubah):
 * method privatnya dipanggil lewat reflection, jadi hasilnya identik dengan menu lama.
 */
class BkBmVeneerService
{
    private function mutasiUntuk(Bk|Bm $nota, bool $buat = false): ?VeneerMutasi
    {
        $kolom = $nota instanceof Bk ? 'id_bk' : 'id_bm';
        $m = VeneerMutasi::where($kolom, $nota->id)->first();
        if (! $m && $buat) {
            $m = new VeneerMutasi();
            $m->forceFill([
                $kolom => $nota->id,
                'tipe_transaksi' => $nota instanceof Bk ? 'keluar' : 'masuk',
                'status' => 'kirim',
                'dibuat_oleh' => auth()->id(),
            ]);
        }
        if ($m) {
            $m->forceFill([
                'tanggal' => $nota->tanggal,
                'no_nota' => $nota->no_nota,
                'tujuan_nota' => $nota instanceof Bk ? $nota->tujuan_nota : ($nota->dari ?: '-'),
                'keterangan' => $nota->keterangan,
            ]);
            $m->save();
        }
        return $m;
    }

    public static function namaBarang(string $tipe, Ukuran $u, JenisKayu $k, $kw): string
    {
        return 'Veneer ' . ucfirst($tipe) . ' - ' . $u->nama_ukuran . ' - ' . $k->nama_kayu . ' - KW ' . $kw;
    }

    /** Tambah satu baris veneer ke nota BK/BM (stok belum berubah sampai validasi). */
    public function tambahItem(Bk|Bm $nota, array $d): void
    {
        DB::transaction(function () use ($nota, $d) {
            $ukuran = Ukuran::findOrFail($d['id_ukuran']);
            $kayu = JenisKayu::findOrFail($d['id_jenis_kayu']);
            $qty = (int) $d['qty'];

            $mutasi = $this->mutasiUntuk($nota, true);
            $det = $mutasi->details()->create([
                'tipe_veneer' => $d['tipe_veneer'],
                'id_ukuran' => $ukuran->id,
                'id_jenis_kayu' => $kayu->id,
                'kw' => $d['kw'],
                'qty' => $qty,
                'm3' => ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $qty) / 10000000,
                'harga' => $d['harga'] ?? null,
            ]);

            $baris = [
                'nama_barang' => self::namaBarang($d['tipe_veneer'], $ukuran, $kayu, $d['kw']),
                'jumlah' => $qty,
                'satuan' => 'Lembar',
                'keterangan' => $d['keterangan'] ?? null,
                'ref_type' => 'veneer',
                'ref_id' => $det->id,
            ];
            $nota instanceof Bk
                ? BkDetail::create($baris + ['id_bk' => $nota->id])
                : BmDetail::create($baris + ['id_bm' => $nota->id]);
        });
    }

    /** Dipanggil saat Validasi Nota. Mengubah stok veneer persis seperti nota lama. */
    public function validasi(Bk|Bm $nota): void
    {
        $mutasi = $this->mutasiUntuk($nota, false);
        if (! $mutasi) {
            return;
        }
        $details = $mutasi->details()->get();
        if ($details->isEmpty()) {
            return;
        }
        $isKeluar = $nota instanceof Bk;
        $svc = app(VeneerMutasiService::class);

        // Service lama menempelkan keterangan mutasi ke log stok, jadi jejak (pembuat, validator, jam) ditaruh di sini.
        $mutasi->forceFill(['keterangan' => BkBmJejak::tempel($nota->keterangan, $nota, 200)])->save();

        foreach ($details as $detail) {
            $ukuran = Ukuran::findOrFail($detail->id_ukuran);
            $kayu = JenisKayu::findOrFail($detail->id_jenis_kayu);
            $nama = self::namaBarang($detail->tipe_veneer, $ukuran, $kayu, $detail->kw);

            if ($detail->tipe_veneer === 'basah') {
                $this->panggil($svc, 'updateStokBasah', [$mutasi, $detail, $ukuran, $nama, $isKeluar]);
            } elseif ($detail->tipe_veneer === 'jadi') {
                $this->panggil($svc, 'updateStokJadi', [$mutasi, $detail, $ukuran, $nama, $isKeluar]);
            } else {
                $this->panggil($svc, 'updateStokKering', [$mutasi, $detail, $isKeluar, $nama]);
            }
        }

        app(HppDryerService::class)->updateLogHarian($mutasi->tanggal);
    }

    private function panggil(object $svc, string $method, array $args): void
    {
        (new \ReflectionMethod($svc, $method))->invokeArgs($svc, $args);
    }
}
