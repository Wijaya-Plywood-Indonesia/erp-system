<?php

namespace App\Filament\Pages;

use App\Models\HppVeneerBasahSummary;
use App\Models\SerahTerimaPivot;
use App\Models\Ukuran;
use App\Models\VeneerBasahMutasi;
use App\Services\GudangVeneerBasahService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class GudangVeneerBasah extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.gudang-veneer-basah';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static ?string $navigationLabel = 'Gudang Veneer Basah';

    protected static ?string $title = 'Gudang Veneer Basah';

    public string $serahTerimaTab = 'aktif';

    public string $keluarSearchQuery = '';

    public bool $showFormKeluarModal = false;

    public string $searchVeneer = '';

    public bool $showVeneerList = false;

    public ?int $selectedSummaryId = null;

    public $jumlahPalet = 1;

    public array $paletQuantities = [0 => ''];

    public string $tujuanKeluar = 'dryer';

    public string $keteranganKeluar = '';

    public bool $showEditKeluarModal = false;

    public ?int $editKeluarId = null;

    public $editJumlahPalet = 1;

    public array $editPaletQuantities = [0 => ''];

    public function getSerahTerimaProperty(): Collection
    {
        return SerahTerimaPivot::with(['detailHasilPalet.ukuran', 'detailHasilPalet.penggunaanLahan.jenisKayu'])
            ->where('tipe', 'rotary')
            ->where('diterima_oleh', '-')
            ->latest()
            ->get();
    }

    public function getRiwayatSerahTerimaProperty(): Collection
    {
        return SerahTerimaPivot::with(['detailHasilPalet.ukuran', 'detailHasilPalet.penggunaanLahan.jenisKayu'])
            ->where('tipe', 'gudang_veneer_basah')
            ->latest()
            ->limit(50)
            ->get();
    }

    public function terimaRotary(int $id): void
    {
        try {
            app(GudangVeneerBasahService::class)->terimaDariRotary($id);

            Notification::make()
                ->title('Palet Berhasil Diterima Gudang')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menerima')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getRiwayatKeluarProperty(): Collection
    {
        $query = VeneerBasahMutasi::with(['details.jenisKayu', 'details.ukuran', 'details.serahTerima'])
            ->latest();

        if (trim($this->keluarSearchQuery) !== '') {
            $q = strtolower(trim($this->keluarSearchQuery));
            $query->where(function ($qq) use ($q) {
                $qq->whereHas('details.jenisKayu', fn ($j) => $j->whereRaw('LOWER(nama_kayu) LIKE ?', ["%{$q}%"]))
                    ->orWhereHas('details', fn ($d) => $d->whereRaw('LOWER(kw) LIKE ?', ["%{$q}%"]))
                    ->orWhereRaw('LOWER(keterangan) LIKE ?', ["%{$q}%"]);
            });
        }

        return $query->limit(50)->get()->map(function ($mutasi) {
            $mutasi->bisa_diedit = ! $mutasi->details->contains(
                fn ($d) => $d->serahTerima && $d->serahTerima->status !== 'Menunggu'
            );

            return $mutasi;
        });
    }

    /**
     * Daftar SEMUA stok veneer basah yang tersedia (stok <> 0), dikirim
     * sekali ke Alpine.js sebagai array JS — filtering/pencarian dilakukan
     * 100% di client (tanpa round-trip ke server), sama seperti pola
     * yang dipakai Gudang Veneer Kering.
     */
    public function getVeneerStokAllProperty(): Collection
    {
        return HppVeneerBasahSummary::with('jenisKayu')
            ->where('stok_lembar', '<>', 0)
            ->orderByDesc('tebal')
            ->get();
    }

    public function openFormKeluar(): void
    {
        $this->reset([
            'searchVeneer', 'selectedSummaryId', 'showVeneerList', 'jumlahPalet', 'paletQuantities',
            'tujuanKeluar', 'keteranganKeluar',
        ]);
        $this->jumlahPalet = 1;
        $this->paletQuantities = [0 => ''];
        $this->tujuanKeluar = 'dryer';
        $this->showFormKeluarModal = true;
    }

    public function cancelFormKeluar(): void
    {
        $this->showFormKeluarModal = false;
    }

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

        if (! $this->selectedSummaryId || $totalLembar <= 0) {
            Notification::make()
                ->danger()
                ->title('Input Gagal')
                ->body('Pilih veneer dan isi kuantitas palet terlebih dahulu.')
                ->send();

            return;
        }

        $summary = HppVeneerBasahSummary::find($this->selectedSummaryId);

        if (! $summary) {
            Notification::make()->danger()->title('Data Veneer Tidak Ditemukan')->send();

            return;
        }

        $ukuran = Ukuran::where('panjang', $summary->panjang)
            ->where('lebar', $summary->lebar)
            ->where('tebal', $summary->tebal)
            ->first();

        if (! $ukuran) {
            Notification::make()
                ->danger()
                ->title('Ukuran Tidak Ditemukan')
                ->body('Kombinasi dimensi pada stok ini belum ada di master Ukuran.')
                ->send();

            return;
        }

        // ✅ FIX: sebelumnya semua palet digabung jadi 1 item (1 baris di sisi
        // produksi). Sekarang tiap palet dengan isi > 0 jadi 1 item sendiri,
        // supaya sisi produksi (Dryer/Kedi) menerima & menampilkan per-palet,
        // persis seperti jumlah palet yang dicatat di Gudang.
        $items = [];
        foreach ($this->paletQuantities as $index => $qty) {
            $qtyPalet = intval($qty);
            if ($qtyPalet <= 0) {
                continue;
            }

            $items[] = [
                'id_jenis_kayu' => $summary->id_jenis_kayu,
                'id_ukuran' => $ukuran->id,
                'kw' => $summary->kw,
                'qty_lembar' => $qtyPalet,
                'no_palet' => $index + 1,
            ];
        }

        if (empty($items)) {
            Notification::make()
                ->danger()
                ->title('Input Gagal')
                ->body('Isi minimal satu palet dengan kuantitas lebih dari 0.')
                ->send();

            return;
        }

        try {
            app(GudangVeneerBasahService::class)->buatMutasiKeluar(
                items: $items,
                tujuan: $this->tujuanKeluar,
                keterangan: trim($this->keteranganKeluar) !== '' ? trim($this->keteranganKeluar) : null,
            );

            $this->showFormKeluarModal = false;

            Notification::make()
                ->title('Barang Keluar Tercatat')
                ->body("{$totalLembar} lembar veneer basah (".count($items)." palet) menunggu diterima di sisi tujuan. Stok gudang belum berkurang.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal Membuat Mutasi')->body($e->getMessage())->danger()->send();
        }
    }

    // ─── EDIT RINCIAN PALET (selama belum ada yang diterima/ditolak) ────────

    public function editKeluar(int $id): void
    {
        $mutasi = VeneerBasahMutasi::with(['details.serahTerima'])->find($id);

        if (! $mutasi) {
            Notification::make()->danger()->title('Data tidak ditemukan')->send();

            return;
        }

        $sudahAdaYangDiterima = $mutasi->details->contains(
            fn ($d) => $d->serahTerima && $d->serahTerima->status !== 'Menunggu'
        );

        if ($sudahAdaYangDiterima) {
            Notification::make()
                ->danger()
                ->title('Tidak Bisa Diedit')
                ->body('Sebagian atau seluruh palet pada mutasi ini sudah diterima/ditolak di sisi produksi, rincian tidak bisa diubah lagi. Silakan buat catatan barang keluar baru.')
                ->send();

            return;
        }

        $this->editKeluarId = $mutasi->id;

        $palet = $mutasi->details->sortBy('no_palet')->pluck('qty_lembar')->map('intval')->values()->toArray();
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
            Notification::make()
                ->danger()
                ->title('Input Gagal')
                ->body('Kuantitas palet wajib diisi.')
                ->send();

            return;
        }

        try {
            $mutasi = VeneerBasahMutasi::findOrFail($this->editKeluarId);

            $paletBaru = [];
            foreach ($this->editPaletQuantities as $index => $qty) {
                $paletBaru[] = [
                    'qty_lembar' => intval($qty),
                    'no_palet' => $index + 1,
                ];
            }

            app(GudangVeneerBasahService::class)->updateMutasiKeluar($mutasi, $paletBaru);

            $this->showEditKeluarModal = false;
            $this->editKeluarId = null;
            unset($this->riwayatKeluar);

            Notification::make()
                ->success()
                ->title('✓ Rincian Diperbarui')
                ->body("Rincian palet berhasil diubah menjadi {$totalLembar} lembar.")
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Memperbarui')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function formatKodePalet($pivot): string
    {
        return $pivot?->detailHasilPalet?->kode_palet ?? '-';
    }

    public function formatJumlahLembar($pivot): int
    {
        return (int) ($pivot?->detailHasilPalet?->total_lembar ?? 0);
    }
}