<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
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
    protected static ?string $title = 'Laporan Produksi Repair';
    protected string $view = 'filament.pages.laporan-repairs';
    protected static ?int $navigationSort = 6;
    protected static bool $shouldRegisterNavigation = false;

    public array $data = [
        'tanggal' => null,
    ];

    public array $laporan = [];
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
                    ->label('Pilih Tanggal Laporan Repair')
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
                    ->helperText('Pilih tanggal untuk melihat laporan hasil produksi repair'),
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
                ->visible(fn() => !empty($this->laporan)),
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
            Log::error('Error parsing date Repair: ' . $e->getMessage());

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
            $this->laporan = [];

            $raw = LoadLaporanRepairs::run($tanggal);

            if ($raw->isNotEmpty()) {
                $mappedData = RepairDataMap::make($raw);

                $this->dataProduksi = $mappedData;
                $this->laporan      = $mappedData;

                Log::info('Data laporan repair berhasil dimuat', [
                    'total_meja' => count($mappedData),
                    'tanggal'    => $tanggal,
                ]);
            } else {
                Notification::make()
                    ->warning()
                    ->title('Tidak Ada Data Repair')
                    ->body('Tidak ditemukan data repair untuk tanggal ' . Carbon::parse($tanggal)->format('d/m/Y'))
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Error loading repair report: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->danger()
                ->title('Error Memuat Data Repair')
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
            $tanggalQuery = Carbon::parse($this->data['tanggal'])->format('Y-m-d');
            $tanggalFile  = Carbon::parse($this->data['tanggal'])->format('d-m-Y');

            if (empty($this->laporan)) {
                Notification::make()
                    ->warning()
                    ->title('Tidak Ada Data')
                    ->send();
                return;
            }

            return Excel::download(
                new LaporanRepairExport($this->laporan, $tanggalQuery),
                "laporan-repair-{$tanggalFile}.xlsx"
            );
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
            'laporan'      => $this->laporan,
            'dataProduksi' => $this->dataProduksi,
            'isLoading'    => $this->isLoading,
        ];
    }
}
