<?php

namespace App\Filament\Pages;

use App\Exports\LaporanPressDryerExport;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;

// Untuk export to excel
use Filament\Actions\Action;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

// Exception dan Logging
use Exception;
use Illuminate\Support\Facades\Log;

// Query dan Transformer
use App\Filament\Pages\LaporanPressDryer\Queries\LoadPressDryer;
use App\Filament\Pages\LaporanPressDryer\Transformers\DryerDataMap;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanPressDryer extends Page implements HasForms
{
    use HasPageShield;
    use Forms\Concerns\InteractsWithForms;

    protected string $view = 'filament.pages.laporan-press-dryer';

    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $title = 'Laporan Produksi Dryer';
    protected static ?int $navigationSort = 3;

    public array $data = [
        'tanggal' => null,
    ];

    public array $dataProduksi = [];

    public bool $isLoading = false;

    protected $listeners = [
        'refreshLaporanPressDryer' => 'loadData',
    ];

    public function mount(): void
    {
        $tanggal = now()->format('Y-m-d');

        // FIX: fill form dulu, lalu set ulang $this->data SETELAH fill
        // karena form->fill() dengan statePath('data') akan overwrite $this->data
        $this->form->fill(['tanggal' => $tanggal]);
        $this->data['tanggal'] = $tanggal;

        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal')
                    ->label('Tanggal')
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
                    ->suffixIconColor('primary'),
            ])
            ->statePath('data');
    }

    public function onTanggalUpdated($state): void
    {
        try {
            if ($state instanceof Carbon) {
                $tanggal = $state->format('Y-m-d');
            } elseif (is_string($state) && $state !== '') {
                $tanggal = Carbon::parse($state)->format('Y-m-d');
            } else {
                $tanggal = now()->format('Y-m-d');
            }

            $this->data['tanggal'] = $tanggal;
            $this->loadData();

        } catch (Exception $e) {
            Notification::make()
                ->danger()
                ->title('Format Tanggal Tidak Valid')
                ->body('Silakan pilih tanggal yang valid.')
                ->send();

            $this->data['tanggal'] = now()->format('Y-m-d');
            $this->form->fill($this->data);
        }
    }

    public function loadData(): void
    {
        try {
            $this->isLoading = true;

            // FIX: ambil tanggal dari $this->data, fallback ke hari ini
            // Pastikan tidak null sebelum digunakan
            $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');
            $tanggal = Carbon::parse($tanggal)->format('Y-m-d');

            Log::info('Loading produksi data for date: ' . $tanggal, [
                'timestamp' => now()->toDateTimeString(),
            ]);

            $this->dataProduksi = [];

            $raw = LoadPressDryer::run($tanggal);

            Log::info('Found ' . $raw->count() . ' production records');

            $this->dataProduksi = DryerDataMap::make($raw);

            if (empty($this->dataProduksi)) {
                Notification::make()
                    ->warning()
                    ->title('Tidak Ada Data')
                    ->body('Tidak ditemukan data produksi untuk tanggal ' . Carbon::parse($tanggal)->format('d/m/Y'))
                    ->send();
            }

        } catch (Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error Memuat Data')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->send();

            Log::error('Error loading produksi data: ' . $e->getMessage());

            $this->dataProduksi = [];
        } finally {
            $this->isLoading = false;
        }
    }

    public function refresh(): void
    {
        $this->loadData();

        Notification::make()
            ->success()
            ->title('Data Diperbarui')
            ->body('Data berhasil dimuat ulang')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action('refresh'),

            Action::make('export')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportToExcel'),  
        ];
    }

    public function exportToExcel()
    {
        $tanggal  = $this->data['tanggal'] ?? now()->format('Y-m-d');
        $filename = 'Laporan-Produksi-Dryer-' . Carbon::parse($tanggal)->format('Y-m-d') . '.xlsx';

        return Excel::download(new LaporanPressDryerExport($this->dataProduksi), $filename);
    }
}