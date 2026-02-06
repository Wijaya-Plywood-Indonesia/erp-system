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

use App\Filament\Pages\LaporanRepairs\Queries\LoadLaporanRepairs;
use App\Filament\Pages\LaporanRepairs\Transformers\RepairDataMap;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LaporanRepairExport;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class LaporanRepairs extends Page
{
    use HasPageShield;

    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $title = 'Laporan Repairs';
    protected string $view = 'filament.pages.laporan-repairs';
    protected static ?int $navigationSort = 6;

    public array $data = [
        'tanggal' => null,
    ];

    public array $laporan = [];
    public array $dataProduksi = [];
    public bool $isLoading = false;

    public function mount(): void
    {
        $this->data['tanggal'] = now()->format('Y-m-d');
        $this->form->fill($this->data);
        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal')
                    ->label('Pilih Tanggal Laporan')
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
                    ->helperText('Pilih tanggal untuk melihat laporan repair'),
            ])
            ->statePath('data')
            ->columns(1);
    }

    /**
     * Header Actions untuk refresh dan export (opsional)
     */
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
                ->visible(fn() => !empty($this->laporan)),
        ];
    }

    /**
     * Update saat tanggal berubah
     */
    public function onTanggalUpdated($state): void
    {
        try {
            if ($state instanceof Carbon) {
                $tanggal = $state->format('Y-m-d');
            } elseif (is_string($state)) {
                $tanggal = Carbon::parse($state)->format('Y-m-d');
            } else {
                $tanggal = now()->format('Y-m-d');
            }

            $this->data['tanggal'] = $tanggal;
            $this->loadData();
        } catch (Exception $e) {
            Log::error('Error parsing date: ' . $e->getMessage());

            Notification::make()
                ->danger()
                ->title('Format Tanggal Tidak Valid')
                ->body('Silakan pilih tanggal yang valid.')
                ->send();

            $this->data['tanggal'] = now()->format('Y-m-d');
            $this->form->fill($this->data);
        }
    }

    /**
     * Load data dari database
     */
    public function loadData(): void
    {
        try {
            $this->isLoading = true;

            $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');

            // Reset data
            $this->dataProduksi = [];
            $this->laporan = [];

            // Load data menggunakan Query class
            $raw = LoadLaporanRepairs::run($tanggal);

            Log::info('Query executed', [
                'records_found' => $raw->count(),
                'tanggal' => $tanggal
            ]);

            // Transform data menggunakan Transformer
            if ($raw->isNotEmpty()) {
                $this->dataProduksi = RepairDataMap::make($raw);
                $this->laporan = $this->dataProduksi;

                Log::info('Data transformed successfully', [
                    'items_count' => count($this->dataProduksi)
                ]);
            } else {
                Notification::make()
                    ->warning()
                    ->title('Tidak Ada Data')
                    ->body('Tidak ditemukan data repair untuk tanggal ' . Carbon::parse($tanggal)->format('d/m/Y'))
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Error loading repair data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tanggal' => $tanggal ?? 'unknown'
            ]);

            Notification::make()
                ->danger()
                ->title('Error Memuat Data')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->send();

            $this->dataProduksi = [];
            $this->laporan = [];
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Refresh data
     */
    public function refresh(): void
    {
        $this->loadData();

        Notification::make()
            ->success()
            ->title('Data Diperbarui')
            ->body('Data berhasil dimuat ulang untuk tanggal ' . Carbon::parse($this->data['tanggal'])->format('d/m/Y'))
            ->send();
    }

    /*
        Export Data ke Excel
    */
    public function exportExcel()
    {
        try {
            $tanggal = Carbon::parse($this->data['tanggal'])->format('d-m-Y');

            return Excel::download(
                new LaporanRepairExport($this->laporan),
                "laporan-repair-{$tanggal}.xlsx"
            );
        } catch (Exception $e) {
            Log::error('Export Excel gagal', [
                'message' => $e->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('Gagal Export Excel')
                ->body($e->getMessage())
                ->send();
        }
    }



    /**
     * Kirim data ke view
     */
    public function getViewData(): array
    {
        return [
            'laporan' => $this->laporan,
            'dataProduksi' => $this->dataProduksi,
            'isLoading' => $this->isLoading,
        ];
    }
}
