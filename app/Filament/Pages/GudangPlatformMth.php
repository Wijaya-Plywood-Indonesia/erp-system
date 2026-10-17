<?php

namespace App\Filament\Pages;

use App\Models\ModalSanding;
use App\Models\PlatformMthMutasiKeluar;
use App\Models\SerahTerimaHp;
use App\Models\StokPlatformMth;
use App\Services\TerimaGudangHpService;
use App\Services\TransferPlatformJadiKeMthService;
use App\Services\TransferPlatformMthKeJadiService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class GudangPlatformMth extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.gudang-platform-mth';

    protected static ?string $navigationLabel = 'Gudang Platform Mentah';

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static ?string $title = 'Gudang Platform Mentah';

    protected static ?int $navigationSort = 21;

    public string $search = '';             // search dropdown stok di form

    public string $keluarSearchQuery = '';  // search riwayat keluar

    // ── Form Barang Keluar ──
    public bool $showFormKeluarModal = false;

    public ?int $selectedStokId = null;     // id baris stok_platform_mth

    public $kuantitas = '';

    public string $keteranganKeluar = '';

    // Tujuan keluar: Produksi Sanding atau Gudang Platform Jadi.
    public string $tujuanKeluar = 'Sanding';

    // key => label tampilan; key juga dipakai sebagai value kolom 'tujuan'.
    public array $opsiTujuan = [
        'Sanding' => 'Produksi Sanding',
        'Platform Jadi' => 'Gudang Platform Jadi',
    ];

    // Modal Edit Riwayat Keluar
    public bool $showEditKeluarModal = false;

    public ?int $editKeluarId = null;

    public $editKuantitas = '';

    // ── Modal Tolak Serah Terima ──
    public bool $showTolakModal = false;

    public ?int $tolakId = null;

    public string $tolakTipe = '';

    public string $alasanTolak = '';

    public string $tolakModalTitle = 'Tolak Serah Terima';

    public string $tolakModalDescription = 'Kiriman ini akan ditolak dan tidak dimasukkan ke stok Gudang Platform Mentah.';

    public string $serahTerimaTab = 'aktif';

    public function hitungKubikasi(float $p, float $l, float $t, ?int $lembar): float
    {
        return ($p * $l * $t * ($lembar ?? 0)) / 10000000;
    }

    /**
     * Lembar yang sudah dicatat keluar ke Gudang Platform Jadi tetapi belum
     * diterima/ditolak. Stok fisik baru dipotong saat diterima, jadi angka ini
     * dikurangkan dari stok agar barang yang sama tidak bisa dikirim dua kali.
     *
     * Kiriman ke Sanding sengaja TIDAK dihitung, supaya perilaku Sanding yang
     * sekarang tidak berubah.
     */
    protected function pendingLembar(StokPlatformMth $stok, ?int $kecualiMutasiId = null): int
    {
        return (int) PlatformMthMutasiKeluar::query()
            ->where('tujuan', 'Platform Jadi')
            ->where('id_jenis_kayu', $stok->id_jenis_kayu)
            ->where('panjang', $stok->panjang)
            ->where('lebar', $stok->lebar)
            ->where('tebal', $stok->tebal)
            ->where('kw_grade', $stok->kw_grade)
            ->when($kecualiMutasiId, fn ($q) => $q->where('id', '!=', $kecualiMutasiId))
            ->whereHas('serahTerimaHp', fn ($q) => $q
                ->where('diterima_oleh', '-')
                ->whereNull('ditolak_oleh'))
            ->sum('stok_lembar');
    }

    /**
     * Cek apakah barang sudah mulai dipakai di produksi tujuan.
     * Tujuan 'Platform Jadi' dikunci lewat status diterima (isMenunggu),
     * jadi tidak perlu cabang di sini.
     */
    protected function cekSudahTerpakai(PlatformMthMutasiKeluar $mutasi, ?SerahTerimaHp $st): bool
    {
        if ($st === null) {
            return false;
        }

        return match ($mutasi->tujuan) {
            'Sanding' => ModalSanding::where('id_serah_terima_hp', $st->id)->exists(),
            default => false,
        };
    }

    // ─── DETAIL STOK (untuk dropdown pilih barang) ───────────────────────────

    public function getStokListProperty(): Collection
    {
        return StokPlatformMth::with(['jenisKayu'])
            ->where('stok_lembar', '>', 0)
            ->get()
            ->filter(function ($item) {
                if (trim($this->search) === '') {
                    return true;
                }
                $q = strtolower(trim($this->search));
                $kayu = strtolower((string) $item->jenisKayu?->nama_kayu);
                $grade = strtolower((string) $item->kw_grade);
                $dimensi = strtolower(($item->panjang + 0).'x'.($item->lebar + 0).'x'.($item->tebal + 0));

                return str_contains($kayu, $q)
                    || str_contains($grade, $q)
                    || str_contains($dimensi, $q);
            })
            ->sortBy([
                ['id_jenis_kayu', 'asc'],
                ['tebal', 'asc'],
                ['panjang', 'asc'],
                ['lebar', 'asc'],
                ['kw_grade', 'asc'],
            ])
            ->values();
    }

    // ─── MENUNGGU DITERIMA GUDANG (hasil hotpress) ───────────────────────────

    public function getMenungguTerimaProperty(): Collection
    {
        return SerahTerimaHp::with([
            'platformHasilHp.mesin',
            'platformHasilHp.barangSetengahJadi.jenisBarang',
            'platformHasilHp.barangSetengahJadi.grade',
            'platformHasilHp.barangSetengahJadi.ukuran',
        ])
            ->whereNotNull('id_platform_hasil_hp')
            ->where('diterima_oleh', '-')
            ->whereNull('ditolak_oleh')
            ->whereNull('diterima_gudang_at')
            ->latest()
            ->get();
    }

    public function getRiwayatTerimaProperty(): Collection
    {
        return SerahTerimaHp::with([
            'platformHasilHp.mesin',
            'platformHasilHp.barangSetengahJadi.jenisBarang',
            'platformHasilHp.barangSetengahJadi.grade',
            'platformHasilHp.barangSetengahJadi.ukuran',
        ])
            ->whereNotNull('id_platform_hasil_hp')
            ->whereNotNull('diterima_gudang_at')
            ->whereNull('ditolak_oleh')
            ->orderByDesc('diterima_gudang_at')
            ->limit(100)
            ->get();
    }

    public function terimaKeGudang(int $id): void
    {
        try {
            app(TerimaGudangHpService::class)->terimaPlatform($id);

            unset($this->menungguTerima, $this->riwayatTerima);

            Notification::make()->success()
                ->title('Stok Bertambah')
                ->body('Barang diterima di Gudang Platform Mentah.')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title('Gagal Menerima')
                ->body($e->getMessage())
                ->send();
        }
    }

    // ─── TERIMA DARI GUDANG PLATFORM JADI ────────────────────────────────────

    public function getMenungguDariJadiProperty(): Collection
    {
        return SerahTerimaHp::with('platformJadiMutasiKeluar.jenisBarang')
            ->where('tujuan', TransferPlatformJadiKeMthService::TUJUAN)
            ->whereNotNull('id_platform_jadi_mutasi_keluar')
            ->where('diterima_oleh', '-')
            ->whereNull('ditolak_oleh')
            ->whereNull('diterima_gudang_at')
            ->latest()
            ->get();
    }

    public function getRiwayatDariJadiProperty(): Collection
    {
        return SerahTerimaHp::with('platformJadiMutasiKeluar.jenisBarang')
            ->where('tujuan', TransferPlatformJadiKeMthService::TUJUAN)
            ->whereNotNull('diterima_gudang_at')
            ->orderByDesc('diterima_gudang_at')
            ->limit(50)
            ->get();
    }

    public function terimaDariJadi(int $id): void
    {
        try {
            app(TransferPlatformJadiKeMthService::class)->terima($id);

            unset($this->menungguDariJadi, $this->riwayatDariJadi, $this->stokList);

            Notification::make()->success()
                ->title('Stok Bertambah')
                ->body('Platform dari Gudang Platform Jadi diterima di Gudang Platform Mentah.')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title('Gagal Menerima')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function bukaModalTolak(int $id, string $tipe = 'hp'): void
    {
        $this->tolakId = $id;
        $this->tolakTipe = $tipe;
        $this->alasanTolak = '';
        if ($tipe === 'jadi') {
            $this->tolakModalTitle = 'Tolak Kiriman dari Gudang Platform Jadi';
            $this->tolakModalDescription = 'Kiriman ini akan ditolak dan tidak akan menambah stok Gudang Platform Mentah.';
        } else {
            $this->tolakModalTitle = 'Tolak Serah Terima Hotpress';
            $this->tolakModalDescription = 'Barang ini akan ditolak dan tidak dimasukkan ke stok Gudang Platform Mentah.';
        }
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
            unset($this->menungguTerima, $this->riwayatTerima, $this->menungguDariJadi, $this->riwayatDariJadi, $this->stokList);

            Notification::make()->warning()
                ->title('Barang Ditolak')
                ->body('Serah terima barang telah ditolak.')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title('Gagal Menolak')
                ->body($e->getMessage())
                ->send();
        }
    }

    // ─── BARANG KELUAR ────────────────────────────────────────────────────────

    /**
     * Keluarkan barang dari stok Platform Mentah menuju Produksi Sanding atau
     * Gudang Platform Jadi. Header mutasi dibuat di sini, sekaligus baris
     * SerahTerimaHp berstatus "menunggu" (diterima_oleh = '-').
     * Stok TIDAK dipotong sekarang; dipotong saat tujuan menerima/memproses.
     */
    public function prosesKeluar(): void
    {
        $qty = (int) $this->kuantitas;

        if (! $this->selectedStokId || $qty <= 0) {
            Notification::make()->danger()
                ->title('Input Gagal')
                ->body('Pilih stok dan isi kuantitas yang valid.')
                ->send();

            return;
        }

        if (! array_key_exists($this->tujuanKeluar, $this->opsiTujuan)) {
            Notification::make()->danger()
                ->title('Input Gagal')
                ->body('Tujuan tidak valid.')
                ->send();

            return;
        }

        $tujuanTerpilih = $this->tujuanKeluar;

        try {
            DB::transaction(function () use ($qty, $tujuanTerpilih) {
                $stok = StokPlatformMth::lockForUpdate()->findOrFail($this->selectedStokId);

                $tersedia = (int) $stok->stok_lembar - $this->pendingLembar($stok);

                if ($qty > $tersedia) {
                    throw new \Exception("Sisa stok tidak mencukupi. Tersedia (setelah dikurangi yang menunggu diterima): {$tersedia} lembar.");
                }

                $user = Auth::user();

                $mutasi = PlatformMthMutasiKeluar::create([
                    'id_jenis_kayu' => $stok->id_jenis_kayu,
                    'panjang' => $stok->panjang,
                    'lebar' => $stok->lebar,
                    'tebal' => $stok->tebal,
                    'kw_grade' => $stok->kw_grade,
                    'stok_lembar' => $qty,
                    'stok_kubikasi' => $this->hitungKubikasi($stok->panjang, $stok->lebar, $stok->tebal, $qty),
                    'tujuan' => $tujuanTerpilih,
                    'dikeluarkan_by' => $user?->id,
                    'keterangan' => trim($this->keteranganKeluar) !== '' ? trim($this->keteranganKeluar) : null,
                ]);

                $keGudangJadi = $tujuanTerpilih === 'Platform Jadi';

                $data = [
                    'id_platform_mth_mutasi_keluar' => $mutasi->id,
                    'tujuan' => $keGudangJadi ? TransferPlatformMthKeJadiService::TUJUAN : 'sanding',
                    'diserahkan_oleh' => $user?->name ?? 'System',
                    'diterima_oleh' => '-',
                ];

                if ($keGudangJadi) {
                    $data['status'] = 'Serah ke Gudang Platform Jadi';
                }

                SerahTerimaHp::create($data);
            });

            $labelTujuan = $this->opsiTujuan[$tujuanTerpilih];

            // Reset form
            $this->selectedStokId = null;
            $this->kuantitas = '';
            $this->keteranganKeluar = '';
            $this->tujuanKeluar = 'Sanding';
            $this->showFormKeluarModal = false;

            unset($this->riwayatKeluar);

            Notification::make()->success()
                ->title('Mutasi Keluar Dicatat')
                ->body("{$qty} lembar tercatat dikirim ke {$labelTujuan}. Stok akan terpotong setelah diterima/diproses di tujuan.")
                ->send();
        } catch (\Exception $e) {
            Notification::make()->danger()
                ->title('Gagal Mengeluarkan Barang')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function getRiwayatKeluarProperty(): Collection
    {
        $query = PlatformMthMutasiKeluar::with(['jenisKayu', 'operator', 'serahTerimaHp'])
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

        return $query->get()->map(function ($mk) {
            $st = $mk->serahTerimaHp;

            $sudahDiterima = $st !== null && ! $st->isMenunggu();
            $sudahTerpakai = $this->cekSudahTerpakai($mk, $st);

            $mk->status_label = is_null($st) ? '-' : $st->label_status;
            $mk->bisa_diedit = ! $sudahDiterima && ! $sudahTerpakai;

            return $mk;
        });
    }

    // ─── EDIT RIWAYAT KELUAR ───────────────────────────────────────────────

    public function editKeluar(int $id): void
    {
        $mutasi = PlatformMthMutasiKeluar::with('serahTerimaHp')->find($id);

        if (! $mutasi) {
            Notification::make()->danger()->title('Data tidak ditemukan')->send();

            return;
        }

        $st = $mutasi->serahTerimaHp;

        if ($st !== null && ! $st->isMenunggu()) {
            Notification::make()
                ->danger()
                ->title('Tidak Bisa Diedit')
                ->body('Mutasi ini sudah diterima di tujuan, rincian tidak bisa diubah lagi.')
                ->send();

            return;
        }

        if ($this->cekSudahTerpakai($mutasi, $st)) {
            Notification::make()
                ->danger()
                ->title('Tidak Bisa Diedit')
                ->body('Barang ini sudah mulai dipakai di tujuan, rincian tidak bisa diubah lagi.')
                ->send();

            return;
        }

        $this->editKeluarId = $mutasi->id;
        $this->editKuantitas = (string) $mutasi->stok_lembar;

        $this->showEditKeluarModal = true;
    }

    public function cancelEditKeluar(): void
    {
        $this->showEditKeluarModal = false;
        $this->editKeluarId = null;
    }

    public function updateKeluar(): void
    {
        if (! $this->editKeluarId) {
            return;
        }

        $qty = (int) $this->editKuantitas;

        if ($qty <= 0) {
            Notification::make()->danger()->title('Input Gagal')->body('Kuantitas wajib diisi.')->send();

            return;
        }

        try {
            DB::transaction(function () use ($qty) {
                $mutasi = PlatformMthMutasiKeluar::with('serahTerimaHp')
                    ->where('id', $this->editKeluarId)
                    ->lockForUpdate()
                    ->first();

                if (! $mutasi) {
                    throw new \Exception('Data tidak ditemukan.');
                }

                $st = $mutasi->serahTerimaHp;

                // Re-cek race condition
                if ($st !== null && ! $st->isMenunggu()) {
                    throw new \Exception('Mutasi ini sudah diterima di tujuan, tidak bisa diedit lagi.');
                }

                if ($this->cekSudahTerpakai($mutasi, $st)) {
                    throw new \Exception('Barang ini sudah mulai dipakai di tujuan, tidak bisa diedit lagi.');
                }

                $stok = StokPlatformMth::where('id_jenis_kayu', $mutasi->id_jenis_kayu)
                    ->where('panjang', $mutasi->panjang)
                    ->where('lebar', $mutasi->lebar)
                    ->where('tebal', $mutasi->tebal)
                    ->where('kw_grade', $mutasi->kw_grade)
                    ->lockForUpdate()
                    ->first();

                // Stok belum dipotong untuk mutasi ini, jadi bandingkan dengan
                // stok fisik dikurangi kiriman LAIN ke Platform Jadi yang masih menunggu.
                if (! $stok || $qty > ((int) $stok->stok_lembar - $this->pendingLembar($stok, $mutasi->id))) {
                    throw new \Exception('Sisa stok fisik di gudang tidak mencukupi untuk kuantitas baru.');
                }

                $mutasi->update([
                    'stok_lembar' => $qty,
                    'stok_kubikasi' => $this->hitungKubikasi($mutasi->panjang, $mutasi->lebar, $mutasi->tebal, $qty),
                ]);
            });

            unset($this->riwayatKeluar);

            $this->showEditKeluarModal = false;
            $this->editKeluarId = null;

            Notification::make()
                ->success()
                ->title('✓ Rincian Diperbarui')
                ->body("Kuantitas berhasil diubah menjadi {$qty} lembar.")
                ->send();
        } catch (\Exception $e) {
            Notification::make()->danger()->title('Gagal Memperbarui')->body($e->getMessage())->send();
        }
    }
}
