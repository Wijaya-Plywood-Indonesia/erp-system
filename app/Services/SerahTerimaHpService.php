<?php

namespace App\Services;

use App\Models\JenisKayu;
use App\Models\ProduksiSanding;
use App\Models\SerahTerimaHp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SerahTerimaHpService
{
    public function __construct(
        protected StokTriplekJadiService $stokTriplekJadi,
        protected StokPlatformMthService $stokPlatformMth,
        protected StokTriplekMthService $stokTriplekMth,
    ) {}

    /**
     * Asal bahan Sanding, atau null kalau baris ini bukan bahan Sanding.
     * Gudang Triplek Mentah tidak masuk karena hanya dikirim ke Graji.
     *
     * @return 'triplek_jadi'|'platform_mth'|'platform_hp'|'graji'|null
     */
    public static function sumberSanding(SerahTerimaHp $serahTerima): ?string
    {
        return match (true) {
            $serahTerima->id_triplek_mutasi_keluar !== null => 'triplek_jadi',
            $serahTerima->id_platform_mth_mutasi_keluar !== null => 'platform_mth',
            $serahTerima->id_platform_hasil_hp !== null => 'platform_hp',
            $serahTerima->id_hasil_graji_triplek !== null => 'graji',
            default => null,
        };
    }

    /**
     * Kembalikan sisa bahan Sanding ke gudang, APAPUN asal bahannya.
     *
     * Dipanggil saat tombol "Kembalikan ke Gudang" di tab Modal (Produksi
     * Sanding) ditekan, dengan $serahTerima = baris serah_terima_hp yang
     * dipilih dan $jumlah = berapa lembar yang dikembalikan. Alurnya sama
     * dengan kembaliDariHotpress() di Stok*JadiService:
     *
     *   1. Kunci baris serah_terima_hp (lockForUpdate) & hitung ulang sisa
     *      (qty asli - total dipakai di ModalSanding - jumlah yang sudah
     *      dikembalikan sebelumnya) supaya tidak kembali lebih dari yang
     *      tersedia, dan aman dari race condition.
     *   2. Tambah stok gudang tujuan + catat log HPP, lewat method tambah()
     *      yang sudah ada di masing-masing service. Gudang tujuannya:
     *        - Gudang Triplek Jadi     -> StokTriplekJadi (tetap)
     *        - Gudang Platform Mentah  -> StokPlatformMth
     *        - Hasil Hotpress / Graji  -> ditentukan dari KATEGORI barangnya:
     *            kategori Platform -> StokPlatformMth (Gudang Platform Mentah)
     *            kategori Plywood  -> StokTriplekMth  (Gudang Triplek Mentah)
     *      Log stok mencatat siapa yang mengembalikan (di keterangan).
     *   3. Naikkan `jumlah_dikembalikan` pada BARIS serah_terima_hp itu
     *      sendiri — bukan pada baris modal_sandings manapun, karena sisa
     *      yang belum terpakai adalah properti serah_terima_hp, bukan
     *      properti satu baris pemakaian tertentu.
     */
    public function kembaliKeGudang(SerahTerimaHp $serahTerima, float $jumlah, ?ProduksiSanding $produksiSanding = null): void
    {
        if ($jumlah <= 0) {
            throw new \RuntimeException('Jumlah pengembalian harus lebih dari 0.');
        }

        DB::transaction(function () use ($serahTerima, $jumlah, $produksiSanding) {
            $serahTerima = SerahTerimaHp::query()
                ->lockForUpdate()
                ->findOrFail($serahTerima->id);

            if ($serahTerima->isMenunggu()) {
                throw new \RuntimeException('Bahan ini belum diterima di Sanding.');
            }

            $sumber = self::sumberSanding($serahTerima);

            if ($sumber === null) {
                throw new \RuntimeException('Bahan ini bukan bahan Produksi Sanding.');
            }

            // Validasi ulang sisa DI DALAM transaksi, memakai rumus yang
            // sama dengan SerahTerimaHp::sisa.
            $sisaSaatIni = $serahTerima->sisa;

            if ($jumlah > $sisaSaatIni) {
                throw new \RuntimeException("Jumlah melebihi sisa yang tersedia ({$sisaSaatIni} lembar).");
            }

            [$idJenisKayu, $panjang, $lebar, $tebal, $kwGrade] = $this->dataBarang($serahTerima, $sumber);

            // Rumus kubikasi disamakan dengan StokVeneerJadiService/
            // StokTriplekJadiService (fitur pengembalian Hotpress).
            $kubikasi = ($panjang * $lebar * $tebal * $jumlah) / 10000000;

            // $tanggal = $produksiSanding?->tanggal
            //     ? Carbon::parse($produksiSanding->tanggal)->format('d/m/Y')
            //     : now()->format('d/m/Y');

            $gudang = $this->tentukanGudang($serahTerima, $sumber);

            $namaGudang = match ($gudang) {
                'triplek_jadi' => 'Gudang Triplek Jadi',
                'triplek_mth' => 'Gudang Triplek Mentah',
                default => 'Gudang Platform Mentah',
            };

            // Tabel log stok tidak punya kolom user, jadi nama pengembali
            // dicatat di keterangan (tampil di log stok gudang).
            $pengembali = Auth::user()?->name ?? 'Tidak diketahui';

            $keterangan = "Pengembalian sisa bahan dari Sanding ke {$namaGudang} oleh {$pengembali}";

            $service = match ($gudang) {
                'triplek_jadi' => $this->stokTriplekJadi,
                'triplek_mth' => $this->stokTriplekMth,
                default => $this->stokPlatformMth,
            };

            $service->tambah(
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

            // Baris modal_sandings TIDAK disentuh — hanya serah_terima_hp
            // yang dicatat sudah menerima pengembalian sebanyak $jumlah lembar.
            $serahTerima->update([
                'jumlah_dikembalikan' => (float) $serahTerima->jumlah_dikembalikan + $jumlah,
            ]);
        });
    }

    /**
     * Gudang tujuan pengembalian:
     *  - Gudang Triplek Jadi -> 'triplek_jadi' (tetap)
     *  - Gudang Platform Mentah -> 'platform_mth'
     *  - Hasil Hotpress / Graji -> dilihat dari KATEGORI barangnya
     *    (grade -> kategori barang): mengandung "platform" -> 'platform_mth',
     *    mengandung "plywood"/"triplek" -> 'triplek_mth'.
     */
    protected function tentukanGudang(SerahTerimaHp $serahTerima, string $sumber): string
    {
        if ($sumber === 'triplek_jadi' || $sumber === 'platform_mth') {
            return $sumber;
        }

        $kategori = (string) $serahTerima->barangSetengahJadi?->grade?->kategoriBarang?->nama_kategori;
        $kategoriLower = mb_strtolower($kategori);

        return match (true) {
            str_contains($kategoriLower, 'platform') => 'platform_mth',
            str_contains($kategoriLower, 'plywood'), str_contains($kategoriLower, 'triplek') => 'triplek_mth',
            default => throw new \RuntimeException(
                'Kategori barang "'.($kategori ?: '-').'" tidak dikenali (harus Platform atau Plywood), sehingga gudang tujuan tidak bisa ditentukan.'
            ),
        };
    }

    /**
     * Data barang yang dibutuhkan stok gudang: [id jenis kayu, panjang,
     * lebar, tebal, kw/grade].
     *  - Dari gudang (Triplek Jadi / Platform Mentah): dibaca dari mutasi keluar.
     *  - Dari hasil Hotpress / Graji: dibaca dari barang setengah jadi, dan
     *    jenis kayunya dicocokkan lewat nama (sama seperti saat bahan
     *    diterima di Sanding).
     *
     * @return array{0:int,1:float,2:float,3:float,4:string}
     */
    protected function dataBarang(SerahTerimaHp $serahTerima, string $sumber): array
    {
        if ($sumber === 'triplek_jadi' || $sumber === 'platform_mth') {
            $mutasi = $serahTerima->mutasiGudang();

            if (! $mutasi || ! (int) $mutasi->id_jenis_kayu) {
                throw new \RuntimeException('Data mutasi keluar gudang tidak lengkap.');
            }

            return [
                (int) $mutasi->id_jenis_kayu,
                (float) $mutasi->panjang,
                (float) $mutasi->lebar,
                (float) $mutasi->tebal,
                (string) $mutasi->kw_grade,
            ];
        }

        $barang = $serahTerima->barangSetengahJadi;
        $ukuran = $barang?->ukuran;
        $grade = $barang?->grade;
        $jenisBarang = $barang?->jenisBarang;

        if (! $ukuran || ! $grade || ! $jenisBarang) {
            throw new \RuntimeException('Data ukuran, grade, atau jenis barang tidak lengkap.');
        }

        $jenisKayu = JenisKayu::where('nama_kayu', $jenisBarang->nama_jenis_barang)->first();

        if (! $jenisKayu) {
            throw new \RuntimeException("Jenis kayu \"{$jenisBarang->nama_jenis_barang}\" tidak ditemukan di data Jenis Kayu. Mohon samakan penamaan atau tambahkan datanya terlebih dahulu.");
        }

        return [
            (int) $jenisKayu->id,
            (float) $ukuran->panjang,
            (float) $ukuran->lebar,
            (float) $ukuran->tebal,
            (string) $grade->nama_grade,
        ];
    }
}
