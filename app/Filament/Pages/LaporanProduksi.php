<?php

namespace App\Filament\Pages;

use App\Exports\LaporanProduksiExport;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use App\Filament\Pages\LaporanProduksi\Queries\LoadProduksi;
use App\Filament\Pages\LaporanProduksi\Transformers\ProduksiDataMap;
use Maatwebsite\Excel\Facades\Excel;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanProduksi extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-produksi';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Rotary';
    protected static ?int $navigationSort = 2;

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label("Download Excel")
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportToExcel'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\DatePicker::make('tanggal')
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
            $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');
            $raw = LoadProduksi::run($tanggal);
            $this->dataProduksi = ProduksiDataMap::make($raw);
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
            ->body('Data produksi telah dimuat ulang.')
            ->send();
    }

    public function exportToExcel()
    {
        $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');
        $filename = 'Laporan-Produksi-Rotary-' . Carbon::parse($tanggal)->format('Y-m-d') . '.xlsx';
        return Excel::download(new LaporanProduksiExport($this->dataProduksi, $tanggal), $filename);
    }
}
