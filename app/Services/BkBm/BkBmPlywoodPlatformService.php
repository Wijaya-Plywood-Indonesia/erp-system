<?php

namespace App\Services\BkBm;

use App\Models\Bk;
use App\Models\BkDetail;
use App\Models\Bm;
use App\Models\BmDetail;
use App\Models\JenisBarang;
use App\Models\JenisKayu;
use App\Models\PlatformJadiMutasi;
use App\Models\PlatformJadiMutasiDetail;
use App\Models\PlatformMthMutasi;
use App\Models\PlatformMthMutasiDetail;
use App\Models\PlywoodMutasi;
use App\Models\PlywoodMutasiDetail;
use App\Models\Ukuran;
use App\Services\PlatformJadiMutasiService;
use App\Services\PlatformMthMutasiService;
use App\Services\PlywoodMutasiService;
use Illuminate\Support\Facades\DB;

/**
 * Plywood, Platform Jadi, Platform MTH pada BK/BM baru.
 * Stok dan HPP dihitung oleh service lama (processStockFromNota) tanpa diubah:
 * service itu hanya butuh $nota->plywoodMutasi / platformJadiMutasi / platformMthMutasi,
 * $nota->detail(), id nota, dan divalidasi_oleh, yang semuanya tersedia di Bk/Bm.
 */
class BkBmPlywoodPlatformService
{
    public const TIPE = [
        'plywood' => ['label' => 'Plywood', 'mutasi' => PlywoodMutasi::class, 'detail' => PlywoodMutasiDetail::class, 'fk' => 'id_plywood_mutasi', 'prefix' => 'Plywood', 'jenis' => 'kayu'],
        'platform_jadi' => ['label' => 'Platform Jadi', 'mutasi' => PlatformJadiMutasi::class, 'detail' => PlatformJadiMutasiDetail::class, 'fk' => 'id_platform_jadi_mutasi', 'prefix' => 'Platform Jadi', 'jenis' => 'barang'],
        'platform_mth' => ['label' => 'Platform MTH', 'mutasi' => PlatformMthMutasi::class, 'detail' => PlatformMthMutasiDetail::class, 'fk' => 'id_platform_mth_mutasi', 'prefix' => 'Platform Mth', 'jenis' => 'kayu'],
    ];

    private function mutasiUntuk(string $tipe, Bk|Bm $nota)
    {
        $cfg = self::TIPE[$tipe];
        $kolom = $nota instanceof Bk ? 'id_bk' : 'id_bm';
        $m = $cfg['mutasi']::where($kolom, $nota->id)->first();
        if (! $m) {
            $m = new $cfg['mutasi']();
            $m->forceFill([
                $kolom => $nota->id,
                'tipe_transaksi' => $nota instanceof Bk ? 'keluar' : 'masuk',
                'status' => 'draft',
                'dibuat_oleh' => auth()->id(),
            ]);
        }
        $m->forceFill([
            'tanggal' => $nota->tanggal,
            'no_nota' => $nota->no_nota,
            'tujuan_nota' => $nota instanceof Bk ? $nota->tujuan_nota : ($nota->dari ?: '-'),
            'keterangan' => $nota->keterangan,
        ]);
        $m->save();
        return $m;
    }

    public function tambahItem(Bk|Bm $nota, string $tipe, array $d): void
    {
        $cfg = self::TIPE[$tipe] ?? throw new \RuntimeException('Jenis barang tidak dikenal.');

        DB::transaction(function () use ($nota, $tipe, $cfg, $d) {
            $ukuran = Ukuran::findOrFail($d['id_ukuran']);
            $qty = (int) $d['qty'];
            $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $qty) / 10000000;

            $mutasi = $this->mutasiUntuk($tipe, $nota);
            $isBarang = $cfg['jenis'] === 'barang';

            if ($isBarang) {
                $jenis = JenisBarang::findOrFail($d['id_jenis']);
                $namaJenis = $jenis->nama_jenis_barang;
                $kolomJenis = 'id_jenis_barang';
            } else {
                $jenis = JenisKayu::findOrFail($d['id_jenis']);
                $namaJenis = $jenis->nama_kayu;
                $kolomJenis = 'id_jenis_kayu';
            }

            $det = $cfg['detail']::create([
                $cfg['fk'] => $mutasi->id,
                'id_ukuran' => $ukuran->id,
                $kolomJenis => $jenis->id,
                'kw_grade' => $d['kw_grade'],
                'qty' => $qty,
                'm3' => $m3,
            ] + ($tipe === 'plywood' ? ['harga' => $d['harga'] ?? null] : []));

            // Format nama persis seperti yang dicari service lama untuk mengambil keterangan.
            $baris = [
                'nama_barang' => $cfg['prefix'] . ' - ' . $ukuran->nama_ukuran . ' - ' . $namaJenis . ' - KW ' . $d['kw_grade'],
                'jumlah' => $qty,
                'satuan' => 'Lembar',
                'keterangan' => $d['keterangan'] ?? null,
                'ref_type' => $tipe,
                'ref_id' => $det->id,
            ];
            $nota instanceof Bk
                ? BkDetail::create($baris + ['id_bk' => $nota->id])
                : BmDetail::create($baris + ['id_bm' => $nota->id]);
        });
    }

    /** Validasi: panggil service stok lama untuk tiap jenis yang ada di nota ini. */
    public function validasi(Bk|Bm $nota): void
    {
        // Service lama membaca nama validator dari $nota->divalidasi_oleh.
        $nota->divalidasi_oleh = auth()->id();

        $nota->unsetRelation('plywoodMutasi')->unsetRelation('platformJadiMutasi')->unsetRelation('platformMthMutasi');

        app(PlywoodMutasiService::class)->processStockFromNota($nota);
        app(PlatformJadiMutasiService::class)->processStockFromNota($nota);
        app(PlatformMthMutasiService::class)->processStockFromNota($nota);

        // Lengkapi keterangan log stok: siapa membuat, siapa memvalidasi, jam berapa.
        foreach ([\App\Models\HppPlywoodSiapJualLog::class, \App\Models\HppPlatformJadiLog::class, \App\Models\HppPlatformMthLog::class] as $model) {
            $model::where('referensi_type', get_class($nota))->where('referensi_id', $nota->id)->get()
                ->each(function ($log) use ($nota) {
                    $log->keterangan = BkBmJejak::tempel($log->keterangan, $nota);
                    $log->save();
                });
        }
    }
}
