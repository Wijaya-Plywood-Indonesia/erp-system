<?php

namespace App\Filament\Pages;

use App\Models\SerahTerimaVeneerKering;
use App\Models\StokVeneerKering;
use App\Models\Ukuran;
use App\Models\VeneerKeringMutasiKeluar;
use App\Models\VeneerKeringMutasiKeluarPalet;
use App\Services\StokVeneerKeringService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class GudangVeneerKering extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.gudang-veneer-kering';

    protected static ?string $navigationLabel = 'Gudang Veneer Kering';

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static ?string $title = 'Gudang Veneer Kering';

    protected static ?int $navigationSort = 21;

    // Tab aktif: 'masuk' (Serah Terima) / 'keluar' (Veneer Keluar)
    public string $activeTab = 'masuk';

    // Search bebas: cocok ke ukuran (p x l x t), KW, atau nama jenis kayu
    public string $search = '';

    // Search riwayat keluar
    public string $keluarSearchQuery = '';

    // Tab aktif untuk section Serah Terima Dryer/Kedi: 'aktif' / 'history'
    public string $serahTerimaTab = 'aktif';

    // ── Form Barang Keluar ──
    public bool $showFormKeluarModal = false;

    public ?int $selectedStokId = null;          // id baris summary (StokVeneerKering latest per kombinasi)

    public $jumlahPalet = 1;

    public array $paletQuantities = [0 => ''];

    public string $tujuanKeluar = 'Repair';

    public string $keteranganKeluar = '';

    protected $queryString = ['activeTab'];

    /**
     * Ringkasan stok per kombinasi produk (id_ukuran + id_jenis_kayu + kw).
     * Logika IDENTIK dgn halaman Stok Veneer Kering (MAX(id), stok_m3_sesudah > 0,
     * total_lembar = SUM(masuk) - SUM(keluar)).
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
                    rtrim(rtrim(number_format((float) $row->ukuran?->panjang, 2), '0'), '.').'x'.
                    rtrim(rtrim(number_format((float) $row->ukuran?->lebar, 2), '0'), '.').'x'.
                    rtrim(rtrim(number_format((float) $row->ukuran?->tebal, 2), '0'), '.')
                );
                $kw = strtolower((string) $row->kw);
                $kayu = strtolower((string) $row->jenisKayu?->nama_kayu);

                return str_contains($dimensi, $needle)
                    || str_contains($kw, $needle)
                    || str_contains($kayu, $needle);
            });
        }

        return $rows;
    }

    public function getFacebackProperty()
    {
        return $this->summaries
            ->filter(fn (StokVeneerKering $r) => (float) ($r->ukuran?->tebal ?? 0) <= 1)
            ->sortBy(fn (StokVeneerKering $r) => (float) ($r->ukuran?->tebal ?? 0))
            ->values();
    }

    public function getCoreProperty()
    {
        return $this->summaries
            ->filter(fn (StokVeneerKering $r) => (float) ($r->ukuran?->tebal ?? 0) > 1)
            ->sortBy(fn (StokVeneerKering $r) => (float) ($r->ukuran?->tebal ?? 0))
            ->values();
    }

    // ─── SERAH TERIMA (DARI DRYER / KEDI) ──────────────────────────────────────

    /**
     * Daftar antrean SerahTerimaVeneerKering yang berasal dari Press Dryer atau
     * Kedi dan BELUM diterima siapa pun (diterima_oleh = '-').
     * Ini yang ditampilkan di tab "Serah Terima" halaman Gudang Veneer Kering,
     * supaya admin gudang bisa langsung menerima hasil dryer ke stok gudang
     * TANPA perlu lewat halaman Produksi Repair.
     */
    public function getSerahTerimaProperty(): Collection
    {
        return SerahTerimaVeneerKering::query()
            ->whereIn('tipe_sumber', ['dryer', 'kedi'])
            ->where('jenis_terima', 'kering')
            ->where('diterima_oleh', '-')
            ->with([
                'detailHasil.ukuran',
                'detailHasil.jenisKayu',
                'detailBongkarKedi.ukuran',
                'detailBongkarKedi.jenisKayu',
            ])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Riwayat veneer dari Dryer/Kedi yang SUDAH diterima ke Gudang Veneer
     * Kering (untuk tab "History" — supaya bisa dilihat kapan diterimanya).
     */
    public function getRiwayatSerahTerimaProperty(): Collection
    {
        return SerahTerimaVeneerKering::query()
            ->whereIn('tipe_sumber', ['dryer', 'kedi'])
            ->where('jenis_terima', 'kering')
            ->where('diterima_oleh', '!=', '-')
            ->with([
                'detailHasil.ukuran',
                'detailHasil.jenisKayu',
                'detailBongkarKedi.ukuran',
                'detailBongkarKedi.jenisKayu',
            ])
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Terima satu baris antrean dryer/kedi langsung ke stok Gudang Veneer
     * Kering. Selalu diterima sebagai "kering" (bukan "jadi"), karena memang
     * ini alur masuk ke Gudang Veneer Kering.
     */
    public function terimaDryer(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $fresh = SerahTerimaVeneerKering::lockForUpdate()->findOrFail($id);

                if ($fresh->diterima_oleh !== '-') {
                    throw new \RuntimeException('Veneer ini sudah diterima sebelumnya.');
                }

                if (! in_array($fresh->tipe_sumber, ['dryer', 'kedi'], true)) {
                    throw new \RuntimeException('Sumber veneer tidak valid untuk diterima di Gudang Veneer Kering.');
                }

                if ($fresh->jenis_terima !== 'kering') {
                    throw new \RuntimeException('Barang ini bukan veneer kering, tidak bisa diterima di sini.');
                }

                $user = Auth::user();

                $fresh->update([
                    'diterima_oleh' => ($user?->name ?? 'System').' - Gudang Veneer Kering',
                    'status' => 'Terima Veneer',
                ]);

                app(StokVeneerKeringService::class)->terimaRepair($fresh);
            });

            Notification::make()
                ->title('Veneer kering berhasil diterima ke Gudang.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menerima Veneer')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ─── VENEER KELUAR ────────────────────────────────────────────────────────

    /**
     * Sinkronkan jumlah field "isi per palet" saat angka Jumlah Palet berubah.
     */
    public function updatedJumlahPalet($value): void
    {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            return; // biarkan saat user sedang mengosongkan input
        }

        $count = max(1, intval($value));
        $this->paletQuantities = array_slice($this->paletQuantities, 0, $count);

        while (count($this->paletQuantities) < $count) {
            $this->paletQuantities[] = '';
        }
    }

    public function hapusPalet(int $index): void
    {
        if (isset($this->paletQuantities[$index])) {
            unset($this->paletQuantities[$index]);
            $this->paletQuantities = array_values($this->paletQuantities);
            $this->jumlahPalet = count($this->paletQuantities);
        }
    }

    /**
     * Proses pengeluaran veneer kering — ALUR BARU:
     *
     * Klik "Proses Barang Keluar" di sini TIDAK LAGI langsung memotong stok.
     * Yang terjadi hanya:
     *   1. Simpan header VeneerKeringMutasiKeluar + rincian tiap palet
     *      (murni pencatatan "barang apa yang dikeluarkan dari gudang").
     *   2. Untuk tiap palet dengan qty > 0, buat satu baris antrean
     *      SerahTerimaVeneerKering (tipe_sumber='gudang', diterima_oleh='-').
     *
     * Palet-palet itu baru akan memotong stok & tercatat di Log HPP saat
     * di-"Terima" satu-per-satu di RelationManager Produksi Repair — lihat
     * StokVeneerKeringService::terimaKeluarGudang(). Kalau dari 5 palet yang
     * dikeluarkan cuma 3 yang diterima, maka stok/log hanya terpotong 3 palet;
     * sisanya tetap menunggu di antrean tanpa memengaruhi stok.
     */
    public function prosesKeluar(): void
    {
        // Tujuan keluar dikunci: selalu Repair.
        $this->tujuanKeluar = 'Repair';

        $totalLembar = array_sum(array_map('intval', $this->paletQuantities));

        if (! $this->selectedStokId || $totalLembar <= 0 || trim($this->tujuanKeluar) === '') {
            Notification::make()
                ->danger()
                ->title('Input Gagal')
                ->body('Pilih stok, isi kuantitas palet, dan tujuan keluar wajib diisi.')
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($totalLembar) {
                // Baris summary yang dipilih = wakil kombinasi produk.
                $ref = StokVeneerKering::with('ukuran')->findOrFail($this->selectedStokId);

                $idUkuran = (int) $ref->id_ukuran;
                $idJenisKayu = (int) $ref->id_jenis_kayu;
                $kw = (string) $ref->kw;

                // Validasi saldo tetap di sini (early feedback), meskipun stok
                // baru benar-benar terpotong nanti saat masing-masing palet
                // diterima di Produksi Repair.
                $saldo = StokVeneerKering::saldoLembarTerakhir($idUkuran, $idJenisKayu, $kw);
                if ($totalLembar > $saldo) {
                    throw new \Exception("Stok tidak mencukupi. Tersedia: {$saldo} lembar.");
                }

                $ukuran = $ref->ukuran ?? Ukuran::findOrFail($idUkuran);
                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $totalLembar) / 10000000;

                $user = Auth::user();

                // 1. Header mutasi keluar — murni pencatatan, TIDAK menyentuh stok/log.
                $mutasi = VeneerKeringMutasiKeluar::create([
                    'id_ukuran' => $idUkuran,
                    'id_jenis_kayu' => $idJenisKayu,
                    'kw' => $kw,
                    'jumlah_palet' => count($this->paletQuantities),
                    'qty' => $totalLembar,
                    'm3' => $m3,
                    'tujuan_keluar' => trim($this->tujuanKeluar),
                    'dikeluarkan_oleh' => $user?->id,
                    'keterangan' => trim($this->keteranganKeluar) !== '' ? trim($this->keteranganKeluar) : null,
                ]);

                // 2. Rincian per palet + antrean Serah Terima (BELUM potong stok/log).
                foreach ($this->paletQuantities as $index => $qty) {
                    $qtyPalet = intval($qty);

                    $palet = VeneerKeringMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'no_palet' => $index + 1,
                        'qty' => $qtyPalet,
                    ]);

                    if ($qtyPalet <= 0) {
                        continue; // palet kosong tidak perlu masuk antrean
                    }

                    SerahTerimaVeneerKering::create([
                        'id_mutasi_keluar_palet' => $palet->id,
                        'tipe_sumber' => 'gudang',
                        'diserahkan_oleh' => $user?->name ?? 'System',
                        'diterima_oleh' => '-',
                        'status' => 'Serah Veneer',
                    ]);
                }
            });

            // Reset form
            $this->selectedStokId = null;
            $this->jumlahPalet = 1;
            $this->paletQuantities = [0 => ''];
            $this->tujuanKeluar = 'Repair';
            $this->keteranganKeluar = '';
            $this->showFormKeluarModal = false;

            Notification::make()
                ->success()
                ->title('Barang Keluar Tercatat')
                ->body("{$totalLembar} lembar veneer kering menunggu diterima di Serah Terima (Produksi Repair). Stok baru berkurang setelah diterima.")
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Mengeluarkan Barang')
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * Riwayat mutasi keluar (terbaru dulu), dengan pencarian bebas.
     * Menampilkan SEMUA mutasi keluar apa adanya (mis. 5 palet dikeluarkan),
     * terlepas dari berapa banyak yang sudah diterima di Produksi Repair.
     */
    public function getRiwayatKeluarProperty(): Collection
    {
        $query = VeneerKeringMutasiKeluar::with(['ukuran', 'jenisKayu', 'palets', 'operator'])
            ->orderByDesc('created_at');

        if (trim($this->keluarSearchQuery) !== '') {
            $q = strtolower(trim($this->keluarSearchQuery));
            $query->where(function ($query) use ($q) {
                $query->whereHas('jenisKayu', fn ($qr) => $qr->whereRaw('LOWER(nama_kayu) LIKE ?', ["%{$q}%"]))
                    ->orWhereRaw('LOWER(kw) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(tujuan_keluar) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(keterangan) LIKE ?', ["%{$q}%"]);
            });
        }

        return $query->get();
    }
}
