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

// Pastikan mengarah ke namespace Pot Afalan yang baru
use App\Filament\Pages\LaporanPotAfalanJoin\Queries\LoadLaporanPotAfalan;
use App\Filament\Pages\LaporanPotAfalanJoin\Transformers\PotAfalanDataMap;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LaporanPotAfalanJoinExport;

class LaporanPotAfalanJoin extends Page
{
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $title = 'Laporan Produksi Potong Afalan Join';
    protected static ?string $navigationLabel = 'Laporan Potong Afalan Join';
    protected string $view = 'filament.pages.laporan-pot-afalan';
    protected static ?int $navigationSort = 9;

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
                    ->label('Pilih Tanggal Laporan Potong Afalan')
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
                    ->helperText('Pilih tanggal untuk melihat laporan hasil produksi potong afalan'),
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
                $tanggal = Carbon::parse($state)->format('Y-m-d');
            } else {
                $tanggal = now()->format('Y-m-d');
            }

            $this->data['tanggal'] = $tanggal;
            $this->loadData();
        } catch (Exception $e) {
            Log::error('Error parsing date Potong Afalan: ' . $e->getMessage());

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

            // Memanggil Query Class Potong Afalan
            $raw = LoadLaporanPotAfalan::run($tanggal);

            Log::info('Potong Afalan Query executed', [
                'records_found' => $raw->count(),
                'tanggal' => $tanggal
            ]);

            if ($raw->isNotEmpty()) {
                // Memanggil Transformer PotAfalanDataMap
                $this->dataProduksi = PotAfalanDataMap::make($raw);
                $this->laporan = $this->dataProduksi;
            } else {
                Notification::make()
                    ->warning()
                    ->title('Tidak Ada Data Potong Afalan')
                    ->body('Tidak ditemukan data produksi potong afalan untuk tanggal ' . Carbon::parse($tanggal)->format('d/m/Y'))
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Error loading potong afalan data: ' . $e->getMessage());

            Notification::make()
                ->danger()
                ->title('Error Memuat Data Potong Afalan')
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

            if (class_exists('App\Exports\LaporanPotAfalanJoinExport')) {
                return Excel::download(
                    new LaporanPotAfalanJoinExport($this->laporan),
                    "laporan-pot-afalan-{$tanggal}.xlsx"
                );
            }

            throw new Exception("Class Export Excel belum tersedia.");
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
            'laporan' => $this->laporan,
            'dataProduksi' => $this->dataProduksi,
            'isLoading' => $this->isLoading,
            'summary' => $this->calculateSummary(),
        ];
    }

    private function calculateSummary(): array
    {
        $totalAll = 0;
        $uniquePegawai = [];
        $globalUkuranKw = [];

        foreach ($this->laporan as $row) {
            $totalAll += $row['hasil'];

            foreach ($row['pekerja'] as $p) {
                $uniquePegawai[$p['nama']] = true;
            }

            $key = $row['ukuran'] . '|' . $row['kw'];
            if (!isset($globalUkuranKw[$key])) {
                $globalUkuranKw[$key] = (object)[
                    'ukuran' => $row['ukuran'],
                    'kw' => $row['kw'],
                    'total' => 0
                ];
            }
            $globalUkuranKw[$key]->total += $row['hasil'];
        }

        return [
            'totalAll' => $totalAll,
            'totalPegawai' => count($uniquePegawai),
            'globalUkuranKw' => array_values($globalUkuranKw),
        ];
    }
}
