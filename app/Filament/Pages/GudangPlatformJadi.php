<?php

namespace App\Filament\Pages;

use App\Models\HasilSanding;
use App\Models\HppPlatformJadiLog;
use App\Models\HppPlatformMthLog;
use App\Models\JenisKayu;
use App\Models\PlatformJadiMutasiKeluar;
use App\Models\PlatformJadiMutasiKeluarPalet;
use App\Models\SerahTerimaPlatformJadi;
use App\Models\StokPlatformJadi;
use App\Models\StokPlatformMth;
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

    public string $tujuanKeluar = 'Hotpress';

    public string $keteranganKeluar = '';

    protected $queryString = ['activeTab'];

    public function hitungKubikasi(float $p, float $l, float $t, ?int $lembar): float
    {
        return ($p * $l * $t * ($lembar ?? 0)) / 10000000;
    }

    // Modal Edit Riwayat Keluar
    public bool $showEditKeluarModal = false;

    public ?int $editKeluarId = null;

    public $editJumlahPalet = 1;

    public array $editPaletQuantities = [0 => ''];

    public function editKeluar(int $id): void
    {
        $mutasi = PlatformJadiMutasiKeluar::with('palets.bahanHotpress')->find($id);

        if (! $mutasi) {
            Notification::make()->danger()->title('Data tidak ditemukan')->send();

            return;
        }

        if (! is_null($mutasi->id_produksi_hp) || ! is_null($mutasi->diterima_by)) {
            Notification::make()
                ->danger()
                ->title('Tidak Bisa Diedit')
                ->body('Mutasi ini sudah diterima di sisi tujuan, rincian tidak bisa diubah lagi.')
                ->send();

            return;
        }

        $sudahAdaYangTerserap = $mutasi->palets->contains(
            fn ($p) => ! is_null($p->diterima_by) || $p->bahanHotpress->sum('isi') > 0
        );

        if ($sudahAdaYangTerserap) {
            Notification::make()
                ->danger()
                ->title('Tidak Bisa Diedit')
                ->body('Sebagian palet pada mutasi ini sudah mulai dipakai di produksi Hotpress, rincian tidak bisa diubah lagi.')
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

                // 🔒 Re-cek race condition
                if (! is_null($mutasi->id_produksi_hp) || ! is_null($mutasi->diterima_by)) {
                    throw new \Exception('Mutasi ini sudah diterima di sisi tujuan, tidak bisa diedit lagi.');
                }

                $sudahAdaYangTerserap = $mutasi->palets->contains(
                    fn ($p) => ! is_null($p->diterima_by) || $p->bahanHotpress->sum('isi') > 0
                );

                if ($sudahAdaYangTerserap) {
                    throw new \Exception('Sebagian palet sudah mulai dipakai di produksi, tidak bisa diedit lagi.');
                }

                // Validasi sisa stok fisik masih cukup untuk kuantitas baru
                $stok = StokPlatformJadi::where('id_jenis_barang', $mutasi->id_jenis_barang)
                    ->where('panjang', $mutasi->panjang)
                    ->where('lebar', $mutasi->lebar)
                    ->where('tebal', $mutasi->tebal)
                    ->where('kw_grade', $mutasi->kw_grade)
                    ->lockForUpdate()
                    ->first();

                if (! $stok || $totalLembar > (int) $stok->stok_lembar) {
                    throw new \Exception('Sisa stok fisik di gudang tidak mencukupi untuk kuantitas baru.');
                }

                $mutasi->update([
                    'jumlah_palet' => count($this->editPaletQuantities),
                    'stok_lembar' => $totalLembar,
                    'stok_kubikasi' => $this->hitungKubikasi($mutasi->panjang, $mutasi->lebar, $mutasi->tebal, $totalLembar),
                ]);

                // Aman dihapus & dibuat ulang karena sudah dipastikan tidak ada
                // satu pun palet yang punya BahanHotpress (FK id_mutasi_keluar_platform)
                // atau diterima_by terisi — lihat guard di atas.
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
     * Terima satu palet hasil sanding ke Gudang Platform Jadi:
     * upsert stok jadi + tulis log + catat serah terima + KURANGI stok mentah.
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

                // ── KURANGI STOK PLATFORM MENTAH (boleh minus, crosscheck) ──
                $this->kurangiStokPlatformMth($bsj, $p, $l, $t, $kw, $qty, $hs);
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

    /**
     * Kurangi Stok Platform Mentah sesuai barang & qty yang diterima.
     * Map jenis_barang -> jenis_kayu by nama. Boleh minus (crosscheck).
     */
    protected function kurangiStokPlatformMth($bsj, float $p, float $l, float $t, ?string $kw, int $qty, HasilSanding $hs): void
    {
        $namaJenis = $bsj->jenisBarang?->nama_jenis_barang;

        $jenisKayu = $namaJenis
            ? JenisKayu::where('nama_kayu', $namaJenis)->first()
            : null;

        if (! $jenisKayu) {
            throw new \Exception("Jenis kayu \"{$namaJenis}\" tidak ditemukan di master Jenis Kayu. Samakan namanya dulu.");
        }

        $stokMth = StokPlatformMth::where('id_jenis_kayu', $jenisKayu->id)
            ->where('panjang', $p)->where('lebar', $l)->where('tebal', $t)
            ->where('kw_grade', $kw)
            ->lockForUpdate()
            ->first();

        if (! $stokMth) {
            // baris belum ada -> buat mulai 0 supaya bisa minus
            $stokMth = StokPlatformMth::create([
                'id_jenis_kayu' => $jenisKayu->id,
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

        // Keperluan pemakaian bahan mentah (ubah di sini bila perlu)
        $keperluan = 'Sanding';
        $namaPemakai = Auth::user()?->name ?? 'System';

        $kubikasi = $this->hitungKubikasi($p, $l, $t, $qty);

        $before = [
            'lembar' => (float) $stokMth->stok_lembar,
            'kubikasi' => (float) $stokMth->stok_kubikasi,
            'nilai' => (float) $stokMth->nilai_stok,
        ];

        $after = [
            'lembar' => $before['lembar'] - $qty,
            'kubikasi' => round($before['kubikasi'] - $kubikasi, 6),
            'nilai' => $before['nilai'], // HPP diabaikan
        ];

        $log = HppPlatformMthLog::create([
            'id_jenis_kayu' => $jenisKayu->id,
            'panjang' => $p,
            'lebar' => $l,
            'tebal' => $t,
            'kw_grade' => $kw,
            'tanggal' => now(),
            'tipe_transaksi' => 'keluar',
            'referensi_type' => HasilSanding::class,
            'referensi_id' => $hs->id,
            'keterangan' => "Dipakai produksi: {$keperluan} - Palet {$hs->no_palet} (oleh {$namaPemakai})",
            'total_lembar' => $qty,
            'total_kubikasi' => $kubikasi,
            'hpp_pekerja' => 0,
            'hpp_bahan_penolong' => 0,
            'hpp_average' => (float) $stokMth->hpp_average,
            'nilai_stok' => $after['nilai'],
            'stok_lembar_before' => $before['lembar'],
            'stok_kubikasi_before' => $before['kubikasi'],
            'nilai_stok_before' => $before['nilai'],
            'stok_lembar_after' => $after['lembar'],
            'stok_kubikasi_after' => $after['kubikasi'],
            'nilai_stok_after' => $after['nilai'],
        ]);

        $stokMth->update([
            'stok_lembar' => $after['lembar'],
            'stok_kubikasi' => $after['kubikasi'],
            'nilai_stok' => $after['nilai'],
            'id_last_log' => $log->id,
        ]);
    }

    // ─── VENEER/PLATFORM KELUAR ──────────────────────────────────────────────

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
     * Keluarkan barang dari stok: header mutasi + rincian palet, lalu
     * SATU BARIS LOG PER PALET (snapshot berantai), potong stok.
     */
    public function prosesKeluar(): void
    {
        $totalLembar = array_sum(array_map('intval', $this->paletQuantities));

        if (! $this->selectedStokId || $totalLembar <= 0 || trim($this->tujuanKeluar) === '') {
            Notification::make()->danger()
                ->title('Input Gagal')
                ->body('Pilih stok, isi kuantitas palet, dan tujuan keluar wajib diisi.')
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($totalLembar) {
                $stok = StokPlatformJadi::lockForUpdate()->findOrFail($this->selectedStokId);

                if ($totalLembar > (int) $stok->stok_lembar) {
                    throw new \Exception('Sisa stok tidak mencukupi. Tersedia: '.$stok->stok_lembar.' lembar.');
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

                // Catatan: stok TIDAK dipotong di sini. Baris palet di bawah ini
                // hanya mencatat "niat kirim" per palet — angka jumlah_lembar
                // inilah yang nanti dipakai untuk memotong stok saat diterima.
                foreach ($this->paletQuantities as $index => $qtyRaw) {
                    $qty = intval($qtyRaw);

                    PlatformJadiMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'nomor_palet' => $index + 1,
                        'jumlah_lembar' => $qty,
                    ]);
                }
            });

            // Reset form
            $this->selectedStokId = null;
            $this->jumlahPalet = 1;
            $this->paletQuantities = [0 => ''];
            $this->tujuanKeluar = 'Hotpress';
            $this->keteranganKeluar = '';
            $this->showFormKeluarModal = false;

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
            $rk->bisa_diedit = is_null($rk->id_produksi_hp)
                && is_null($rk->diterima_by)
                && ! $rk->palets->contains(fn ($p) => ! is_null($p->diterima_by) || $p->bahanHotpress->sum('isi') > 0);

            return $rk;
        });
    }
}
