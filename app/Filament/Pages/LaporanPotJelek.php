<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Carbon\Carbon;

use BackedEnum;
use UnitEnum;

use Exception;
use Illuminate\Support\Facades\Log;

use App\Filament\Pages\LaporanPotJelek\Queries\LoadLaporanPotJelek;
use App\Filament\Pages\LaporanPotJelek\Transformers\PotJelekDataMap;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LaporanPotJelekExport;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class LaporanPotJelek extends Page
{
    use HasPageShield;

    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $title = 'Laporan Produksi Potong Jelek';
    protected static ?string $navigationLabel = 'Laporan Produksi Pot Jelek';
    protected string $view = 'filament.pages.laporan-pot-jelek';
    protected static ?int $navigationSort = 10;
    protected static bool $shouldRegisterNavigation = false;

    public array $data = [
        'tanggal' => null,
    ];

    public array $dataProduksi = [];
    public bool $isLoading = false;

    public function mount(): void
    {
        $this->form->fill($this->data);
        $this->data['tanggal'] = now()->format('Y-m-d');
        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal')
                    ->label('Pilih Tanggal Laporan Potong Jelek')
                    ->native(false)
                    ->format('Y-m-d')
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->closeOnDateSelection()
                    ->afterStateUpdated(fn($state) => $this->onTanggalUpdated($state))
                    ->required()
                    ->maxDate(now())
                    ->default(now())
                    ->suffixIcon('heroicon-o-calendar')
                    ->suffixIconColor('primary')
                    ->helperText('Pilih tanggal untuk melihat laporan hasil produksi potong jelek'),
            ])
            ->statePath('data')
            ->columns(1);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->refresh()),

            Action::make('exportExcel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn() => $this->exportExcel())
                ->visible(fn() => !empty($this->dataProduksi)),
        ];
    }

    public function onTanggalUpdated($state): void
    {
        try {
            if ($state instanceof Carbon) {
                $tanggal = $state->format('Y-m-d');
            } elseif (is_string($state)) {
                if (str_contains($state, '/')) {
                    $tanggal = Carbon::createFromFormat('d/m/Y', $state)->format('Y-m-d');
                } else {
                    $tanggal = Carbon::parse($state)->format('Y-m-d');
                }
            } else {
                $tanggal = now()->format('Y-m-d');
            }

            $this->data['tanggal'] = $tanggal;
            $this->loadData();
        } catch (Exception $e) {
            Log::error('Error parsing date Potong Jelek: ' . $e->getMessage());

            Notification::make()
                ->danger()
                ->title('Format Tanggal Tidak Valid')
                ->send();

            $this->data['tanggal'] = now()->format('Y-m-d');
            $this->form->fill($this->data);
        }
    }

    public function loadData(): void
    {
        try {
            $this->isLoading = true;
            $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');

            $this->dataProduksi = [];

            $raw = LoadLaporanPotJelek::run($tanggal);

            Log::info('Potong Jelek Query executed', [
                'records_found' => $raw->count(),
                'tanggal' => $tanggal
            ]);

            if ($raw->isNotEmpty()) {
                // PotJelekDataMap::make() sekarang menghitung target PER
                // UKURAN (bukan flat) + capaian global per individu, lihat
                // README perhitungan potongan (pola sama dengan Join & Pot Siku).
                $this->dataProduksi = PotJelekDataMap::make($raw);
            } else {
                Notification::make()
                    ->warning()
                    ->title('Tidak Ada Data Potong Jelek')
                    ->body('Tidak ditemukan data produksi potong jelek untuk tanggal ' . Carbon::parse($tanggal)->format('d/m/Y'))
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Error loading potong jelek data: ' . $e->getMessage());

            Notification::make()
                ->danger()
                ->title('Error Memuat Data Potong Jelek')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->send();
        } finally {
            $this->isLoading = false;
        }
    }

    public function refresh(): void
    {
        $this->loadData();
        Notification::make()->success()->title('Data Diperbarui')->send();
    }

    public function exportExcel()
    {
        try {
            $tanggal = Carbon::parse($this->data['tanggal'])->format('d-m-Y');

            if (class_exists('App\Exports\LaporanPotJelekExport')) {
                return Excel::download(
                    new LaporanPotJelekExport($this->dataProduksi, $this->data['tanggal']),
                    "laporan-pot-jelek-{$tanggal}.xlsx"
                );
            }

            throw new Exception("Fitur Export Excel untuk Pot Jelek dalam pengembangan.");
        } catch (Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Export Excel')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function getViewData(): array
    {
        return [
            'dataProduksi' => $this->dataProduksi,
            'isLoading' => $this->isLoading,
        ];
    }
}