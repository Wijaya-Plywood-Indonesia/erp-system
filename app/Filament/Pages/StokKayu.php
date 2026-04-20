<?php

namespace App\Filament\Pages;

use App\Models\HppAverageSummarie;
use App\Models\JenisKayu;
use App\Models\Lahan;
use App\Services\HppAverageService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use UnitEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class StokKayu extends Page
{
    protected string $view = 'filament.pages.stok-kayu';

    protected static ?string $navigationLabel = 'Stok Kayu';
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title          = 'Stok Kayu';
    protected static ?int    $navigationSort = 10;

    // Role untuk membuat super admin yang bisa akses untuk edit dan delete
    private const ROLE_ADMIN    = ['super_admin', 'Super Admin'];

    // ── State ──────────────────────────────────────────────────
    public ?int   $activeLahanId = null;
    public string $filterPanjang = '';
    public string $filterJenis   = '';
    public string $lahanSearch   = '';

    public function mount(): void
    {
        $this->activeLahanId = null;
    }


    // ─── ACTION EDIT ──────────────────────────────────────────

    /**
     * Mendefinisikan logika edit data untuk summary stok.
     * Dipanggil di Blade dengan passing ID record.
     */
    // ─── ACTION EDIT ──────────────────────────────────────────

    public function editStokAction(): Action
    {
        $isAdmin = Auth::user()?->hasAnyRole(self::ROLE_ADMIN) ?? false;

        return Action::make('editStok')
            ->label('Edit')
            ->icon('heroicon-m-pencil-square')
            ->color('warning')
            ->size('xs')
            ->visible($isAdmin)
            ->modalHeading(
                fn(array $arguments) => isset($arguments['id'])
                    ? 'Update Stok Lahan'
                    : 'Inisialisasi Stok Lahan Baru'
            )
            ->mountUsing(function (Schema $schema, array $arguments) {
                if (isset($arguments['id'])) {
                    $record = HppAverageSummarie::find($arguments['id']);
                    return $schema->fill($record?->toArray() ?? []);
                }

                return $schema->fill([
                    'id_lahan'      => $arguments['lahan_id'] ?? null,
                    'stok_batang'   => 0,
                    'stok_kubikasi' => 0,
                    'nilai_stok'    => 0,
                    'panjang'       => 130,
                ]);
            })
            ->form([
                Grid::make()
                    ->schema([
                        Select::make('id_lahan')
                            ->label('Lahan')
                            ->options(Lahan::pluck('kode_lahan', 'id'))
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Select::make('id_jenis_kayu')
                            ->label('Jenis Kayu')
                            ->options(JenisKayu::pluck('nama_kayu', 'id'))
                            ->searchable()
                            ->required(),

                        TextInput::make('panjang')
                            ->label('Panjang (cm)')
                            ->numeric()
                            ->required(),

                        TextInput::make('stok_batang')
                            ->label('Jumlah Batang')
                            ->numeric()
                            ->required(),

                        TextInput::make('stok_kubikasi')
                            ->label('Volume (m³)')
                            ->numeric()
                            ->step('0.0001')
                            ->required(),

                        TextInput::make('nilai_stok')
                            ->label('Total Poin')
                            ->numeric()
                            ->required(),
                    ])
            ])
            ->action(function (array $data, array $arguments) {
                $service = app(HppAverageService::class);

                // Simpan perubahan stok
                $record = HppAverageSummarie::updateOrCreate(
                    ['id' => $arguments['id'] ?? null],
                    $data
                );

                // Auto kalkulasi HPP Average
                if ($record && $record->stok_kubikasi > 0) {
                    $record->update([
                        'hpp_average' => round($record->nilai_stok / $record->stok_kubikasi, 2)
                    ]);
                } else {
                    $record->update(['hpp_average' => 0]);
                }

                // ✅ Sync TempatKayu otomatis setelah stok berubah
                $record->syncTempatKayu();

                Notification::make()
                    ->success()
                    ->title('Data Stok Berhasil Diperbarui')
                    ->body('Tempat Kayu telah diperbarui secara otomatis.')
                    ->send();
            });
    }

    public function deleteStokAction(): Action
    {
        $isAdmin = Auth::user()?->hasAnyRole(self::ROLE_ADMIN) ?? false;

        return Action::make('deleteStok')
            ->label('Hapus')
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->size('xs')
            ->requiresConfirmation()
            ->modalHeading('Hapus Data Stok?')
            ->modalDescription('Tindakan ini akan menghapus data ringkasan stok pada baris ini.')
            ->action(function (array $arguments) {
                if (! isset($arguments['id'])) return;

                $record = HppAverageSummarie::find($arguments['id']);

                if ($record) {
                    $lahanId = $record->id_lahan;

                    // Hapus record
                    $record->delete();

                    // Cek apakah masih ada stok lain untuk lahan ini
                    $remainingStok = HppAverageSummarie::where('id_lahan', $lahanId)
                        ->where('stok_batang', '>', 0)
                        ->exists();

                    if (!$remainingStok) {
                        // Hapus semua TempatKayu untuk lahan ini
                        \App\Models\TempatKayu::where('id_lahan', $lahanId)->delete();
                    } else {
                        // Update TempatKayu dengan stok terbaru
                        $service = app(HppAverageService::class);
                        $service->syncTempatKayuByLahan($lahanId);
                    }
                }

                Notification::make()
                    ->success()
                    ->title('Stok Berhasil Dihapus')
                    ->body('Tempat Kayu telah diperbarui secara otomatis.')
                    ->send();
            })
            ->visible(fn(array $arguments) => isset($arguments['id']) && $isAdmin);
    }

    // ── Computed: semua lahan ──────────────────────────────────
    public function getLahansProperty()
    {
        return Lahan::query()
            ->when(
                $this->lahanSearch,
                fn($q) =>
                $q->where('nama_lahan', 'like', "%{$this->lahanSearch}%")
                    ->orWhere('kode_lahan', 'like', "%{$this->lahanSearch}%")
            )
            ->get();
    }

    // ── Computed: lahan aktif (null = global) ──────────────────
    public function getActiveLahanProperty()
    {
        return $this->activeLahanId ? Lahan::find($this->activeLahanId) : null;
    }

    // ── Computed: ringkasan stok per lahan (untuk sidebar) ─────
    public function getStokPerLahanProperty()
    {
        return HppAverageSummarie::with('jenisKayu')
            ->whereNull('grade')
            ->where('stok_batang', '>', 0)
            ->get()
            ->groupBy('id_lahan')
            ->map(fn($rows) => [
                'btg'   => $rows->sum('stok_batang'),
                'jenis' => $rows->pluck('jenisKayu.nama_kayu')->filter()->unique()->sort()->values(),
            ]);
    }

    // ── Computed: baris stok (filter aware) ───────────────────
    public function getSummariesProperty()
    {
        return HppAverageSummarie::with(['lahan', 'jenisKayu'])
            ->whereNull('grade')
            ->when($this->activeLahanId, fn($q) => $q->where('id_lahan', $this->activeLahanId))
            ->when($this->filterPanjang, fn($q) => $q->where('panjang', $this->filterPanjang))
            ->when(
                $this->filterJenis,
                fn($q) =>
                $q->whereHas(
                    'jenisKayu',
                    fn($q2) =>
                    $q2->where('nama_kayu', $this->filterJenis)
                )
            )
            ->where('stok_batang', '>', 0)
            ->get();
    }

    // ── Computed: daftar panjang unik (untuk filter chip) ──────
    public function getPanjangListProperty()
    {
        return HppAverageSummarie::whereNull('grade')
            ->when($this->activeLahanId, fn($q) => $q->where('id_lahan', $this->activeLahanId))
            ->where('stok_batang', '>', 0)
            ->distinct()
            ->orderBy('panjang')
            ->pluck('panjang');
    }

    // ── Computed: daftar jenis kayu unik (untuk filter chip) ───
    public function getJenisListProperty()
    {
        return HppAverageSummarie::with('jenisKayu')
            ->whereNull('grade')
            ->when($this->activeLahanId, fn($q) => $q->where('id_lahan', $this->activeLahanId))
            ->where('stok_batang', '>', 0)
            ->get()
            ->pluck('jenisKayu.nama_kayu')
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    // ── Computed: summaries digroup per panjang ────────────────
    public function getGroupedSummariesProperty()
    {
        return $this->summaries
            ->groupBy('panjang')
            ->sortKeys();
    }

    // ── Computed: lahan yang memiliki stok per kombinasi ───────
    public function getLahanPerKombinasiProperty()
    {
        if ($this->activeLahanId) {
            return collect();
        }

        return HppAverageSummarie::with('lahan')
            ->whereNull('grade')
            ->where('stok_batang', '>', 0)
            ->get()
            ->groupBy(fn($r) => $r->id_jenis_kayu . '_' . $r->panjang)
            ->map(
                fn($rows) =>
                $rows->pluck('lahan.kode_lahan')->filter()->unique()->sort()->values()
            );
    }

    // ── Actions ────────────────────────────────────────────────
    public function selectLahan(?int $lahanId): void
    {
        $this->activeLahanId = $lahanId;
        $this->filterPanjang = '';
        $this->filterJenis   = '';
    }

    public function recalculate(): void
    {
        $service = app(HppAverageService::class);

        if (\App\Models\HppAverageLog::whereNull('grade')->count() === 0) {
            $service->seedFromNotaKayu();
        } else {
            $service->recalculateAll();
        }

        Notification::make()
            ->title('HPP Average berhasil dihitung ulang')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculate')
                ->label('Hitung Ulang HPP')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Hitung Ulang HPP Average?')
                ->modalDescription('Seluruh snapshot akan dihitung ulang dari awal. Pastikan semua nota kayu sudah benar.')
                ->action(fn() => $this->recalculate()),
        ];
    }

    public function getAllLahansWithStokProperty()
    {
        return Lahan::query()
            ->whereHas('summaries', fn($q) => $q->where('stok_batang', '>', 0))
            ->orderBy('kode_lahan')
            ->get();
    }

    // Tambahkan relasi detail ke summaries agar grouping di blade lebih ringan
    public function getSummariesByLahanProperty()
    {
        return $this->summaries->groupBy('id_lahan');
    }
}
