<?php

namespace App\Filament\Pages;

use App\Models\HasilSanding;
use App\Models\HppPlatformJadiLog;
use App\Models\PlatformJadiMutasiKeluar;
use App\Models\PlatformJadiMutasiKeluarPalet;
use App\Models\SerahTerimaHp;
use App\Models\SerahTerimaPlatformJadi;
use App\Models\StokPlatformJadi;
use App\Services\TransferPlatformJadiKeMthService;
use App\Services\TransferPlatformMthKeJadiService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class GudangPlatformJadi extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.gudang-platform-jadi';

    protected static ?string $navigationLabel = 'Gudang Platform Jadi';

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static ?string $title = 'Gudang Platform Jadi';

    protected static ?int $navigationSort = 22;

    // Tujuan keluar yang diizinkan — SATU sumber kebenaran.
    public const TUJUAN_HOTPRESS = 'Hotpress';

    public const TUJUAN_JUAL = 'Jual';

    public const TUJUAN_MTH = 'Gudang Platform Mentah';

    public const TUJUAN_OPTIONS = [
        self::TUJUAN_HOTPRESS,
        self::TUJUAN_JUAL,
        self::TUJUAN_MTH,
    ];

    // Tab aktif: 'masuk' (Serah Terima) / 'keluar'
    public string $activeTab = 'masuk';

    public string $search = '';             // search detail stok

    public string $antreanSearch = '';      // search antrean serah terima

    public string $keluarSearchQuery = '';  // search riwayat keluar

    // ── Form Barang Keluar ──
    public bool $showFormKeluarModal = false;

    public ?int $selectedStokId = null;     // id baris stok_platform_jadi

    public $jumlahPalet = 1;

    public array $paletQuantities = [0 => ''];

    public string $tujuanKeluar = self::TUJUAN_HOTPRESS;

    public string $keteranganKeluar = '';

    // ── Modal Edit Riwayat Keluar ──
    public bool $showEditKeluarModal = false;

    public ?int $editKeluarId = null;

    public $editJumlahPalet = 1;

    public array $editPaletQuantities = [0 => ''];

    // ── Modal Tolak Serah Terima ──
    public bool $showTolakModal = false;

    public ?int $tolakId = null;

    public string $tolakTipe = '';

    public string $alasanTolak = '';

    public string $tolakModalTitle = 'Tolak Kiriman Platform';

    public string $tolakModalDescription = 'Kiriman ini akan ditolak dan tidak dimasukkan ke stok Gudang Platform Jadi.';

    protected $queryString = ['activeTab'];

    public function hitungKubikasi(float $p, float $l, float $t, ?int $lembar): float
    {
        return ($p * $l * $t * ($lembar ?? 0)) / 10000000;
    }

    /**
     * Lembar yang sudah dicatat keluar ke Gudang Platform Mentah tetapi belum
     * diterima/ditolak. Stok fisik baru dipotong saat diterima, jadi angka ini
     * dikurangkan dari stok agar barang yang sama tidak bisa dikirim dua kali.
     */
    protected function pendingLembarMth(StokPlatformJadi $stok, ?int $kecualiMutasiId = null): int
    {
        $idMutasiPending = SerahTerimaHp::query()
            ->where('tujuan', TransferPlatformJadiKeMthService::TUJUAN)
            ->whereNotNull('id_platform_jadi_mutasi_keluar')
            ->where('diterima_oleh', '-')
            ->whereNull('ditolak_oleh')
            ->whereNull('diterima_gudang_at')
            ->select('id_platform_jadi_mutasi_keluar');

        return (int) PlatformJadiMutasiKeluar::query()
            ->whereIn('id', $idMutasiPending)
            ->where('id_jenis_barang', $stok->id_jenis_barang)
            ->where('panjang', $stok->panjang)
            ->where('lebar', $stok->lebar)
            ->where('tebal', $stok->tebal)
            ->where('kw_grade', $stok->kw_grade)
            ->when($kecualiMutasiId, fn ($q) => $q->where('id', '!=', $kecualiMutasiId))
            ->sum('stok_lembar');
    }

    /**
     * Satu sumber kebenaran untuk "boleh diedit"
     * (dipakai editKeluar, updateKeluar, dan riwayat keluar).
     */
    protected function bisaDiedit(PlatformJadiMutasiKeluar $mutasi): bool
    {
        if (! is_null($mutasi->id_produksi_hp) || ! is_null($mutasi->diterima_by)) {
            return false;
        }

        $sudahAdaYangTerserap = $mutasi->palets->contains(
            fn ($p) => ! is_null($p->diterima_by) || $p->bahanHotpress->sum('isi') > 0
        );

        if ($sudahAdaYangTerserap) {
            return false;
        }

        $sudahDiterimaMth = SerahTerimaHp::where('id_platform_jadi_mutasi_keluar', $mutasi->id)
            ->where('tujuan', TransferPlatformJadiKeMthService::TUJUAN)
            ->where('diterima_oleh', '!=', '-')
            ->exists();

        return ! $sudahDiterimaMth;
    }

    // ─── EDIT RIWAYAT KELUAR ─────────────────────────────────────────────────

    public function editKeluar(int $id): void
    {
        $mutasi = PlatformJadiMutasiKeluar::with('palets.bahanHotpress')->find($id);

        if (! $mutasi) {
            Notification::make()->danger()->title('Data tidak ditemukan')->send();

            return;
        }

        if (! $this->bisaDiedit($mutasi)) {
            Notification::make()
                ->danger()
                ->title('Tidak Bisa Diedit')
                ->body('Mutasi ini sudah diterima/dipakai di sisi tujuan, rincian tidak bisa diubah lagi.')
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

    /**
     * 💾 SIMPAN PERUBAHAN RINCIAN KELUAR
     */
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
                $mutasi = PlatformJadiMutasiKeluar::with('palets.bahanHotpress')
                    ->where('id', $this->editKeluarId)
                    ->lockForUpdate()
                    ->first();

                if (! $mutasi) {
                    throw new \Exception('Data tidak ditemukan.');
                }

                // Re-cek race condition
                if (! $this->bisaDiedit($mutasi)) {
                    throw new \Exception('Mutasi ini sudah diterima/dipakai di sisi tujuan, tidak bisa diedit lagi.');
                }

                $stok = StokPlatformJadi::where('id_jenis_barang', $mutasi->id_jenis_barang)
                    ->where('panjang', $mutasi->panjang)
                    ->where('lebar', $mutasi->lebar)
                    ->where('tebal', $mutasi->tebal)
                    ->where('kw_grade', $mutasi->kw_grade)
                    ->lockForUpdate()
                    ->first();

                // Stok belum dipotong untuk mutasi ini, jadi bandingkan dengan stok fisik
                // dikurangi kiriman LAIN ke Platform Mentah yang masih menunggu.
                if (! $stok || $totalLembar > ((int) $stok->stok_lembar - $this->pendingLembarMth($stok, $mutasi->id))) {
                    throw new \Exception('Sisa stok fisik di gudang tidak mencukupi untuk kuantitas baru.');
                }

                $mutasi->update([
                    'jumlah_palet' => count($this->editPaletQuantities),
                    'stok_lembar' => $totalLembar,
                    'stok_kubikasi' => $this->hitungKubikasi($mutasi->panjang, $mutasi->lebar, $mutasi->tebal, $totalLembar),
                ]);

                // Aman dihapus & dibuat ulang karena sudah dipastikan tidak ada
                // satu pun palet yang punya BahanHotpress atau diterima_by terisi.
                $mutasi->palets()->delete();

                foreach ($this->editPaletQuantities as $index => $qty) {
                    $qtyPalet = intval($qty);
                    if ($qtyPalet <= 0) {
                        continue;
                    }

                    PlatformJadiMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'nomor_palet' => $index + 1,
                        'jumlah_lembar' => $qtyPalet,
                    ]);
                }
            });

            unset($this->riwayatKeluar);

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

    // ─── DETAIL STOK ──────────────────────────────────────────────────────────

    public function getStokListProperty(): Collection
    {
        return StokPlatformJadi::with(['jenisBarang'])
            ->where('stok_lembar', '>', 0)
            ->get()
            ->filter(function ($item) {
                if (trim($this->search) === '') {
                    return true;
                }
                $q = strtolower(trim($this->search));
                $barang = strtolower((string) $item->jenisBarang?->nama_jenis_barang);
                $grade = strtolower((string) $item->kw_grade);
                $dimensi = strtolower(($item->panjang + 0).'x'.($item->lebar + 0).'x'.($item->tebal + 0));

                return str_contains($barang, $q)
                    || str_contains($grade, $q)
                    || str_contains($dimensi, $q);
            })
            ->sortBy([
                ['id_jenis_barang', 'asc'],
                ['tebal', 'asc'],
                ['panjang', 'asc'],
                ['lebar', 'asc'],
                ['kw_grade', 'asc'],
            ])
            ->values();
    }


    /**
     * Opsi stok untuk pop up "Catat Barang Keluar" (komponen x-gudang.pilih-stok).
     * Sengaja TIDAK difilter oleh kolom search halaman — pencarian di pop up
     * dilakukan di sisi client (Alpine) per kata kunci: ukuran, jenis kayu, KW.
     */
    public function getStokOpsiKeluarProperty(): array
    {
        return StokPlatformJadi::with(['jenisBarang'])
            ->where('stok_lembar', '>', 0)
            ->get()
            ->sortBy([
                ['id_jenis_barang', 'asc'],
                ['tebal', 'asc'],
                ['panjang', 'asc'],
                ['lebar', 'asc'],
                ['kw_grade', 'asc'],
            ])
            ->map(fn ($s) => [
                'id'   => (string) $s->id,
                'kayu' => (string) $s->jenisBarang?->nama_jenis_barang,
                'kw'   => (string) $s->kw_grade,
                'p'    => (float) $s->panjang,
                'l'    => (float) $s->lebar,
                't'    => (float) $s->tebal,
                'sisa' => (int) $s->stok_lembar,
            ])
            ->values()
            ->all();
    }

    // ─── TERIMA DARI GUDANG PLATFORM MENTAH ──────────────────────────────────

    public function getMenungguDariMthProperty(): Collection
    {
        return SerahTerimaHp::with('platformMthMutasiKeluar.jenisKayu')
            ->where('tujuan', TransferPlatformMthKeJadiService::TUJUAN)
            ->whereNotNull('id_platform_mth_mutasi_keluar')
            ->where('diterima_oleh', '-')
            ->whereNull('ditolak_oleh')
            ->whereNull('diterima_gudang_at')
            ->latest()
            ->get();
    }

    public function getRiwayatDariMthProperty(): Collection
    {
        return SerahTerimaHp::with('platformMthMutasiKeluar.jenisKayu')
            ->where('tujuan', TransferPlatformMthKeJadiService::TUJUAN)
            ->whereNotNull('diterima_gudang_at')
            ->orderByDesc('diterima_gudang_at')
            ->limit(50)
            ->get();
    }

    public function terimaDariMth(int $id): void
    {
        try {
            app(TransferPlatformMthKeJadiService::class)->terima($id);

            unset($this->menungguDariMth, $this->riwayatDariMth, $this->stokList);

            Notification::make()->success()
                ->title('Stok Bertambah')
                ->body('Platform dari Gudang Platform Mentah diterima di Gudang Platform Jadi.')
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

        if ($tipe === 'sanding') {
            $this->tolakModalTitle = 'Tolak Palet Hasil Sanding';
            $this->tolakModalDescription = 'Palet ini akan ditolak dan dikembalikan ke antrean Sanding.';
        } else {
            $this->tolakModalTitle = 'Tolak Kiriman dari Gudang Platform Mentah';
            $this->tolakModalDescription = 'Kiriman ini akan ditolak dan tidak akan menambah stok Gudang Platform Jadi.';
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
            if ($this->tolakTipe === 'sanding') {
                DB::transaction(function () {
                    $hs = HasilSanding::lockForUpdate()->findOrFail($this->tolakId);

                    if (SerahTerimaPlatformJadi::where('id_hasil_sanding', $hs->id)->exists()) {
                        throw new \Exception('Palet ini sudah diterima, tidak bisa ditolak.');
                    }

                    $hs->update([
                        'tujuan_serah' => null,
                        'diserahkan_at' => null,
                        'diserahkan_oleh' => null,
                    ]);
                });

                $this->batalTolak();
                unset($this->antrean);

                Notification::make()->warning()
                    ->title('Barang Ditolak')
                    ->body('Barang berhasil dikembalikan ke antrean Sanding.')
                    ->send();
            } else {
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
                unset($this->menungguDariMth, $this->riwayatDariMth, $this->stokList);

                Notification::make()->warning()
                    ->title('Barang Ditolak')
                    ->body('Kiriman dari Gudang Platform Mentah telah ditolak.')
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title('Gagal Menolak')
                ->body($e->getMessage())
                ->send();
        }
    }

    }

    // ─── SERAH TERIMA (dari Hasil Sanding) ───────────────────────────────────

    /**
     * Gabungan antrean: HasilSanding yang BELUM diterima (paling atas,
     * terbaru dulu) + yang SUDAH diterima (di bawah, dari tabel serah terima).
     */
    public function getAntreanProperty(): Collection
    {
        $diterimaIds = SerahTerimaPlatformJadi::pluck('id_hasil_sanding');

        $rows = HasilSanding::with([
            'barangSetengahJadi.ukuran',
            'barangSetengahJadi.jenisBarang',
            'barangSetengahJadi.grade',
            'produksiSanding',
        ])
            ->where('tujuan_serah', 'platform_jadi')
            ->whereNotNull('diserahkan_at')
            ->get()
            ->map(function (HasilSanding $hs) use ($diterimaIds) {
                $bsj = $hs->barangSetengahJadi;
                $ukuran = $bsj?->ukuran;

                $st = $diterimaIds->contains($hs->id)
                    ? SerahTerimaPlatformJadi::with('penerima')->where('id_hasil_sanding', $hs->id)->first()
                    : null;

                return (object) [
                    'id' => $hs->id,
                    'no_palet' => $hs->no_palet,
                    'jenis_barang' => $bsj?->jenisBarang?->nama_jenis_barang ?? '-',
                    'panjang' => (float) ($ukuran?->panjang ?? 0),
                    'lebar' => (float) ($ukuran?->lebar ?? 0),
                    'tebal' => (float) ($ukuran?->tebal ?? 0),
                    'grade' => $bsj?->grade?->nama_grade ?? '-',
                    'jumlah' => (int) $hs->kuantitas,
                    'created_at' => $hs->created_at,
                    'sudah' => $st !== null,
                    'penerima' => $st?->penerima?->name,
                    'diterima_at' => $st?->diterima_at,
                ];
            });

        if (trim($this->antreanSearch) !== '') {
            $q = strtolower(trim($this->antreanSearch));
            $rows = $rows->filter(
                fn ($r) => str_contains(strtolower((string) $r->jenis_barang), $q)
                    || str_contains(strtolower((string) $r->grade), $q)
                    || str_contains(strtolower(($r->panjang + 0).'x'.($r->lebar + 0).'x'.($r->tebal + 0)), $q)
                    || str_contains(strtolower('palet '.$r->no_palet), $q)
            );
        }

        // Belum diterima di atas (terbaru dulu), sudah diterima di bawah.
        return $rows
            ->sortByDesc('created_at')
            ->sortBy(fn ($r) => $r->sudah ? 1 : 0)
            ->values();
    }

    /**
     * Tolak satu palet hasil sanding: kembalikan ke antrean Sanding.
     */
    public function tolak(int $idHasilSanding): void
    {
        try {
            DB::transaction(function () use ($idHasilSanding) {
                $hs = HasilSanding::lockForUpdate()->findOrFail($idHasilSanding);

                if (SerahTerimaPlatformJadi::where('id_hasil_sanding', $hs->id)->exists()) {
                    throw new \Exception('Palet ini sudah diterima, tidak bisa ditolak.');
                }

                $hs->update([
                    'tujuan_serah' => null,
                    'diserahkan_at' => null,
                    'diserahkan_oleh' => null,
                ]);
            });

            Notification::make()->success()
                ->title('Barang Ditolak')
                ->body('Barang berhasil dikembalikan ke antrean Sanding.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()->danger()
                ->title('Gagal Menolak Barang')
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * Terima satu palet hasil sanding ke Gudang Platform Jadi:
     * upsert stok jadi + tulis log + catat serah terima.
     * HPP sementara diabaikan (0).
     */
    public function terima(int $idHasilSanding): void
    {
        try {
            DB::transaction(function () use ($idHasilSanding) {
                $hs = HasilSanding::with([
                    'barangSetengahJadi.ukuran',
                    'barangSetengahJadi.grade',
                    'barangSetengahJadi.jenisBarang',
                ])->lockForUpdate()->findOrFail($idHasilSanding);

                if (SerahTerimaPlatformJadi::where('id_hasil_sanding', $hs->id)->exists()) {
                    throw new \Exception('Palet ini sudah pernah diterima.');
                }

                $bsj = $hs->barangSetengahJadi;
                $ukuran = $bsj?->ukuran;
                if (! $bsj || ! $ukuran) {
                    throw new \Exception('Data barang setengah jadi / ukuran tidak lengkap.');
                }

                $idJenisBarang = (int) $bsj->id_jenis_barang;
                $p = (float) $ukuran->panjang;
                $l = (float) $ukuran->lebar;
                $t = (float) $ukuran->tebal;
                $kw = $bsj->grade?->nama_grade;

                $qty = (int) $hs->kuantitas;
                $kubikasi = $this->hitungKubikasi($p, $l, $t, $qty);

                $user = Auth::user();
                $userName = $user?->name ?? 'System';

                $stok = StokPlatformJadi::where('id_jenis_barang', $idJenisBarang)
                    ->where('panjang', $p)->where('lebar', $l)->where('tebal', $t)
                    ->where('kw_grade', $kw)
                    ->lockForUpdate()
                    ->first();

                if (! $stok) {
                    $stok = StokPlatformJadi::create([
                        'id_jenis_barang' => $idJenisBarang,
                        'panjang' => $p,
                        'lebar' => $l,
                        'tebal' => $t,
                        'kw_grade' => $kw,
                        'stok_lembar' => 0,
                        'stok_kubikasi' => 0,
                        'nilai_stok' => 0,
                        'hpp_average' => 0,
                    ]);
                }

                $before = [
                    'lembar' => (int) $stok->stok_lembar,
                    'kubikasi' => (float) $stok->stok_kubikasi,
                    'nilai' => (float) $stok->nilai_stok,
                ];

                $after = [
                    'lembar' => $before['lembar'] + $qty,
                    'kubikasi' => round($before['kubikasi'] + $kubikasi, 6),
                    'nilai' => $before['nilai'], // HPP diabaikan dulu -> nilai tidak berubah
                ];

                $log = HppPlatformJadiLog::create([
                    'id_jenis_barang' => $idJenisBarang,
                    'panjang' => $p,
                    'lebar' => $l,
                    'tebal' => $t,
                    'kw_grade' => $kw,
                    'tanggal' => now(),
                    'tipe_transaksi' => 'masuk',
                    'referensi_type' => HasilSanding::class,
                    'referensi_id' => $hs->id,
                    'total_lembar' => $qty,
                    'total_kubikasi' => $kubikasi,
                    'hpp_pekerja' => 0,
                    'hpp_bahan_penolong' => 0,
                    'hpp_average' => (float) $stok->hpp_average,
                    'nilai_stok' => 0,
                    'stok_lembar_before' => $before['lembar'],
                    'stok_kubikasi_before' => $before['kubikasi'],
                    'nilai_stok_before' => $before['nilai'],
                    'stok_lembar_after' => $after['lembar'],
                    'stok_kubikasi_after' => $after['kubikasi'],
                    'nilai_stok_after' => $after['nilai'],
                    'keterangan' => 'Serah terima Hasil Sanding Palet '.$hs->no_palet
                        .' | Diterima: '.$userName,
                ]);

                $stok->update([
                    'stok_lembar' => $after['lembar'],
                    'stok_kubikasi' => $after['kubikasi'],
                    'nilai_stok' => $after['nilai'],
                    'id_last_log' => $log->id,
                ]);

                SerahTerimaPlatformJadi::create([
                    'id_hasil_sanding' => $hs->id,
                    'diterima_by' => $user?->id,
                    'diterima_at' => now(),
                ]);

                // Stok platform mentah sudah dikurangi saat Mutasi Keluar / diterima Sanding
                // (tidak ada pemotongan ganda di sini).
            });

            Notification::make()->success()
                ->title('Barang diterima ke Gudang Platform Jadi.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()->danger()
                ->title('Gagal menerima barang')
                ->body($e->getMessage())
                ->send();
        }
    }

    // ─── BARANG KELUAR ────────────────────────────────────────────────────────

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
     * Keluarkan barang dari stok: header mutasi + rincian palet.
     * Stok TIDAK dipotong di sini; untuk tujuan Gudang Platform Mentah dipotong
     * saat tujuan menerima (lihat TransferPlatformJadiKeMthService).
     */
    public function prosesKeluar(): void
    {
        $totalLembar = array_sum(array_map('intval', $this->paletQuantities));

        if (! $this->selectedStokId || $totalLembar <= 0) {
            Notification::make()->danger()
                ->title('Input Gagal')
                ->body('Pilih stok dan isi kuantitas palet.')
                ->send();

            return;
        }

        if (! in_array($this->tujuanKeluar, self::TUJUAN_OPTIONS, true)) {
            Notification::make()->danger()
                ->title('Input Gagal')
                ->body('Tujuan keluar tidak valid. Pilih Hotpress, Jual, atau Gudang Platform Mentah.')
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($totalLembar) {
                $stok = StokPlatformJadi::lockForUpdate()->findOrFail($this->selectedStokId);

                $tersedia = (int) $stok->stok_lembar - $this->pendingLembarMth($stok);

                if ($totalLembar > $tersedia) {
                    throw new \Exception("Sisa stok tidak mencukupi. Tersedia (setelah dikurangi kiriman ke Gudang Platform Mentah yang menunggu diterima): {$tersedia} lembar.");
                }

                $user = Auth::user();

                $mutasi = PlatformJadiMutasiKeluar::create([
                    'id_jenis_barang' => $stok->id_jenis_barang,
                    'panjang' => $stok->panjang,
                    'lebar' => $stok->lebar,
                    'tebal' => $stok->tebal,
                    'kw_grade' => $stok->kw_grade,
                    'jumlah_palet' => count($this->paletQuantities),
                    'stok_lembar' => $totalLembar,
                    'stok_kubikasi' => $this->hitungKubikasi($stok->panjang, $stok->lebar, $stok->tebal, $totalLembar),
                    'tujuan' => trim($this->tujuanKeluar),
                    'dikeluarkan_by' => $user?->id,
                    'keterangan' => trim($this->keteranganKeluar) !== '' ? trim($this->keteranganKeluar) : null,
                ]);

                foreach ($this->paletQuantities as $index => $qtyRaw) {
                    PlatformJadiMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'nomor_palet' => $index + 1,
                        'jumlah_lembar' => intval($qtyRaw),
                    ]);
                }

                if ($this->tujuanKeluar === self::TUJUAN_MTH) {
                    SerahTerimaHp::create([
                        'id_platform_jadi_mutasi_keluar' => $mutasi->id,
                        'tujuan' => TransferPlatformJadiKeMthService::TUJUAN,
                        'diserahkan_oleh' => $user?->name ?? 'System',
                        'diterima_oleh' => '-',
                        'status' => 'Serah ke Gudang Platform Mentah',
                    ]);
                }
            });

            // Reset form
            $this->selectedStokId = null;
            $this->jumlahPalet = 1;
            $this->paletQuantities = [0 => ''];
            $this->tujuanKeluar = self::TUJUAN_HOTPRESS;
            $this->keteranganKeluar = '';
            $this->showFormKeluarModal = false;

            unset($this->riwayatKeluar);

            Notification::make()->success()
                ->title('Mutasi Keluar Dicatat')
                ->body("{$totalLembar} lembar tercatat dikirim. Stok akan terpotong setelah barang dikonfirmasi diterima di tujuan.")
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
        $query = PlatformJadiMutasiKeluar::with(['jenisBarang', 'palets.bahanHotpress', 'operator'])
            ->orderByDesc('created_at');

        if (trim($this->keluarSearchQuery) !== '') {
            $q = strtolower(trim($this->keluarSearchQuery));
            $query->where(function ($query) use ($q) {
                $query->whereHas('jenisBarang', fn ($qr) => $qr->whereRaw('LOWER(nama_jenis_barang) LIKE ?', ["%{$q}%"]))
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
