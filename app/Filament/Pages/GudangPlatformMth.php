<?php

namespace App\Filament\Pages;

use App\Models\ModalSanding;
use App\Models\PlatformMthMutasiKeluar;
use App\Models\SerahTerimaHp;
use App\Models\StokPlatformMth;
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

    // Untuk saat ini tujuan Platform Mentah selalu ke Produksi Sanding.
    public string $tujuanKeluar = 'Sanding';

    // Modal Edit Riwayat Keluar
    public bool $showEditKeluarModal = false;

    public ?int $editKeluarId = null;

    public $editKuantitas = '';

    public function hitungKubikasi(float $p, float $l, float $t, ?int $lembar): float
    {
        return ($p * $l * $t * ($lembar ?? 0)) / 10000000;
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

    // ─── BARANG KELUAR ────────────────────────────────────────────────────────

    /**
     * Keluarkan barang dari stok Platform Mentah menuju Produksi Sanding.
     * Header mutasi dibuat di sini, sekaligus baris SerahTerimaHp berstatus
     * "menunggu" (diterima_oleh = '-'). Stok TIDAK dipotong sekarang — baru
     * dipotong nanti oleh proses Produksi Sanding saat barang ini benar-benar
     * dipakai/diselesaikan.
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

        try {
            DB::transaction(function () use ($qty) {
                $stok = StokPlatformMth::lockForUpdate()->findOrFail($this->selectedStokId);

                if ($qty > (int) $stok->stok_lembar) {
                    throw new \Exception('Sisa stok tidak mencukupi. Tersedia: '.$stok->stok_lembar.' lembar.');
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
                    'tujuan' => 'Sanding',
                    'dikeluarkan_by' => $user?->id,
                    'keterangan' => trim($this->keteranganKeluar) !== '' ? trim($this->keteranganKeluar) : null,
                ]);

                SerahTerimaHp::create([
                    'id_platform_mth_mutasi_keluar' => $mutasi->id,
                    'tujuan' => 'sanding',
                    'diserahkan_oleh' => $user?->name ?? 'System',
                    'diterima_oleh' => '-',
                ]);
            });

            // Reset form
            $this->selectedStokId = null;
            $this->kuantitas = '';
            $this->keteranganKeluar = '';
            $this->showFormKeluarModal = false;

            unset($this->riwayatKeluar);

            Notification::make()->success()
                ->title('Mutasi Keluar Dicatat')
                ->body("{$qty} lembar tercatat dikirim ke Produksi Sanding. Stok akan terpotong setelah barang diproses di Sanding.")
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
                    ->orWhereRaw('LOWER(keterangan) LIKE ?', ["%{$q}%"]);
            });
        }

        return $query->get()->map(function ($mk) {
            $st = $mk->serahTerimaHp;

            $sudahDiterima = $st !== null && ! $st->isMenunggu();
            $sudahTerpakai = $st !== null && ModalSanding::where('id_serah_terima_hp', $st->id)->exists();

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
                ->body('Mutasi ini sudah diterima di Produksi Sanding, rincian tidak bisa diubah lagi.')
                ->send();

            return;
        }

        if ($st !== null && ModalSanding::where('id_serah_terima_hp', $st->id)->exists()) {
            Notification::make()
                ->danger()
                ->title('Tidak Bisa Diedit')
                ->body('Barang ini sudah mulai dipakai di Produksi Sanding, rincian tidak bisa diubah lagi.')
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

                // 🔒 Re-cek race condition
                if ($st !== null && ! $st->isMenunggu()) {
                    throw new \Exception('Mutasi ini sudah diterima di Produksi Sanding, tidak bisa diedit lagi.');
                }

                if ($st !== null && ModalSanding::where('id_serah_terima_hp', $st->id)->exists()) {
                    throw new \Exception('Barang ini sudah mulai dipakai di Produksi Sanding, tidak bisa diedit lagi.');
                }

                // Validasi sisa stok fisik masih cukup untuk kuantitas baru
                $stok = StokPlatformMth::where('id_jenis_kayu', $mutasi->id_jenis_kayu)
                    ->where('panjang', $mutasi->panjang)
                    ->where('lebar', $mutasi->lebar)
                    ->where('tebal', $mutasi->tebal)
                    ->where('kw_grade', $mutasi->kw_grade)
                    ->lockForUpdate()
                    ->first();

                if (! $stok || $qty > (int) $stok->stok_lembar) {
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
