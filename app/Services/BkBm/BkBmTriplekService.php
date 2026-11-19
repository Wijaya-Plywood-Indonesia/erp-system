<?php

namespace App\Services\BkBm;

use App\Models\Bk;
use App\Models\BkbmTriplekItem;
use App\Models\BkDetail;
use App\Models\BmDetail;
use App\Models\JenisKayu;
use App\Models\StokTriplekJadi;
use App\Models\StokTriplekMth;
use App\Models\Ukuran;
use App\Models\Bm;
use App\Services\StokTriplekJadiService;
use App\Services\StokTriplekMthService;
use Illuminate\Support\Facades\DB;

/** Triplek Jadi dan Triplek Mth (setengah jadi) pada BK/BM baru. Memakai service stok lama tanpa mengubahnya. */
class BkBmTriplekService
{
    public const TIPE = [
        'triplek_jadi' => ['jenis' => 'jadi', 'label' => 'Triplek Jadi', 'stok' => StokTriplekJadi::class],
        'triplek_mth' => ['jenis' => 'mth', 'label' => 'Triplek Mth', 'stok' => StokTriplekMth::class],
    ];

    public function tambahItem(Bk|Bm $nota, string $tipe, array $d): void
    {
        $cfg = self::TIPE[$tipe] ?? throw new \RuntimeException('Jenis barang tidak dikenal.');

        DB::transaction(function () use ($nota, $tipe, $cfg, $d) {
            $u = Ukuran::findOrFail($d['id_ukuran']);
            $kayu = JenisKayu::findOrFail($d['id_jenis']);
            $qty = (int) $d['qty'];

            $item = BkbmTriplekItem::create([
                'jenis' => $cfg['jenis'],
                'id_ukuran' => $u->id,
                'id_jenis_kayu' => $kayu->id,
                'kw_grade' => $d['kw_grade'],
                'qty' => $qty,
                'm3' => ($u->panjang * $u->lebar * $u->tebal * $qty) / 10000000,
            ]);

            $baris = [
                'nama_barang' => $cfg['label'] . ' - ' . $u->nama_ukuran . ' - ' . $kayu->nama_kayu . ' - KW ' . $d['kw_grade'],
                'jumlah' => $qty,
                'satuan' => 'Lembar',
                'keterangan' => $d['keterangan'] ?? null,
                'ref_type' => $tipe,
                'ref_id' => $item->id,
            ];
            $nota instanceof Bk
                ? BkDetail::create($baris + ['id_bk' => $nota->id])
                : BmDetail::create($baris + ['id_bm' => $nota->id]);
        });
    }

    /** Dipanggil saat validasi. Keluar: stok berkurang (gagal bila kurang). Masuk: stok bertambah. */
    public function validasi(Bk|Bm $nota): void
    {
        $keluar = $nota instanceof Bk;

        foreach ($nota->detail()->whereIn('ref_type', array_keys(self::TIPE))->get() as $d) {
            $item = BkbmTriplekItem::with('ukuran')->find($d->ref_id);
            if (! $item || ! $item->ukuran) {
                throw new \RuntimeException("Data {$d->nama_barang} tidak ditemukan. Hapus barisnya lalu tambahkan lagi.");
            }
            $cfg = collect(self::TIPE)->firstWhere('jenis', $item->jenis);
            $u = $item->ukuran;

            // Ikuti orientasi panjang/lebar baris stok yang sudah ada agar tidak lahir baris ganda.
            $p = (float) $u->panjang;
            $l = (float) $u->lebar;
            $ada = $cfg['stok']::where('id_jenis_kayu', $item->id_jenis_kayu)->where('tebal', (float) $u->tebal)
                ->where('kw_grade', $item->kw_grade)
                ->where(fn ($q) => $q->where(fn ($x) => $x->where('panjang', $p)->where('lebar', $l))
                    ->orWhere(fn ($x) => $x->where('panjang', $l)->where('lebar', $p)))->first();
            if ($ada) {
                $p = (float) $ada->panjang;
                $l = (float) $ada->lebar;
            }

            $svc = $item->jenis === 'jadi' ? app(StokTriplekJadiService::class) : app(StokTriplekMthService::class);
            $ket = BkBmJejak::tempel("Nota {$nota->no_nota}" . ($d->keterangan ? " - {$d->keterangan}" : ''), $nota, 200);
            $args = [(int) $item->id_jenis_kayu, $p, $l, (float) $u->tebal, $item->kw_grade, (float) $item->qty, (float) $item->m3, $ket, $nota];

            $keluar ? $svc->kurang(...$args) : $svc->tambah(...$args);
        }
    }
}
