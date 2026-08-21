<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanPotSikuExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ProduksiPotSiku;
use App\Filament\Pages\LaporanPotSiku\Transformers\PotSikuDataMap;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanPotSiku extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-pot-siku';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Pot Siku';
    protected static ?int $navigationSort = 6;
    protected static bool $shouldRegisterNavigation = false;

    public $dataSiku = [];
    public $tanggal = null;
    public bool $isLoading = false;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->loadAllData()),

            Action::make('exportExcel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn() => $this->exportExcel())
                ->visible(fn() => !empty($this->dataSiku)),
        ];
    }

    public function exportExcel()
    {
        try {
            if (empty($this->dataSiku)) {
                throw new Exception('Tidak ada data untuk diunduh.');
            }

            $tglFile = Carbon::parse($this->tanggal)->format('d-m-Y');

            return Excel::download(
                new LaporanPotSikuExport($this->dataSiku, $this->tanggal),
                "laporan-pot-siku-{$tglFile}.xlsx"
            );
        } catch (Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Export Excel')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function getListeners(): array
    {
        return [
            'echo:production.pot_siku,.ProductionUpdated' => 'loadAllData',
        ];
    }

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');

        $existsToday = ProduksiPotSiku::whereDate('tanggal_produksi', $this->tanggal)->exists();
        if (!$existsToday) {
            $lastDate = ProduksiPotSiku::latest('tanggal_produksi')->value('tanggal_produksi');
            if ($lastDate) {
                $this->tanggal = $lastDate instanceof Carbon ? $lastDate->format('Y-m-d') : $lastDate;
            }
        }

        $this->form->fill(['tanggal' => $this->tanggal]);
        $this->loadAllData();
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('tanggal')
                ->label('Pilih Tanggal Laporan')
                ->native(false)
                ->format('Y-m-d')
                ->displayFormat('d/m/Y')
                ->live()
                ->closeOnDateSelection()
                ->afterStateUpdated(function ($state) {
                    $this->tanggal = $state;
                    $this->loadAllData();
                })
                ->required()
                ->maxDate(now())
                ->default(now())
                ->suffixIcon('heroicon-o-calendar')
                ->suffixIconColor('primary'),
        ];
    }

    public function onTanggalUpdated($state)
    {
        $this->tanggal = $state;
        $this->loadAllData();
    }

    /**
     * Ambil semua produksi Pot Siku di tanggal terpilih, transform TIAP
     * produksi lewat PotSikuDataMap::make() (target per ukuran dari DB,
     * capaian global per individu — lihat README Join untuk konsep
     * rumusnya, Pot Siku pakai pola yang sama tapi per-orang).
     */
    public function loadAllData()
    {
        $this->isLoading = true;

        $tanggal = now()->format('Y-m-d');
        if ($this->tanggal) {
            try {
                if ($this->tanggal instanceof Carbon) {
                    $tanggal = $this->tanggal->format('Y-m-d');
                } elseif (is_string($this->tanggal)) {
                    if (str_contains($this->tanggal, '/')) {
                        $tanggal = Carbon::createFromFormat('d/m/Y', $this->tanggal)->format('Y-m-d');
                    } else {
                        $tanggal = Carbon::parse($this->tanggal)->format('Y-m-d');
                    }
                }
            } catch (Exception $e) {
                Log::error('Error parsing date in loadAllData LaporanPotSiku: ' . $e->getMessage());
            }
        }

        $produksiList = ProduksiPotSiku::with([
            'pegawaiPotSiku.pegawai',
            'detailBarangDikerjakanPotSiku.jenisKayu',
            'detailBarangDikerjakanPotSiku.ukuran',
            'detailBarangDikerjakanPotSiku.pegawaiPotSiku.pegawai',
            'validasiTerakhir',
        ])
            ->whereDate('tanggal_produksi', $tanggal)
            ->get();

        if ($produksiList->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Data Tidak Ditemukan')
                ->body('Tidak ada data Produksi Pot Siku untuk tanggal ' . Carbon::parse($tanggal)->format('d/m/Y'))
                ->send();
        } else {
            Notification::make()
                ->success()
                ->title('Data Ditemukan')
                ->body('Ditemukan ' . $produksiList->count() . ' data produksi.')
                ->send();
        }

        // PENTING: setiap produksi ditransform lewat PotSikuDataMap::make(),
        // bukan dibangun manual di sini lagi (target flat 300 sudah dibuang).
        $this->dataSiku = $produksiList
            ->map(fn (ProduksiPotSiku $produksi) => PotSikuDataMap::make($produksi))
            ->values()
            ->toArray();

        $this->isLoading = false;
    }
}