<?php

namespace App\Filament\Pages;

use App\Models\GudangVeneerKering as GudangVeneerKeringModel;
use App\Models\StokVeneerKering;
use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use App\Services\SerahTerimaVeneerKeringService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class GudangVeneerKering extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.gudang-veneer-kering';

    protected static ?string $navigationLabel = 'Gudang Veneer Kering';
    protected static string|UnitEnum|null $navigationGroup = 'Gudang';
    protected static ?string $title          = 'Gudang Veneer Kering';
    protected static ?int    $navigationSort = 21;

    // Search bebas: cocok ke ukuran (p x l x t), KW, atau nama jenis kayu
    public string $search = '';

    /**
     * Ringkasan stok per kombinasi produk (id_ukuran + id_jenis_kayu + kw).
     *
     * PENTING: logika ini dibuat IDENTIK dengan halaman "Stok Veneer Kering"
     * supaya angkanya sama persis:
     *   - baris terkini per grup diambil via MAX(id),
     *   - baris dianggap masih ada stok bila stok_m3_sesudah > 0
     *     (BUKAN stok_lembar_sesudah — kolom itu bisa 0 di baris ber-id
     *      terbesar meskipun saldo sebenarnya masih ada),
     *   - jumlah lembar dihitung ulang = SUM(masuk) - SUM(keluar) => total_lembar.
     */
    public function getSummariesProperty()
    {
        $rows = StokVeneerKering::query()
            ->with(['ukuran', 'jenisKayu'])
            ->select('stok_veneer_kerings.*')
            ->join(
                DB::raw('(SELECT MAX(id) as max_id FROM stok_veneer_kerings GROUP BY id_ukuran, id_jenis_kayu, kw) as latest'),
                fn ($join) => $join->on('stok_veneer_kerings.id', '=', 'latest.max_id')
            )
            ->where('stok_m3_sesudah', '>', 0)
            ->get();

        // Saldo lembar akurat (masuk - keluar), sama seperti halaman Stok.
        $rows = $rows->map(function (StokVeneerKering $row) {
            $row->total_lembar = StokVeneerKering::saldoLembarTerakhir(
                (int) $row->id_ukuran,
                (int) $row->id_jenis_kayu,
                (string) $row->kw
            );

            return $row;
        });

        if (trim($this->search) !== '') {
            $needle = strtolower(trim($this->search));

            $rows = $rows->filter(function (StokVeneerKering $row) use ($needle) {
                $dimensi = strtolower(
                    rtrim(rtrim(number_format((float) $row->ukuran?->panjang, 2), '0'), '.') . 'x' .
                    rtrim(rtrim(number_format((float) $row->ukuran?->lebar, 2), '0'), '.') . 'x' .
                    rtrim(rtrim(number_format((float) $row->ukuran?->tebal, 2), '0'), '.')
                );
                $kw   = strtolower((string) $row->kw);
                $kayu = strtolower((string) $row->jenisKayu?->nama_kayu);

                return str_contains($dimensi, $needle)
                    || str_contains($kw, $needle)
                    || str_contains($kayu, $needle);
            });
        }

        return $rows;
    }

    /**
     * Face/Back: tebal <= 1mm (konvensi sama seperti di halaman Stok Veneer Basah).
     */
    public function getFacebackProperty()
    {
        return $this->summaries
            ->filter(fn (StokVeneerKering $r) => (float) ($r->ukuran?->tebal ?? 0) <= 1)
            ->sortBy(fn (StokVeneerKering $r) => (float) ($r->ukuran?->tebal ?? 0))
            ->values();
    }

    /**
     * Core: tebal > 1mm.
     */
    public function getCoreProperty()
    {
        return $this->summaries
            ->filter(fn (StokVeneerKering $r) => (float) ($r->ukuran?->tebal ?? 0) > 1)
            ->sortBy(fn (StokVeneerKering $r) => (float) ($r->ukuran?->tebal ?? 0))
            ->values();
    }

    // ─── SERAH TERIMA ─────────────────────────────────────────────────────────

    /**
     * Antrian serah terima veneer kering.
     *
     * Sumber: VeneerMutasi yang notanya (nota_barang_masuks) SUDAH divalidasi
     * — ditandai oleh nota_barang_masuks.divalidasi_oleh yang terisi — dan
     * memiliki minimal satu detail bertipe "veneer kering".
     *
     * State "sudah diterima" diturunkan dari ledger gudang_veneer_kering
     * (relasi details.gudangKering).
     *
     * Keterangan per baris tidak ada di veneer_mutasi_detail, jadi dicocokkan
     * dari detail_nota_barang_masuk (via id_nota_bm) berdasarkan qty + kayu + kw,
     * lalu ditempel ke tiap detail sebagai atribut `keterangan_nota`.
     *
     * Urutan: belum diterima di atas (terbaru dulu), sudah diterima turun ke bawah.
     */
    public function getSerahTerimaProperty()
    {
        $keringLike = SerahTerimaVeneerKeringService::TIPE_KERING_LIKE;

        $rows = VeneerMutasi::query()
            ->whereNotNull('id_nota_bm')
            ->whereHas('notaBm', fn ($q) => $q->whereNotNull('divalidasi_oleh'))
            ->whereHas('details', fn ($q) => $q->where('tipe_veneer', 'like', $keringLike))
            ->with([
                'details' => fn ($q) => $q
                    ->where('tipe_veneer', 'like', $keringLike)
                    ->with(['ukuran', 'jenisKayu', 'gudangKering.penerima']),
                'notaBm.detail', // detail_nota_barang_masuk — sumber keterangan
            ])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        // Tempel keterangan (dari detail nota BM) ke tiap baris veneer kering.
        foreach ($rows as $vm) {
            $kandidat = $vm->notaBm?->detail ?? collect();

            foreach ($vm->details as $d) {
                $d->keterangan_nota = $this->cocokkanKeterangan($d, $kandidat);
            }
        }

        return $rows
            ->sortBy(fn (VeneerMutasi $vm) => $vm->sudahDiterima() ? 1 : 0)
            ->values();
    }

    /**
     * Cari keterangan detail nota BM yang paling cocok untuk satu baris
     * veneer kering: qty sama + nama_barang memuat jenis kayu + memuat KW.
     */
    protected function cocokkanKeterangan(VeneerMutasiDetail $detail, Collection $kandidat): ?string
    {
        $qty  = (int) round((float) $detail->qty);
        $kw   = strtolower(trim((string) $detail->kw));
        $kayu = strtolower(trim((string) ($detail->jenisKayu?->nama_kayu ?? '')));

        $match = $kandidat->first(function ($nd) use ($qty, $kw, $kayu) {
            $nama    = strtolower((string) ($nd->nama_barang ?? ''));
            $namaTNS = str_replace(' ', '', $nama); // tanpa spasi utk "kw 1" / "kw1"
            $jml     = (int) round((float) ($nd->jumlah ?? 0));

            $cocokQty  = $qty > 0 && $jml === $qty;
            $cocokKayu = $kayu !== '' && str_contains($nama, $kayu);
            $cocokKw   = $kw !== '' && (
                str_contains($nama, 'kw ' . $kw) ||
                str_contains($namaTNS, 'kw' . $kw)
            );

            return $cocokQty && $cocokKayu && $cocokKw;
        });

        $ket = $match?->keterangan;

        return ($ket !== null && trim((string) $ket) !== '') ? (string) $ket : null;
    }

    /**
     * Aksi tombol "Terima". Dipanggil dari blade via wire:click="terima(id)".
     */
    public function terima(int $id): void
    {
        $keringLike = SerahTerimaVeneerKeringService::TIPE_KERING_LIKE;

        $mutasi = VeneerMutasi::query()
            ->with([
                'notaBm',
                'details' => fn ($q) => $q->where('tipe_veneer', 'like', $keringLike),
            ])
            ->findOrFail($id);

        if (! $mutasi->id_nota_bm || ! $mutasi->notaBm || ! $mutasi->notaBm->divalidasi_oleh) {
            Notification::make()->title('Nota belum divalidasi.')->danger()->send();
            return;
        }

        $sudahDiterima = GudangVeneerKeringModel::query()
            ->whereHas('mutasiDetail', fn ($q) => $q->where('id_veneer_mutasi', $id))
            ->exists();

        if ($sudahDiterima) {
            Notification::make()->title('Barang ini sudah diterima.')->warning()->send();
            return;
        }

        app(SerahTerimaVeneerKeringService::class)->terima($mutasi, (int) auth()->id());

        Notification::make()
            ->title('Barang berhasil diterima ke Gudang Veneer Kering.')
            ->success()
            ->send();
    }
}