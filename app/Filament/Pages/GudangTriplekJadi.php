<?php

namespace App\Filament\Pages;

use App\Models\HppTriplekJadiLog;
use App\Models\SerahTerimaGudangSatu;
use App\Models\SerahTerimaHp;
use App\Models\SerahTerimaTriplekJadi;
use App\Models\StokTriplekJadi;
use App\Models\TriplekJadiMutasiKeluar;
use App\Models\TriplekJadiMutasiKeluarPalet;
use App\Models\WipSandingReset;
use App\Services\TransferTriplekJadiKeMthService;
use App\Services\TransferTriplekMthKeJadiService;
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

    // Tujuan keluar yang diizinkan — SATU sumber kebenaran.
    public const TUJUAN_NYUSUP = 'Produksi Nyusup';

    public const TUJUAN_GUDANG_SATU = 'Gudang Satu';

    public const TUJUAN_SANDING = 'Produksi Sanding';

    // Hotpress: nilai tersimpan HARUS 'hotpress' (huruf kecil) karena VIEW
    // serah_terima_masuk_hp memfilter LOWER(mk.tujuan) = 'hotpress'.
    public const TUJUAN_HOTPRESS = 'hotpress';

    public const TUJUAN_MTH = 'Gudang Triplek Mentah';

    public const TUJUAN_OPTIONS = [
        self::TUJUAN_NYUSUP,
        self::TUJUAN_GUDANG_SATU,
        self::TUJUAN_SANDING,
        self::TUJUAN_HOTPRESS,
        self::TUJUAN_MTH,
    ];

    public const TUJUAN_LABELS = [
        self::TUJUAN_NYUSUP => 'Produksi Nyusup',
        self::TUJUAN_GUDANG_SATU => 'Gudang Satu',
        self::TUJUAN_SANDING => 'Produksi Sanding',
        self::TUJUAN_HOTPRESS => 'Produksi Hotpress',
        self::TUJUAN_MTH => 'Gudang Triplek Mentah',
    ];

    public string $searchQuery = '';        // search dropdown stok (di modal)

    public string $keluarSearchQuery = '';  // search riwayat keluar

    // ── Form Barang Keluar ──
    public bool $showFormKeluarModal = false;

    public ?int $selectedStokId = null;

    public $jumlahPalet = 1;

    public array $paletQuantities = [0 => ''];

    public string $tujuanKeluar = self::TUJUAN_NYUSUP;

    public string $keteranganKeluar = '';

    // ── Modal Tolak Serah Terima ──
    public bool $showTolakModal = false;

    public ?int $tolakId = null;

    public string $tolakTipe = '';

    public string $alasanTolak = '';

    public string $tolakModalTitle = 'Tolak Kiriman Triplek';

    public string $tolakModalDescription = 'Kiriman ini akan ditolak dan tidak dimasukkan ke stok Gudang Triplek Jadi.';

    public function hitungKubikasi(float $p, float $l, float $t, ?int $lembar): float
    {
        return ($p * $l * $t * ($lembar ?? 0)) / 10000000;
    }

    /**
     * Lembar yang sudah dicatat keluar ke Gudang Triplek Mentah tetapi belum
     * diterima/ditolak. Stok fisik baru dipotong saat diterima, jadi angka ini
     * dikurangkan dari stok agar barang yang sama tidak bisa dikirim dua kali.
     */
    protected function pendingLembarMth(StokTriplekJadi $stok, ?int $kecualiMutasiId = null): int
    {
        $idMutasiPending = SerahTerimaHp::query()
            ->where('tujuan', TransferTriplekJadiKeMthService::TUJUAN)
            ->whereNotNull('id_triplek_mutasi_keluar')
            ->where('diterima_oleh', '-')
            ->whereNull('ditolak_oleh')
            ->whereNull('diterima_gudang_at')
            ->select('id_triplek_mutasi_keluar');

        return (int) TriplekJadiMutasiKeluar::query()
            ->whereIn('id', $idMutasiPending)
            ->where('id_jenis_kayu', $stok->id_jenis_kayu)
            ->where('panjang', $stok->panjang)
            ->where('lebar', $stok->lebar)
            ->where('tebal', $stok->tebal)
            ->where('kw_grade', $stok->kw_grade)
            ->when($kecualiMutasiId, fn ($q) => $q->where('id', '!=', $kecualiMutasiId))
            ->sum('stok_lembar');
    }

    // ── Modal Edit Riwayat Keluar ──
    public bool $showEditKeluarModal = false;

    public ?int $editKeluarId = null;

    public $editJumlahPalet = 1;

    public array $editPaletQuantities = [0 => ''];

    protected function bisaDiedit(TriplekJadiMutasiKeluar $mutasi): bool
    {
        if ($mutasi->status !== TriplekJadiMutasiKeluar::STATUS_DIKIRIM) {
            return false;
        }

        $sudahDiklaimGudangSatu = SerahTerimaGudangSatu::where('id_triplek_mutasi_keluar', $mutasi->id)
            ->where('diterima_oleh', '!=', '-')
            ->exists();

        $sudahDiklaimSanding = SerahTerimaHp::where('id_triplek_mutasi_keluar', $mutasi->id)
            ->where('tujuan', 'sanding')
            ->where('diterima_oleh', '!=', '-')
            ->exists();

        $sudahDiterimaMth = SerahTerimaHp::where('id_triplek_mutasi_keluar', $mutasi->id)
            ->where('tujuan', TransferTriplekJadiKeMthService::TUJUAN)
            ->where('diterima_oleh', '!=', '-')
            ->exists();

        return ! $sudahDiklaimGudangSatu && ! $sudahDiklaimSanding && ! $sudahDiterimaMth;
    }

    public function editKeluar(int $id): void
    {
        $mutasi = TriplekJadiMutasiKeluar::with('palets')->find($id);

        if (! $mutasi) {
            Notification::make()->danger()->title('Data tidak ditemukan')->send();

            return;
        }

        if (! $this->bisaDiedit($mutasi)) {
            Notification::make()
                ->danger()
                ->title('Tidak Bisa Diedit')
                ->body('Mutasi ini sudah diterima/diklaim di sisi tujuan, rincian tidak bisa diubah lagi.')
                ->send();

            return;
        }

        $this->editKeluarId = $mutasi->id;

        $palet = $mutasi->palets->sortBy('nomor_palet')->pluck('jumlah_lembar')->values()->toArray();
        $this->editPaletQuantities = ! empty($palet) ? $palet : [0 => ''];
        $this->editJumlahPalet = count($this->editPaletQuantities);

        $this->showEditKeluarModal = true;
    }

    public function cancelEditKeluar(): void
    {
        $this->showEditKeluarModal = false;
        $this->editKeluarId = null;
    }

    public function updatedEditJumlahPalet($value): void
    {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            return;
        }

        $count = max(1, intval($value));
        $this->editPaletQuantities = array_slice($this->editPaletQuantities, 0, $count);

        while (count($this->editPaletQuantities) < $count) {
            $this->editPaletQuantities[] = '';
        }
    }

    public function hapusEditPalet(int $index): void
    {
        if (isset($this->editPaletQuantities[$index])) {
            unset($this->editPaletQuantities[$index]);
            $this->editPaletQuantities = array_values($this->editPaletQuantities);
            $this->editJumlahPalet = count($this->editPaletQuantities);
        }
    }

    public function updateKeluar(): void
    {
        if (! $this->editKeluarId) {
            return;
        }

        $totalLembar = array_sum(array_map('intval', $this->editPaletQuantities));

        if ($totalLembar <= 0) {
            Notification::make()->danger()->title('Input Gagal')->body('Kuantitas palet wajib diisi.')->send();

            return;
        }

        try {
            DB::transaction(function () use ($totalLembar) {
                $mutasi = TriplekJadiMutasiKeluar::with('palets')
                    ->where('id', $this->editKeluarId)
                    ->lockForUpdate()
                    ->first();

                if (! $mutasi) {
                    throw new \Exception('Data tidak ditemukan.');
                }

                if (! $this->bisaDiedit($mutasi)) {
                    throw new \Exception('Mutasi ini sudah diterima/diklaim di sisi tujuan, tidak bisa diedit lagi.');
                }

                $stok = StokTriplekJadi::where('id_jenis_kayu', $mutasi->id_jenis_kayu)
                    ->where('panjang', $mutasi->panjang)
                    ->where('lebar', $mutasi->lebar)
                    ->where('tebal', $mutasi->tebal)
                    ->where('kw_grade', $mutasi->kw_grade)
                    ->lockForUpdate()
                    ->first();

                // Bandingkan dengan stok fisik dikurangi kiriman LAIN ke Mentah yang masih menunggu.
                if (! $stok || $totalLembar > ((int) $stok->stok_lembar - $this->pendingLembarMth($stok, $mutasi->id))) {
                    throw new \Exception('Sisa stok fisik di gudang tidak mencukupi untuk kuantitas baru.');
                }

                $mutasi->update([
                    'jumlah_palet' => count($this->editPaletQuantities),
                    'stok_lembar' => $totalLembar,
                    'stok_kubikasi' => $this->hitungKubikasi($mutasi->panjang, $mutasi->lebar, $mutasi->tebal, $totalLembar),
                ]);

                $mutasi->palets()->delete();

                foreach ($this->editPaletQuantities as $index => $qty) {
                    $qtyPalet = intval($qty);
                    if ($qtyPalet <= 0) {
                        continue;
                    }

                    TriplekJadiMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'nomor_palet' => $index + 1,
                        'jumlah_lembar' => $qtyPalet,
                    ]);
                }
            });

            unset($this->riwayatKeluarFiltered);

            $this->showEditKeluarModal = false;
            $this->editKeluarId = null;

            Notification::make()
                ->success()
                ->title('✓ Rincian Diperbarui')
                ->body("Rincian palet berhasil diubah menjadi {$totalLembar} lembar.")
                ->send();
        } catch (\Exception $e) {
            Notification::make()->danger()->title('Gagal Memperbarui')->body($e->getMessage())->send();
        }
    }

    // ─── TERIMA DARI GUDANG TRIPLEK MENTAH ──────────────────────────────────

    public function getMenungguDariMthProperty(): Collection
    {
        return SerahTerimaHp::with('triplekMthMutasiKeluar.jenisKayu')
            ->where('tujuan', TransferTriplekMthKeJadiService::TUJUAN)
            ->whereNotNull('id_triplek_mth_mutasi_keluar')
            ->where('diterima_oleh', '-')
            ->whereNull('ditolak_oleh')
            ->whereNull('diterima_gudang_at')
            ->latest()
            ->get();
    }

    public function getRiwayatDariMthProperty(): Collection
    {
        return SerahTerimaHp::with('triplekMthMutasiKeluar.jenisKayu')
            ->where('tujuan', TransferTriplekMthKeJadiService::TUJUAN)
            ->whereNotNull('diterima_gudang_at')
            ->orderByDesc('diterima_gudang_at')
            ->limit(50)
            ->get();
    }

    public function terimaDariMth(int $id): void
    {
        try {
            app(TransferTriplekMthKeJadiService::class)->terima($id);

            unset($this->menungguDariMth, $this->riwayatDariMth);

            Notification::make()->success()
                ->title('Stok Bertambah')
                ->body('Triplek dari Gudang Triplek Mentah diterima di Gudang Triplek Jadi.')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title('Gagal Menerima')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function bukaModalTolak(int $id, string $tipe = 'mth'): void
    {
        $this->tolakId = $id;
        $this->tolakTipe = $tipe;
        $this->alasanTolak = '';
        $this->tolakModalTitle = 'Tolak Kiriman dari Gudang Triplek Mentah';
        $this->tolakModalDescription = 'Kiriman ini akan ditolak dan tidak akan menambah stok Gudang Triplek Jadi.';
        $this->showTolakModal = true;
    }

    public function batalTolak(): void
    {
        $this->showTolakModal = false;
        $this->tolakId = null;
        $this->tolakTipe = '';
        $this->alasanTolak = '';
    }

    public function prosesTolak(): void
    {
        if (! $this->tolakId) {
            return;
        }

        if (trim($this->alasanTolak) === '') {
            Notification::make()->danger()
                ->title('Gagal Menolak')
                ->body('Alasan penolakan wajib diisi.')
                ->send();

            return;
        }

        try {
            DB::transaction(function () {
                $st = SerahTerimaHp::lockForUpdate()->findOrFail($this->tolakId);

                if ($st->diterima_gudang_at || $st->ditolak_oleh || $st->diterima_oleh !== '-') {
                    throw new \RuntimeException('Barang ini sudah diproses (diterima/ditolak) sebelumnya.');
                }

                $st->update([
                    'ditolak_oleh' => Auth::user()->name,
                    'alasan_tolak' => $this->alasanTolak,
                    'ditolak_at' => now(),
                    'status' => 'Ditolak',
                ]);
            });

            $this->batalTolak();
            unset($this->menungguDariMth, $this->riwayatDariMth);

            Notification::make()->warning()
                ->title('Barang Ditolak')
                ->body('Kiriman dari Gudang Triplek Mentah telah ditolak.')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title('Gagal Menolak')
                ->body($e->getMessage())
                ->send();
        }
    }

    // ─── WIP SANDING (agregat) ───────────────────────────────────────────────

    public function getWipSandingProperty(): float
    {
        $idMutasiSanding = TriplekJadiMutasiKeluar::where('tujuan', self::TUJUAN_SANDING)
            ->pluck('id');

        if ($idMutasiSanding->isEmpty()) {
            return 0.0;
        }

        $keluar = (float) HppTriplekJadiLog::query()
            ->whereRaw('LOWER(tipe_transaksi) = ?', ['keluar'])
            ->where('referensi_type', TriplekJadiMutasiKeluar::class)
            ->whereIn('referensi_id', $idMutasiSanding)
            ->sum('total_lembar');

        $idSerahDariSanding = SerahTerimaTriplekJadi::whereNotNull('id_hasil_sanding')
            ->pluck('id');

        $masuk = $idSerahDariSanding->isEmpty()
            ? 0.0
            : (float) HppTriplekJadiLog::query()
                ->whereRaw('LOWER(tipe_transaksi) = ?', ['masuk'])
                ->where('referensi_type', SerahTerimaTriplekJadi::class)
                ->whereIn('referensi_id', $idSerahDariSanding)
                ->sum('total_lembar');

        $baselineKeluar = 0.0;
        $baselineMasuk = 0.0;
        WipSandingReset::query()
            ->orderBy('spec_key')
            ->get(['spec_key', 'keluar_kumulatif', 'masuk_kumulatif'])
            ->groupBy('spec_key')
            ->each(function ($rows) use (&$baselineKeluar, &$baselineMasuk) {
                $baselineKeluar += (float) $rows->max('keluar_kumulatif');
                $baselineMasuk += (float) $rows->max('masuk_kumulatif');
            });

        $wip = ($keluar - $baselineKeluar) - ($masuk - $baselineMasuk);

        return max(0.0, $wip);
    }

    // ─── STOK (untuk dropdown pilih barang di modal keluar) ─────────────────

    public function getStokListProperty(): Collection
    {
        return StokTriplekJadi::with(['jenisKayu', 'lastLog'])
            ->where('stok_lembar', '>', 0)
            ->get()
            ->filter(function ($item) {
                if (trim($this->searchQuery) === '') {
                    return true;
                }
                $q = strtolower(trim($this->searchQuery));
                $namaKayu = strtolower((string) $item->jenisKayu?->nama_kayu);
                $dimensi = strtolower(($item->panjang + 0).'x'.($item->lebar + 0).'x'.($item->tebal + 0));

                return str_contains($namaKayu, $q)
                    || str_contains(strtolower((string) $item->kw_grade), $q)
                    || str_contains($dimensi, $q);
            })
            ->values();
    }

    /**
     * Opsi stok untuk pop up "Catat Barang Keluar" (komponen x-gudang.pilih-stok).
     * Sengaja TIDAK difilter oleh kolom search halaman — pencarian di pop up
     * dilakukan di sisi client (Alpine) per kata kunci: ukuran, jenis kayu, KW.
     */
    public function getStokOpsiKeluarProperty(): array
    {
        return StokTriplekJadi::with(['jenisKayu'])
            ->where('stok_lembar', '>', 0)
            ->get()
            ->sortBy([
                ['id_jenis_kayu', 'asc'],
                ['tebal', 'asc'],
                ['panjang', 'asc'],
                ['lebar', 'asc'],
                ['kw_grade', 'asc'],
            ])
            ->map(fn ($s) => [
                'id'   => (string) $s->id,
                'kayu' => (string) $s->jenisKayu?->nama_kayu,
                'kw'   => (string) $s->kw_grade,
                'p'    => (float) $s->panjang,
                'l'    => (float) $s->lebar,
                't'    => (float) $s->tebal,
                'sisa' => (int) $s->stok_lembar,
            ])
            ->values()
            ->all();
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

    public function prosesKeluar(): void
    {
        $totalLembar = array_sum(array_map('intval', $this->paletQuantities));

        if (! $this->selectedStokId || $totalLembar <= 0) {
            Notification::make()->danger()
                ->title('Input Gagal')
                ->body('Spesifikasi stok dan kuantitas palet wajib diisi.')
                ->send();

            return;
        }

        if (! in_array($this->tujuanKeluar, self::TUJUAN_OPTIONS, true)) {
            Notification::make()->danger()
                ->title('Input Gagal')
                ->body('Tujuan keluar tidak valid. Pilih Produksi Nyusup, Gudang Satu, Produksi Sanding, Produksi Hotpress, atau Gudang Triplek Mentah.')
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($totalLembar) {
                $stok = StokTriplekJadi::lockForUpdate()->findOrFail($this->selectedStokId);

                $tersedia = (int) $stok->stok_lembar - $this->pendingLembarMth($stok);

                if ($totalLembar > $tersedia) {
                    throw new \Exception("Sisa stok tidak mencukupi. Tersedia (setelah dikurangi kiriman ke Gudang Triplek Mentah yang menunggu diterima): {$tersedia} lembar.");
                }

                $mutasi = TriplekJadiMutasiKeluar::create([
                    'id_jenis_kayu' => $stok->id_jenis_kayu,
                    'panjang' => $stok->panjang,
                    'lebar' => $stok->lebar,
                    'tebal' => $stok->tebal,
                    'kw_grade' => $stok->kw_grade,
                    'jumlah_palet' => count($this->paletQuantities),
                    'stok_lembar' => $totalLembar,
                    'stok_kubikasi' => $this->hitungKubikasi($stok->panjang, $stok->lebar, $stok->tebal, $totalLembar),
                    'tujuan' => $this->tujuanKeluar,
                    'dikeluarkan_by' => Auth::id(),
                    'keterangan' => trim($this->keteranganKeluar) !== '' ? trim($this->keteranganKeluar) : null,
                    'status' => TriplekJadiMutasiKeluar::STATUS_DIKIRIM,
                ]);

                foreach ($this->paletQuantities as $index => $qtyRaw) {
                    $qty = intval($qtyRaw);
                    if ($qty <= 0) {
                        continue;
                    }

                    TriplekJadiMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'nomor_palet' => $index + 1,
                        'jumlah_lembar' => $qty,
                    ]);
                }

                if ($this->tujuanKeluar === self::TUJUAN_SANDING) {
                    SerahTerimaHp::create([
                        'id_triplek_mutasi_keluar' => $mutasi->id,
                        'tujuan' => 'sanding',
                        'diserahkan_oleh' => Auth::user()?->name ?? 'System',
                        'diterima_oleh' => '-',
                        'status' => 'Serah ke Sanding',
                    ]);
                } elseif ($this->tujuanKeluar === self::TUJUAN_HOTPRESS) {
                    // Hotpress: tidak perlu baris antrean terpisah (ditarik oleh
                    // VIEW serah_terima_masuk_hp). Sengaja tidak ada create().
                } elseif ($this->tujuanKeluar === self::TUJUAN_MTH) {
                    SerahTerimaHp::create([
                        'id_triplek_mutasi_keluar' => $mutasi->id,
                        'tujuan' => TransferTriplekJadiKeMthService::TUJUAN, // 'triplek_mth'
                        'diserahkan_oleh' => Auth::user()?->name ?? 'System',
                        'diterima_oleh' => '-',
                        'status' => 'Serah ke Gudang Triplek Mentah',
                    ]);
                } else {
                    SerahTerimaGudangSatu::create([
                        'id_triplek_mutasi_keluar' => $mutasi->id,
                        'tujuan' => 'triplek_jadi',
                        'diserahkan_oleh' => Auth::user()?->name ?? 'System',
                        'diterima_oleh' => '-',
                        'status' => 'Menunggu',
                    ]);
                }
            });

            $this->selectedStokId = null;
            $this->jumlahPalet = 1;
            $this->paletQuantities = [0 => ''];
            $this->tujuanKeluar = self::TUJUAN_NYUSUP;
            $this->keteranganKeluar = '';
            $this->showFormKeluarModal = false;

            Notification::make()->success()
                ->title('Mutasi Keluar Dicatat')
                ->body("{$totalLembar} lembar tercatat dikirim ke tujuan. Stok akan terpotong setelah dikonfirmasi diterima.")
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
                $query->whereHas('jenisKayu', fn ($qr) => $qr->whereRaw('LOWER(nama_kayu) LIKE ?', ["%{$q}%"]))
                    ->orWhereRaw('LOWER(kw_grade) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(tujuan) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(keterangan) LIKE ?', ["%{$q}%"]);
            });
        }

        return $query->get()->map(function ($rk) {
            $rk->bisa_diedit = $this->bisaDiedit($rk);

            return $rk;
        });
    }
}
