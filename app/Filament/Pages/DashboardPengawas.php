<?php

namespace App\Filament\Pages;

use App\Services\DashboardPengawas\DashboardPengawasService;
use App\Services\NewRekapAbsensiPegawaiService;
use App\Services\PotonganGajiService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class DashboardPengawas extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Dashboard Pengawas';
    protected static ?string $title = 'Dashboard Pengawas';
    protected static ?string $slug = 'dashboard-pengawas';

    protected string $view = 'filament.pages.dashboard-pengawas';

    public static function canAccess(): bool
    {
        return true;
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    #[Url(keep: true)]
    public ?string $tanggal = null;

    public function mount(): void
    {
        $this->tanggal ??= now()->format('Y-m-d');
    }

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->schema([
            DatePicker::make('tanggal')
                ->label('Filter Tanggal')
                ->default(now())
                ->live(),
        ]);
    }

    /**
     * Data ringkasan per sumber (produksi, serah terima, total pegawai).
     * Diambil dari DashboardPengawasService yang sudah ada (tidak diubah).
     */
    protected function getViewData(): array
    {
        $service = app(DashboardPengawasService::class);
        $user    = auth()->user();

        return [
            'dashboardData' => $service->getDataForUser($user, $this->tanggal),
        ];
    }

    /**
     * Rekap absensi lengkap (checklog finger + potongan), sama persis
     * seperti NewAbsensi::getRekap() — difilter per sumber produksi
     * agar pengawas hanya melihat data bagiannya.
     *
     * @return Collection<string, Collection>  key = label sumber, value = rows
     */
    public function getRekapPerSumber(): Collection
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        // Ambil rekap lengkap dari pipeline yang sama dengan NewAbsensi
        $rekapService   = app(NewRekapAbsensiPegawaiService::class);
        $potonganService = app(PotonganGajiService::class);

        $rekap = $rekapService->getRekap($tanggal);

        $potonganMap = $potonganService->getPotonganMap($tanggal);
        $rekap = $rekap->map(function ($row) use ($potonganService, $potonganMap) {
            $row['potongan'] = $potonganService->resolvePotongan(
                $potonganMap,
                $row['kode_pegawai'] ?? null
            );
            return $row;
        });

        $dashboardService = app(DashboardPengawasService::class);
        $dashboardSources = $dashboardService->getSources();
        
        $hasil = collect();

        foreach ($dashboardSources as $source) {
            $sectionLabel = $source->getLabel();
            
            $rows = $rekap->filter(function ($row) use ($sectionLabel) {
                $labels = (array) ($row['sumber_label'] ?? []);
                return collect($labels)->contains(
                    fn($l) => str_starts_with($l, $sectionLabel) || str_starts_with($sectionLabel, $l)
                );
            })->values();

            if ($rows->isNotEmpty()) {
                $hasil->put($sectionLabel, $rows);
            }
        }

        return $hasil;
    }
}
