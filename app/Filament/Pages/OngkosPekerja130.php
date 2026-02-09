<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

use App\Filament\Pages\LaporanHarian\Services\LoadOngkosPekerja130;
use App\Filament\Pages\LaporanHarian\Transformers\OngkosPekerja130DataMap;
use App\Exports\OngkosPekerja130Export;
use BackedEnum;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Exception;
use Filament\Schemas\Components\Section;
use UnitEnum;

class OngkosPekerja130 extends Page
{
    use InteractsWithForms;
    use HasPageShield;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-banknotes';
    protected string $view = 'filament.pages.ongkos-pekerja130';
    protected static string|null|UnitEnum $navigationGroup = 'Ongkos';
    protected static ?string $title = 'Ongkos Pekerja 130';
    protected static ?int $navigationSort = 7;

    public $laporanOngkos = [];
    public bool $isLoading = false;

    /**
     * Sesuai standar Filament v4, simpan state form dalam satu array.
     */
    public ?array $filterData = [];

    public function mount(): void
    {
        Log::info("Ongkos130: Mengakses halaman laporan.");

        // Penentuan range default
        $start = now()->startOfMonth();
        $end = now();

        $this->form->fill([
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
        ]);

        $this->loadAllData();
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Filter Periode Laporan 130')
                ->schema([
                    DatePicker::make('start_date')
                        ->label('Tanggal Mulai')
                        ->live()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->afterStateUpdated(fn() => $this->loadAllData()),

                    DatePicker::make('end_date')
                        ->label('Tanggal Selesai')
                        ->live()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->afterStateUpdated(fn() => $this->loadAllData()),
                ])->columns(2),
        ];
    }

    protected function getFormStatePath(): string
    {
        return 'filterData';
    }

    public function loadAllData()
    {
        $this->isLoading = true;

        try {
            $state = $this->form->getState();
            $start = $state['start_date'] ?? null;
            $end = $state['end_date'] ?? null;

            if (!$start || !$end) {
                Log::warning("Ongkos130: Percobaan load data tanpa parameter tanggal.");
                return;
            }

            Log::info("Ongkos130: Memulai penarikan data periode {$start} s/d {$end}");

            // Eksekusi Service khusus 130
            $dataMentah = LoadOngkosPekerja130::fetch($start, $end);
            Log::info("Ongkos130: Query database selesai. Jumlah record ditemukan: " . count($dataMentah));

            // Transformasi Data menggunakan DataMap 130
            $this->laporanOngkos = OngkosPekerja130DataMap::make($dataMentah);

            $jumlahData = count($this->laporanOngkos);
            Log::info("Ongkos130: Transformasi data selesai. Jumlah baris tampilan: {$jumlahData}");

            if ($jumlahData > 0) {
                Notification::make()
                    ->title('Data Berhasil Dimuat')
                    ->body("Menampilkan {$jumlahData} baris data (Mesin 130).")
                    ->success()
                    ->send();
            } else {
                $this->laporanOngkos = [];
                Notification::make()
                    ->title('Data Kosong')
                    ->warning()
                    ->send();
            }
        } catch (Exception $e) {
            // Logging detail error untuk debugging
            Log::error("Ongkos130_LOAD_ERROR: " . $e->getMessage());
            Log::error($e->getTraceAsString());

            Notification::make()
                ->title('Kesalahan Sistem')
                ->body('Terjadi kesalahan saat memuat data. Periksa file log.')
                ->danger()
                ->send();

            $this->laporanOngkos = [];
        }

        $this->isLoading = false;
    }

    public function exportToExcel()
    {
        if (empty($this->laporanOngkos)) {
            Log::notice("Ongkos130: User mencoba export data yang kosong.");
            Notification::make()
                ->title('Gagal Export')
                ->body('Data tidak ditemukan.')
                ->warning()
                ->send();
            return;
        }

        try {
            $state = $this->form->getState();
            $startDate = $state['start_date'] ?? now()->format('Y-m-d');
            $endDate = $state['end_date'] ?? now()->format('Y-m-d');

            $filename = "Rekap_Ongkos_130_{$startDate}_to_{$endDate}.xlsx";

            Log::info("Ongkos130: Memulai proses export Excel untuk file {$filename}");

            return Excel::download(
                new OngkosPekerja130Export($this->laporanOngkos),
                $filename
            );
        } catch (Exception $e) {
            Log::error("Ongkos130_EXPORT_ERROR: " . $e->getMessage());
            Log::error($e->getTraceAsString());

            Notification::make()
                ->title('Kesalahan Sistem')
                ->body('Gagal menggenerate file Excel.')
                ->danger()
                ->send();
        }
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
}
