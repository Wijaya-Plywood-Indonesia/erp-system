<?php

namespace App\Filament\Pages;

use App\Models\GudangTriplekJadi as GudangModel;
use App\Models\HppTriplekJadiLog;
use App\Models\StokTriplekJadi;
use App\Models\TriplekJadiMutasiKeluar;
use App\Models\TriplekJadiMutasiKeluarPalet;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class GudangTriplekJadi extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $title = 'Gudang Triplek Jadi';
    protected string $view = 'filament.pages.gudang-triplek-jadi';
    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    // 🌟 KONSTANTA tipe transaksi log — SATU sumber kebenaran, huruf kecil.
    // Selalu pakai ini (jangan string mentah) supaya tidak kena bug casing
    // 'MASUK' vs 'masuk' seperti di modul veneer.
    public const TIPE_MASUK  = 'masuk';
    public const TIPE_KELUAR = 'keluar';

    public string $activeTab = 'masuk';
    public string $searchQuery = '';
    public string $tableSearchQuery = '';
    public string $keluarSearchQuery = '';

    // Modal konfirmasi terima — composite id "{source}-{id}", mis. "produksi-5"
    public bool $showConfirmModal = false;
    public ?string $selectedItemId = null;

    // Modal form keluar barang
    public bool $showFormKeluarModal = false;
    public ?int $selectedStokId = null;
    public $jumlahPalet = 1;
    public array $paletQuantities = [0 => ''];
    public string $tujuanKeluar = 'Packing';
    public string $keteranganKeluar = '';

    protected $queryString = ['activeTab'];

    public function hitungKubikasi(float $p, float $l, float $t, ?int $lembar): float
    {
        return ($p * $l * $t * ($lembar ?? 0)) / 10000000;
    }

    // ─── SERAH TERIMA (BARANG MASUK) ─────────────────────────────────────────

    public function confirmTerima(string $compositeId): void
    {
        $this->selectedItemId = $compositeId;
        $this->showConfirmModal = true;
    }

    public function cancelConfirm(): void
    {
        $this->showConfirmModal = false;
        $this->selectedItemId = null;
    }

    /**
     * Proses terima barang. Branching berdasarkan source dari composite id.
     *
     * Saat ini SEMUA source (produksi/repair/bm) tersimpan di tabel penampung
     * gudang_triplek_jadis, jadi semuanya lewat terimaDariGudang(). Branching
     * disiapkan sejak awal supaya kalau nanti ada sumber yang TIDAK lewat
     * penampung (mis. langsung dari VeneerMutasiDetail seperti di veneer),
     * tinggal tambah satu cabang tanpa mengubah view.
     */
    public function terimaBarang(?string $compositeId = null): void
    {
        // Bisa dipanggil langsung dari wire:confirm (gaya Platform Jadi)
        // dengan composite id, atau lewat modal (selectedItemId sudah diisi).
        if ($compositeId !== null) {
            $this->selectedItemId = $compositeId;
        }

        if (! $this->selectedItemId) {
            return;
        }

        [$source, $rawId] = array_pad(explode('-', $this->selectedItemId, 2), 2, null);

        match ($source) {
            // Contoh cabang masa depan:
            // 'mutasi' => $this->terimaDariMutasi((int) $rawId),
            default => $this->terimaDariGudang((int) $rawId),
        };

        $this->showConfirmModal = false;
        $this->selectedItemId = null;
        $this->dispatch('$refresh');
    }

    /**
     * Terima barang dari tabel penampung gudang_triplek_jadis:
     * upsert stok induk + tulis log berantai + tandai baris antrean.
     */
    protected function terimaDariGudang(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $record = GudangModel::where('id', $id)->lockForUpdate()->first();

                if (! $record) {
                    throw new \Exception('Data tidak ditemukan.');
                }

                if ($record->status_gudang === GudangModel::STATUS_SUDAH_DITERIMA) {
                    throw new \Exception('Barang ini sudah pernah diterima sebelumnya.');
                }

                $user     = Auth::user();
                $userName = $user?->name ?? 'System';

                $stokInduk = StokTriplekJadi::where('id_jenis_kayu', $record->id_jenis_kayu)
                    ->where('panjang', $record->panjang)
                    ->where('lebar', $record->lebar)
                    ->where('tebal', $record->tebal)
                    ->where('kw_grade', $record->kw_grade)
                    ->lockForUpdate()
                    ->first();

                if (! $stokInduk) {
                    $stokInduk = StokTriplekJadi::create([
                        'id_jenis_kayu'           => $record->id_jenis_kayu,
                        'panjang'                 => $record->panjang,
                        'lebar'                   => $record->lebar,
                        'tebal'                   => $record->tebal,
                        'kw_grade'                => $record->kw_grade,
                        'stok_lembar'             => 0,
                        'stok_kubikasi'           => 0,
                        'nilai_stok'              => 0,
                        'hpp_average'             => 0,
                        'hpp_pekerja_last'        => 0,
                        'hpp_bahan_penolong_last' => 0,
                        'id_last_log'             => null,
                    ]);
                }

                $before = [
                    'lembar'   => (int) $stokInduk->stok_lembar,
                    'kubikasi' => (float) $stokInduk->stok_kubikasi,
                    'nilai'    => (float) $stokInduk->nilai_stok,
                ];

                $after = [
                    'lembar'   => $before['lembar'] + (int) $record->stok_lembar,
                    'kubikasi' => round($before['kubikasi'] + (float) $record->stok_kubikasi, 6),
                    'nilai'    => $before['nilai'] + (float) $record->nilai_stok,
                ];

                $hppAverageAfter = $after['lembar'] > 0
                    ? $after['nilai'] / $after['lembar']
                    : 0;

                $log = HppTriplekJadiLog::create([
                    'id_jenis_kayu'        => $record->id_jenis_kayu,
                    'panjang'              => $record->panjang,
                    'lebar'                => $record->lebar,
                    'tebal'                => $record->tebal,
                    'kw_grade'             => $record->kw_grade,
                    'tanggal'              => now(),
                    'tipe_transaksi'       => self::TIPE_MASUK,
                    'referensi_type'       => GudangModel::class,
                    'referensi_id'         => $record->id,
                    'total_lembar'         => $record->stok_lembar,
                    'total_kubikasi'       => $record->stok_kubikasi,
                    'hpp_pekerja'          => $record->hpp_pekerja_last ?? 0,
                    'hpp_bahan_penolong'   => $record->hpp_bahan_penolong_last ?? 0,
                    'hpp_average'          => $hppAverageAfter,
                    'nilai_stok'           => $record->nilai_stok,
                    'stok_lembar_before'   => $before['lembar'],
                    'stok_kubikasi_before' => $before['kubikasi'],
                    'nilai_stok_before'    => $before['nilai'],
                    'stok_lembar_after'    => $after['lembar'],
                    'stok_kubikasi_after'  => $after['kubikasi'],
                    'nilai_stok_after'     => $after['nilai'],
                    'keterangan'           => sprintf(
                        '%s, diterima oleh: %s pada %s',
                        $record->keterangan ?? ucfirst($record->source),
                        $userName,
                        now()->translatedFormat('d F Y H:i')
                    ),
                ]);

                $stokInduk->update([
                    'stok_lembar'   => $after['lembar'],
                    'stok_kubikasi' => $after['kubikasi'],
                    'nilai_stok'    => $after['nilai'],
                    'hpp_average'   => $hppAverageAfter,
                    'id_last_log'   => $log->id,
                ]);

                $record->update([
                    'status_gudang' => GudangModel::STATUS_SUDAH_DITERIMA,
                    'diterima_by'   => $user?->id,
                    'diterima_at'   => now(),
                ]);
            });

            Notification::make()->success()
                ->title('Sukses Diterima!')
                ->body('Barang resmi masuk gudang dan stok telah diperbarui.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()->danger()
                ->title('Gagal Menerima Barang')
                ->body($e->getMessage())
                ->send();
        }
    }

    // ─── STOK UTAMA ──────────────────────────────────────────────────────────

    public function getStokListProperty(): Collection
    {
        return StokTriplekJadi::with(['jenisKayu', 'lastLog'])
            ->where('stok_lembar', '>', 0)
            ->get()
            ->filter(function ($item) {
                if (trim($this->searchQuery) === '') {
                    return true;
                }
                $q        = strtolower(trim($this->searchQuery));
                $namaKayu = strtolower((string) $item->jenisKayu?->nama_kayu);
                $dimensi  = strtolower(($item->panjang + 0) . 'x' . ($item->lebar + 0) . 'x' . ($item->tebal + 0));

                return str_contains($namaKayu, $q)
                    || str_contains(strtolower((string) $item->kw_grade), $q)
                    || str_contains($dimensi, $q);
            })
            ->values();
    }

    // ─── ANTREAN SERAH TERIMA ────────────────────────────────────────────────

    /**
     * Antrean gabungan semua sumber. Setiap baris memakai composite id
     * "{source}-{id}" dan field yang SERAGAM supaya view tidak perlu tahu
     * bedanya. Saat ini satu-satunya sumber fisik adalah tabel penampung —
     * tapi bentuk return sudah siap ditambah collection lain via concat().
     */
    public function getAntreanFilteredProperty(): Collection
    {
        $dariGudang = $this->ambilAntreanDariGudang();

        // Sumber tambahan tinggal di-concat di sini nanti, contoh:
        // $dariMutasi = $this->ambilAntreanDariMutasi();
        // return $dariGudang->concat($dariMutasi)->sortBy([...])->values();

        return $dariGudang
            ->sortBy([
                fn($item) => $item['status_gudang'] === GudangModel::STATUS_BELUM_DITERIMA ? 0 : 1,
                fn($item) => -$item['created_at_ts'],
                fn($item) => $item['id'],
            ])
            ->values();
    }

    protected function ambilAntreanDariGudang(): Collection
    {
        $query = GudangModel::with(['jenisKayu', 'penerima'])
            ->select([
                'gudang_triplek_jadis.*',
                'jenis_kayus.nama_kayu as jenis_kayu_nama',
            ])
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'gudang_triplek_jadis.id_jenis_kayu');

        if (trim($this->tableSearchQuery) !== '') {
            $q = strtolower(trim($this->tableSearchQuery));
            $query->where(function ($query) use ($q) {
                $query->whereRaw('LOWER(jenis_kayus.nama_kayu) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(gudang_triplek_jadis.kw_grade) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(gudang_triplek_jadis.keterangan) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(gudang_triplek_jadis.source) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(CONCAT(
                        (gudang_triplek_jadis.panjang + 0), "x",
                        (gudang_triplek_jadis.lebar + 0), "x",
                        (gudang_triplek_jadis.tebal + 0)
                    )) LIKE ?', ["%{$q}%"]);
            });
        }

        return $query->get()->map(fn($item) => [
            'id'             => $item->source . '-' . $item->id,
            'source'         => $item->source,
            'jenis_kayu'     => $item->jenis_kayu_nama,
            'panjang'        => $item->panjang,
            'lebar'          => $item->lebar,
            'tebal'          => $item->tebal,
            'kw'             => $item->kw_grade,
            'jumlah'         => $item->stok_lembar,
            'stok_kubikasi'  => $item->stok_kubikasi,
            'created_at'     => $item->created_at,
            'created_at_ts'  => $item->created_at?->timestamp ?? 0,
            'status_gudang'  => $item->status_gudang ?? GudangModel::STATUS_BELUM_DITERIMA,
            'diterima_at'    => $item->diterima_at,
            'penerima_name'  => $item->penerima?->name ?? 'N/A',
            'keterangan'     => $item->keterangan,
        ])->values();
    }

    // ─── BARANG KELUAR ───────────────────────────────────────────────────────

    public function updatedJumlahPalet($value): void
    {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            return;
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
     * Catat mutasi keluar: header + rincian palet. Konsisten dengan pola
     * terbaru veneer/platform — stok TIDAK dipotong di sini; pemotongan
     * terjadi saat barang dikonfirmasi diterima di tujuan.
     */
    public function prosesKeluar(): void
    {
        $totalLembar = array_sum(array_map('intval', $this->paletQuantities));

        if (! $this->selectedStokId || $totalLembar <= 0 || trim($this->tujuanKeluar) === '') {
            Notification::make()->danger()
                ->title('Input Gagal')
                ->body('Spesifikasi stok, kuantitas palet, dan tujuan pengeluaran wajib diisi.')
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($totalLembar) {
                $stok = StokTriplekJadi::lockForUpdate()->findOrFail($this->selectedStokId);

                if ($totalLembar > (int) $stok->stok_lembar) {
                    throw new \Exception('Sisa stok tidak mencukupi. Tersedia: ' . $stok->stok_lembar . ' lembar.');
                }

                $mutasi = TriplekJadiMutasiKeluar::create([
                    'id_jenis_kayu'  => $stok->id_jenis_kayu,
                    'panjang'        => $stok->panjang,
                    'lebar'          => $stok->lebar,
                    'tebal'          => $stok->tebal,
                    'kw_grade'       => $stok->kw_grade,
                    'jumlah_palet'   => count($this->paletQuantities),
                    'stok_lembar'    => $totalLembar,
                    'stok_kubikasi'  => $this->hitungKubikasi($stok->panjang, $stok->lebar, $stok->tebal, $totalLembar),
                    'tujuan'         => trim($this->tujuanKeluar),
                    'dikeluarkan_by' => Auth::id(),
                    'keterangan'     => trim($this->keteranganKeluar) !== '' ? trim($this->keteranganKeluar) : null,
                    'status'         => TriplekJadiMutasiKeluar::STATUS_DIKIRIM,
                ]);

                foreach ($this->paletQuantities as $index => $qtyRaw) {
                    $qty = intval($qtyRaw);
                    if ($qty <= 0) {
                        continue;
                    }

                    TriplekJadiMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'nomor_palet'      => $index + 1,
                        'jumlah_lembar'    => $qty,
                    ]);
                }
            });

            // Reset form
            $this->selectedStokId      = null;
            $this->jumlahPalet         = 1;
            $this->paletQuantities     = [0 => ''];
            $this->tujuanKeluar        = 'Packing';
            $this->keteranganKeluar    = '';
            $this->showFormKeluarModal = false;

            Notification::make()->success()
                ->title('Mutasi Keluar Dicatat')
                ->body("{$totalLembar} lembar tercatat dikirim. Stok akan terpotong setelah dikonfirmasi diterima.")
                ->send();
        } catch (\Exception $e) {
            Notification::make()->danger()
                ->title('Gagal Mengeluarkan Barang')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function getRiwayatKeluarFilteredProperty(): Collection
    {
        $query = TriplekJadiMutasiKeluar::with(['jenisKayu', 'palets', 'operator'])
            ->orderByDesc('created_at');

        if (trim($this->keluarSearchQuery) !== '') {
            $q = strtolower(trim($this->keluarSearchQuery));
            $query->where(function ($query) use ($q) {
                $query->whereHas('jenisKayu', fn($qr) => $qr->whereRaw('LOWER(nama_kayu) LIKE ?', ["%{$q}%"]))
                    ->orWhereRaw('LOWER(kw_grade) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(tujuan) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(keterangan) LIKE ?', ["%{$q}%"]);
            });
        }

        // Kembalikan model langsung (bukan array) — sama seperti
        // GudangPlatformJadi::getRiwayatKeluarProperty(), supaya view bisa
        // akses relasi $rk->palets, $rk->jenisKayu, $rk->operator langsung.
        return $query->get();
    }
}