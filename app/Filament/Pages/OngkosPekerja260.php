<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;

use App\Filament\Pages\LaporanHarian\Services\LoadOngkosPekerja260;
use App\Filament\Pages\LaporanHarian\Transformers\OngkosPekerja260DataMap;
use App\Exports\OngkosPekerjaExport;

use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use BackedEnum;
use UnitEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class OngkosPekerja260 extends Page
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';
    protected string $view = 'filament.pages.ongkos-pekerja260';
    protected static UnitEnum|string|null $navigationGroup = 'Ongkos';
    protected static ?string $title = 'Ongkos Pekerja 260';
    protected static ?int $navigationSort = 6;

    public $laporanOngkos = [];
    public $startDate = null;
    public $endDate = null;
    public bool $isLoading = false;

    public function mount(): void
    {
        // Logika Dinamis: Jika awal bulan, tampilkan rekap bulan lalu (Januari)
        if (now()->day <= 10) {
            $this->startDate = now()->subMonth()->startOfMonth()->format('Y-m-d');
            $this->endDate = now()->subMonth()->endOfMonth()->format('Y-m-d');
        } else {
            $this->startDate = now()->startOfMonth()->format('Y-m-d');
            $this->endDate = now()->format('Y-m-d');
        }

        $this->form->fill([
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ]);

        $this->loadAllData();
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Filter Periode Laporan')
                ->schema([
                    DatePicker::make('start_date')
                        ->label('Tanggal Mulai')
                        ->live()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->afterStateUpdated(fn($state) => $this->updatedFilter('startDate', $state)),

                    DatePicker::make('end_date')
                        ->label('Tanggal Selesai')
                        ->live()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->afterStateUpdated(fn($state) => $this->updatedFilter('endDate', $state)),
                ])->columns(2),
        ];
    }

    public function updatedFilter($property, $value)
    {
        $this->$property = $value;

        // PAKSA Form Fill: Ini kunci agar filter berjalan. 
        // Mengisi ulang form secara parsial memastikan Filament mengenali nilai baru.
        $this->form->fill([
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ], partial: true);

        $this->loadAllData();
    }

    public function loadAllData()
    {
        $this->isLoading = true;

        try {
            // Ambil data langsung dari state form mentah (Raw State)
            $state = $this->form->getRawState();
            $start = $state['start_date'] ?? $this->startDate;
            $end = $state['end_date'] ?? $this->endDate;

            if (!$start || !$end) return;

            $dataMentah = LoadOngkosPekerja260::fetch($start, $end);
            $this->laporanOngkos = OngkosPekerja260DataMap::make($dataMentah);

            $jumlahData = count($this->laporanOngkos);

            // Log untuk memantau di laravel.log
            Log::info("FILTER BERHASIL: {$start} s/d {$end} - Data: {$jumlahData}");

            if ($jumlahData > 0) {
                Notification::make()
                    ->title('Data Berhasil Dimuat')
                    ->body("Menampilkan {$jumlahData} baris data.")
                    ->success()
                    ->duration(2000)
                    ->send();
            } else {
                Notification::make()
                    ->title('Data Kosong')
                    ->body('Tidak ditemukan produksi mesin 260 pada periode ini.')
                    ->warning()
                    ->send();
            }

            $this->startDate = $start;
            $this->endDate = $end;
        } catch (\Exception $e) {
            Log::error("FILTER ERROR: " . $e->getMessage());
            Notification::make()->title('Kesalahan Sistem')->danger()->send();
            $this->laporanOngkos = [];
        }

        $this->isLoading = false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportToExcel'),
        ];
    }

    public function exportToExcel()
    {
        if (empty($this->laporanOngkos)) {
            Notification::make()->title('Gagal')->body('Data kosong.')->warning()->send();
            return;
        }
        $filename = "Ongkos-Pekerja-260-{$this->startDate}-to-{$this->endDate}.xlsx";
        return Excel::download(new OngkosPekerjaExport($this->laporanOngkos), $filename);
    }
}
