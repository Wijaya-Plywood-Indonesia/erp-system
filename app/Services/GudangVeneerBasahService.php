<?php

namespace App\Services;

use App\Models\DetailHasilPaletRotary;
use App\Models\HppVeneerBasahSummary;
use App\Models\SerahTerimaVeneerBasah;
use App\Models\Ukuran;
use App\Models\VeneerBasahMutasi;
use App\Services\Akuntansi\RotaryJurnalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GudangVeneerBasahService
{
    public function __construct(
        protected RotaryJurnalService $rotaryJurnalService
    ) {}

    public function terimaDariRotary(int $idPivotRotary, ?string $userGudang = null): void
    {
        $userGudang = $userGudang ?? Auth::user()->name;

        DB::transaction(function () use ($idPivotRotary, $userGudang) {
            $pivotRow = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                ->where('id', $idPivotRotary)
                ->where('tipe', 'rotary')
                ->lockForUpdate()
                ->first();

            if (! $pivotRow) {
                throw new RuntimeException('Data serah rotary tidak ditemukan.');
            }

            if ($pivotRow->diterima_oleh !== '-') {
                throw new RuntimeException('Palet ini sudah diterima gudang sebelumnya.');
            }

            $palet = DetailHasilPaletRotary::find($pivotRow->id_detail_hasil_palet_rotary);
            if (! $palet) {
                throw new RuntimeException('Data palet rotary tidak ditemukan.');
            }

            $userSerah = $pivotRow->diserahkan_oleh;
            $diterimaOleh = "{$userGudang} - Gudang Veneer Basah";

            DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                ->where('id', $pivotRow->id)
                ->update([
                    'diterima_oleh' => $diterimaOleh,
                    'status' => 'Terima Barang',
                    'updated_at' => now(),
                ]);

            DB::table('detail_hasil_palet_rotary_serah_terima_pivot')->insert([
                'id_detail_hasil_palet_rotary' => $palet->id,
                'diserahkan_oleh' => $userSerah,
                'diterima_oleh' => $diterimaOleh,
                'tipe' => 'gudang_veneer_basah',
                'status' => 'Terima Barang',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $keterangan = "SERAH-TERIMA: {$palet->kode_palet} | ROTARY -> GUDANG VENEER BASAH | Oleh: {$userSerah} -> {$userGudang}";
            $this->rotaryJurnalService->serahPalet($palet, $keterangan);
        });
    }

    /**
     * @param  array<int, array{id_jenis_kayu:int,id_ukuran:int,kw:string,qty_lembar:int,no_palet?:int}>  $items
     *
     * Tiap entri di $items merepresentasikan SATU PALET fisik (bukan gabungan
     * total). Ini penting supaya sisi produksi (Dryer/Kedi) menerima per-palet,
     * bukan satu baris besar gabungan — lihat catatan di GudangVeneerBasah::prosesKeluar().
     *
     * Target produksi (Press Dryer / Kedi tertentu) TIDAK dipilih di sini —
     * barang keluar masuk ke pool bersama tujuan (dryer/kedi), baru terikat
     * ke sesi produksi tertentu saat pihak penerima menekan "Terima".
     */
    public function buatMutasiKeluar(
        array $items,
        string $tujuan, // 'dryer' | 'kedi'
        ?string $keterangan = null,
        ?string $dibuatOleh = null,
    ): VeneerBasahMutasi {
        if (! in_array($tujuan, ['dryer', 'kedi'], true)) {
            throw new RuntimeException('Tujuan mutasi tidak valid.');
        }

        $dibuatOleh = $dibuatOleh ?? Auth::user()->name;

        return DB::transaction(function () use ($items, $tujuan, $keterangan, $dibuatOleh) {
            $mutasi = VeneerBasahMutasi::create([
                'tanggal' => Carbon::today(),
                'tujuan' => $tujuan,
                'keterangan' => $keterangan,
                'dibuat_oleh' => $dibuatOleh,
            ]);

            // Beberapa palet dalam satu transaksi bisa memakai kombinasi
            // jenis/ukuran/kw yang sama — validasi stok harus dihitung
            // KUMULATIF per kombinasi, bukan per-item, supaya total semua
            // palet tidak diam-diam melebihi stok yang benar-benar tersedia.
            $terpakai = [];

            foreach ($items as $item) {
                $ukuran = Ukuran::findOrFail($item['id_ukuran']);

                $summary = HppVeneerBasahSummary::where([
                    'id_jenis_kayu' => $item['id_jenis_kayu'],
                    'panjang' => $ukuran->panjang,
                    'lebar' => $ukuran->lebar,
                    'tebal' => $ukuran->tebal,
                    'kw' => $item['kw'],
                ])->first();

                $stokTersedia = (int) ($summary->stok_lembar ?? 0);

                $kunciKombinasi = "{$item['id_jenis_kayu']}|{$item['id_ukuran']}|{$item['kw']}";
                $sudahTerpakai = $terpakai[$kunciKombinasi] ?? 0;
                $totalDiminta = $sudahTerpakai + (int) $item['qty_lembar'];

                if ($stokTersedia < $totalDiminta) {
                    throw new RuntimeException(
                        "Stok tidak cukup untuk kombinasi {$ukuran->panjang}x{$ukuran->lebar}x{$ukuran->tebal} KW {$item['kw']}. ".
                        "Tersedia: {$stokTersedia} lembar, total diminta: {$totalDiminta} lembar."
                    );
                }

                $terpakai[$kunciKombinasi] = $totalDiminta;

                $m3 = round(
                    ((float) $ukuran->panjang * (float) $ukuran->lebar * (float) $ukuran->tebal * (int) $item['qty_lembar']) / 10_000_000,
                    6
                );

                $detail = $mutasi->details()->create([
                    'id_ukuran' => $item['id_ukuran'],
                    'id_jenis_kayu' => $item['id_jenis_kayu'],
                    'kw' => $item['kw'],
                    'qty_lembar' => $item['qty_lembar'],
                    'm3' => $m3,
                    'no_palet' => $item['no_palet'] ?? null,
                ]);

                SerahTerimaVeneerBasah::create([
                    'id_veneer_basah_mutasi_detail' => $detail->id,
                    'tujuan' => $tujuan,
                    'id_produksi_dryer' => null,
                    'id_produksi_kedi' => null,
                    'diserahkan_oleh' => $dibuatOleh,
                    'diterima_oleh' => '-',
                    'status' => 'Menunggu',
                ]);
            }

            return $mutasi;
        });
    }

    /**
     * Ubah rincian per-palet dari sebuah mutasi keluar yang SUDAH dibuat,
     * selama belum ada satu pun palet-nya yang diterima ATAU ditolak di
     * sisi produksi. Pola sama seperti VeneerKeringMutasiKeluar::updateKeluar() —
     * detail lama + antrean SerahTerima-nya dihapus, lalu dibuat ulang.
     *
     * @param  array<int, array{qty_lembar:int,no_palet?:int}>  $paletBaru
     */
    public function updateMutasiKeluar(VeneerBasahMutasi $mutasi, array $paletBaru): void
    {
        DB::transaction(function () use ($mutasi, $paletBaru) {
            $mutasi = VeneerBasahMutasi::with(['details.serahTerima'])
                ->where('id', $mutasi->id)
                ->lockForUpdate()
                ->firstOrFail();

            $detailPertama = $mutasi->details->first();
            if (! $detailPertama) {
                throw new RuntimeException('Mutasi ini tidak memiliki rincian untuk diubah.');
            }

            // ✅ FIX: sebelumnya hanya mengecek diterima_oleh !== '-', sehingga
            // palet yang statusnya "Ditolak" (diterima_oleh masih '-') masih
            // bisa diedit ulang. Sekarang begitu status SUDAH DIPROSES apa pun
            // (Diterima ATAUPUN Ditolak), rincian dikunci — harus buat baru.
            $sudahAdaYangDiterima = $mutasi->details->contains(
                fn ($d) => $d->serahTerima && $d->serahTerima->status !== 'Menunggu'
            );

            if ($sudahAdaYangDiterima) {
                throw new RuntimeException('Sebagian atau seluruh palet pada mutasi ini sudah diterima/ditolak di sisi produksi, rincian tidak bisa diubah lagi. Silakan buat catatan barang keluar baru.');
            }

            $ukuran = Ukuran::findOrFail($detailPertama->id_ukuran);

            $summary = HppVeneerBasahSummary::where([
                'id_jenis_kayu' => $detailPertama->id_jenis_kayu,
                'panjang' => $ukuran->panjang,
                'lebar' => $ukuran->lebar,
                'tebal' => $ukuran->tebal,
                'kw' => $detailPertama->kw,
            ])->first();

            $stokTersedia = (int) ($summary->stok_lembar ?? 0);
            $totalBaru = array_sum(array_map(fn ($p) => (int) $p['qty_lembar'], $paletBaru));

            if ($stokTersedia < $totalBaru) {
                throw new RuntimeException("Stok tidak cukup. Tersedia: {$stokTersedia} lembar, diminta: {$totalBaru} lembar.");
            }

            $dibuatOleh = $mutasi->dibuat_oleh;
            $tujuan = $mutasi->tujuan;

            // Hapus dulu antrean SerahTerima yang menempel, baru detail-nya.
            foreach ($mutasi->details as $detailLama) {
                $detailLama->serahTerima()->delete();
            }
            $mutasi->details()->delete();

            foreach ($paletBaru as $index => $p) {
                $qty = (int) $p['qty_lembar'];

                $m3 = round(
                    ((float) $ukuran->panjang * (float) $ukuran->lebar * (float) $ukuran->tebal * $qty) / 10_000_000,
                    6
                );

                $detail = $mutasi->details()->create([
                    'id_ukuran' => $detailPertama->id_ukuran,
                    'id_jenis_kayu' => $detailPertama->id_jenis_kayu,
                    'kw' => $detailPertama->kw,
                    'qty_lembar' => $qty,
                    'm3' => $m3,
                    'no_palet' => $p['no_palet'] ?? $index + 1,
                ]);

                if ($qty <= 0) {
                    continue;
                }

                SerahTerimaVeneerBasah::create([
                    'id_veneer_basah_mutasi_detail' => $detail->id,
                    'tujuan' => $tujuan,
                    'id_produksi_dryer' => null,
                    'id_produksi_kedi' => null,
                    'diserahkan_oleh' => $dibuatOleh,
                    'diterima_oleh' => '-',
                    'status' => 'Menunggu',
                ]);
            }
        });
    }

    /**
     * Dipanggil saat Dryer/Kedi menekan "Terima". $idProduksiDryer /
     * $idProduksiKedi WAJIB diisi sesuai sesi produksi yang sedang
     * menerima — inilah yang mengikat barang ke sesi tsb sehingga
     * muncul di dropdown "Modal" produksi tersebut.
     *
     * REVISI: langkah ini SAMA SEKALI TIDAK menyentuh stok maupun log
     * (HppVeneerBasahLog). "Terima" cuma mengikat baris ke sesi produksi
     * + mencatat siapa & kapan menerima. Stok baru benar-benar dipotong
     * (dan baru saat itu log dibuat) nanti saat produksi tsb DIVALIDASI —
     * lihat ProductionValidationObserver.
     */
    public function terima(
        SerahTerimaVeneerBasah $row,
        ?int $idProduksiDryer = null,
        ?int $idProduksiKedi = null,
        ?string $userTerima = null,
    ): void {
        $userTerima = $userTerima ?? Auth::user()->name;

        if (! $row->isMenunggu()) {
            throw new RuntimeException('Baris ini sudah diproses sebelumnya.');
        }

        if ($row->tujuan === 'dryer' && ! $idProduksiDryer) {
            throw new RuntimeException('Sesi Produksi Press Dryer penerima wajib diketahui.');
        }

        if ($row->tujuan === 'kedi' && ! $idProduksiKedi) {
            throw new RuntimeException('Sesi Produksi Kedi penerima wajib diketahui.');
        }

        $row->update([
            'id_produksi_dryer' => $idProduksiDryer,
            'id_produksi_kedi' => $idProduksiKedi,
            'diterima_oleh' => $userTerima,
            'status' => 'Diterima',
        ]);
    }

    public function tolak(SerahTerimaVeneerBasah $row, string $alasan, ?string $userTolak = null): void
    {
        $userTolak = $userTolak ?? Auth::user()->name;

        if (! $row->isMenunggu()) {
            throw new RuntimeException('Baris ini sudah diproses sebelumnya.');
        }

        $row->update([
            'ditolak_oleh' => $userTolak,
            'alasan_tolak' => $alasan,
            'ditolak_at' => now(),
            'status' => 'Ditolak',
        ]);
    }
}