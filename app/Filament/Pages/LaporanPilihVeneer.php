<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanPilihVeneerExport;
use App\Filament\Pages\LaporanPilihVeneer\Transformers\PilihVeneerDataMap;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ProduksiPilihVeneer;
use Carbon\Carbon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;
use Exception;
use Illuminate\Support\Facades\Log;

class LaporanPilihVeneer extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-pilih-veneer';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Pilih Veneer';
    protected static ?string $navigationLabel = 'Laporan Produksi Pilih Veneer';
    protected static ?int $navigationSort = 13;
    protected static bool $shouldRegisterNavigation = false;

    public $tanggal = null;
    public array $laporan = [];
    public array $dataProduksi = [];
    public bool $isLoading = false;

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->form->fill(['tanggal' => $this->tanggal]);
        $this->loadAllData();
    }

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
                ->visible(fn() => !empty($this->laporan)),
        ];
    }

    public function exportExcel()
    {
        try {
            if (empty($this->laporan)) {
                throw new \Exception('Tidak ada data untuk diunduh.');
            }

            $tglFile = Carbon::parse($this->tanggal)->format('d-m-Y');

            return Excel::download(
                new LaporanPilihVeneerExport($this->laporan, $this->tanggal),
                "laporan-produksi-pilih-veneer-{$tglFile}.xlsx"
            );
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Export Excel')
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('tanggal')
                ->label('Pilih Tanggal')
                ->reactive()
                ->format('Y-m-d')
                ->displayFormat('d/m/Y')
                ->live()
                ->afterStateUpdated(function ($state) {
                    $this->tanggal = $state;
                    $this->loadAllData();
                }),
        ];
    }

    public function loadAllData()
    {
        try {
            $this->isLoading = true;
            $tanggal = $this->tanggal ?? now()->format('Y-m-d');
            $tanggal = Carbon::parse($tanggal)->format('Y-m-d');

            $this->dataProduksi = [];
            $this->laporan = [];

            $produksiList = ProduksiPilihVeneer::with([
                'hasilPilihVeneer.modalPilihVeneer.ukuran',
                'hasilPilihVeneer.modalPilihVeneer.jenisKayu',
                'hasilPilihVeneer.modalPilihVeneer.stokVeneerJadi.jenisKayu',
                'pegawaiPilihVeneer.pegawai'
            ])
                ->whereDate('tanggal_produksi', $tanggal)
                ->get();

            if ($produksiList->isNotEmpty()) {
                $this->dataProduksi = PilihVeneerDataMap::make($produksiList);
                $this->laporan = $this->dataProduksi;
            } else {
                Notification::make()
                    ->warning()
                    ->title('Tidak Ada Data Pilih Veneer')
                    ->body('Tidak ditemukan data produksi pilih veneer untuk tanggal ' . Carbon::parse($tanggal)->format('d/m/Y'))
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Error loading pilih veneer data: ' . $e->getMessage());
            Notification::make()
                ->danger()
                ->title('Error Memuat Data Pilih Veneer')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->send();
        } finally {
            $this->isLoading = false;
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

        foreach ($this->laporan as $table) {
            foreach ($table['detail_produksi'] as $prod) {
                $totalAll += $prod['hasil'];

                $key = $prod['ukuran'] . '|' . $prod['kw'];
                if (!isset($globalUkuranKw[$key])) {
                    $globalUkuranKw[$key] = (object) [
                        'ukuran' => $prod['ukuran'],
                        'kw' => $prod['kw'],
                        'total' => 0,
                    ];
                }
                $globalUkuranKw[$key]->total += $prod['hasil'];
            }

            foreach ($table['rekap_pekerja'] as $p) {
                $uniquePegawai[$p['nama']] = true;
            }
        }

        return [
            'totalAll' => $totalAll,
            'totalPegawai' => count($uniquePegawai),
            'globalUkuranKw' => array_values($globalUkuranKw),
        ];
    }
}
