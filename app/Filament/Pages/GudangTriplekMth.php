<?php

namespace App\Filament\Pages;

use App\Models\MasukGrajiTriplek;
use App\Models\SerahTerimaHp;
use App\Models\StokTriplekMth;
use App\Models\TriplekMthMutasiKeluar;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class GudangTriplekMth extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.gudang-triplek-mth';

    protected static ?string $navigationLabel = 'Gudang Triplek Mentah';

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static ?string $title = 'Gudang Triplek Mentah';

    protected static ?int $navigationSort = 20;

    public string $search = '';             // search dropdown stok di form

    public string $keluarSearchQuery = '';  // search riwayat keluar

    // ── Form Barang Keluar ──
    public bool $showFormKeluarModal = false;

    public ?int $selectedStokId = null;     // id baris stok_triplek_mth

    public $kuantitas = '';

    public string $keteranganKeluar = '';

    // Tujuan keluar: Produksi Graji Triplek atau Produksi Sanding.
    public string $tujuanKeluar = 'Graji Triplek';

    // key => label tampilan; key juga dipakai sebagai value kolom 'tujuan'.
    public array $opsiTujuan = [
        'Graji Triplek' => 'Produksi Graji Triplek',
        'Sanding' => 'Produksi Sanding',
    ];

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
        return StokTriplekMth::with(['jenisKayu'])
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
     * Keluarkan barang dari stok Triplek Mentah menuju Produksi Graji Triplek
     * atau Produksi Sanding (sesuai $tujuanKeluar). Header mutasi dibuat di sini,
     * sekaligus baris SerahTerimaHp berstatus "menunggu" (diterima_oleh = '-').
     * Stok TIDAK dipotong sekarang — baru dipotong nanti oleh proses produksi
     * tujuan saat barang ini benar-benar dipakai/diselesaikan.
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
                $stok = StokTriplekMth::lockForUpdate()->findOrFail($this->selectedStokId);

                if ($qty > (int) $stok->stok_lembar) {
                    throw new \Exception('Sisa stok tidak mencukupi. Tersedia: '.$stok->stok_lembar.' lembar.');
                }

                $user = Auth::user();

                $mutasi = TriplekMthMutasiKeluar::create([
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

                SerahTerimaHp::create([
                    'id_triplek_mth_mutasi_keluar' => $mutasi->id,
                    'tujuan' => $tujuanTerpilih === 'Sanding' ? 'sanding' : 'graji_triplek',
                    'diserahkan_oleh' => $user?->name ?? 'System',
                    'diterima_oleh' => '-',
                ]);
            });

            $labelTujuan = $this->opsiTujuan[$tujuanTerpilih];

            // Reset form
            $this->selectedStokId = null;
            $this->kuantitas = '';
            $this->keteranganKeluar = '';
            $this->tujuanKeluar = 'Graji Triplek';
            $this->showFormKeluarModal = false;

            unset($this->riwayatKeluar);

            Notification::make()->success()
                ->title('Mutasi Keluar Dicatat')
                ->body("{$qty} lembar tercatat dikirim ke {$labelTujuan}. Stok akan terpotong setelah barang diproses di tujuan.")
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
        $query = TriplekMthMutasiKeluar::with(['jenisKayu', 'operator', 'serahTerimaHp'])
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

    /**
     * Cek apakah barang sudah mulai dipakai di produksi tujuan.
     * Saat ini hanya tersedia pengecekan untuk tujuan Graji Triplek
     * (via model MasukGrajiTriplek). Tambahkan cabang serupa di sini
     * kalau nanti ada model "MasukProduksiSanding" dsb.
     */
    protected function cekSudahTerpakai(TriplekMthMutasiKeluar $mutasi, ?SerahTerimaHp $st): bool
    {
        if ($st === null) {
            return false;
        }

        return match ($mutasi->tujuan) {
            'Graji Triplek' => MasukGrajiTriplek::where('id_serah_terima_hp', $st->id)->exists(),
            // 'Sanding' => MasukProduksiSanding::where('id_serah_terima_hp', $st->id)->exists(),
            default => false,
        };
    }

    // ─── EDIT RIWAYAT KELUAR ───────────────────────────────────────────────

    public function editKeluar(int $id): void
    {
        $mutasi = TriplekMthMutasiKeluar::with('serahTerimaHp')->find($id);

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
                $mutasi = TriplekMthMutasiKeluar::with('serahTerimaHp')
                    ->where('id', $this->editKeluarId)
                    ->lockForUpdate()
                    ->first();

                if (! $mutasi) {
                    throw new \Exception('Data tidak ditemukan.');
                }

                $st = $mutasi->serahTerimaHp;

                // 🔒 Re-cek race condition
                if ($st !== null && ! $st->isMenunggu()) {
                    throw new \Exception('Mutasi ini sudah diterima di tujuan, tidak bisa diedit lagi.');
                }

                if ($this->cekSudahTerpakai($mutasi, $st)) {
                    throw new \Exception('Barang ini sudah mulai dipakai di tujuan, tidak bisa diedit lagi.');
                }

                // Validasi sisa stok fisik masih cukup untuk kuantitas baru
                $stok = StokTriplekMth::where('id_jenis_kayu', $mutasi->id_jenis_kayu)
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
