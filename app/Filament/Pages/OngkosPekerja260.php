<?php

namespace App\Filament\Pages;

use App\Exports\OngkosPekerja260Export;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

use App\Filament\Pages\LaporanHarian\Services\LoadOngkosPekerja260;
use App\Filament\Pages\LaporanHarian\Transformers\OngkosPekerja260DataMap;
use BackedEnum;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class OngkosPekerja260 extends Page
{
    use InteractsWithForms;
    use HasPageShield;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-banknotes';
    protected string $view = 'filament.pages.ongkos-pekerja260';
    protected static string|null|UnitEnum $navigationGroup = 'Ongkos';
    protected static ?string $title = 'Ongkos Pekerja 260';
    protected static ?int $navigationSort = 6;

    public $laporanOngkos = [];
    public bool $isLoading = false;

    /**
     * Sesuai standar Filament v4, simpan state form dalam satu array.
     */
    public ?array $filterData = [];

    public function mount(): void
    {
        // Penentuan range default
        $start = now()->day <= 10 ? now()->subMonth()->startOfMonth() : now()->startOfMonth();
        $end = now()->day <= 10 ? now()->subMonth()->endOfMonth() : now();

        // Inisialisasi state awal ke dalam properti filterData
        $this->form->fill([
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
        ]);

        $this->loadAllData();
    }

    /**
     * Menggunakan getFormSchema untuk mendefinisikan struktur form.
     */
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

    /**
     * Menghubungkan schema dengan properti filterData.
     */
    protected function getFormStatePath(): string
    {
        return 'filterData';
    }

    public function loadAllData()
    {
        $this->isLoading = true;

        try {
            // Ambil data langsung dari state path 'filterData'
            $state = $this->form->getState();
            $start = $state['start_date'] ?? null;
            $end = $state['end_date'] ?? null;

            if (!$start || !$end) return;

            // Eksekusi Service
            $dataMentah = LoadOngkosPekerja260::fetch($start, $end);

            // Transformasi Data
            $this->laporanOngkos = OngkosPekerja260DataMap::make($dataMentah);

            $jumlahData = count($this->laporanOngkos);
            Log::info("ERP Filter: {$start} - {$end} | Count: {$jumlahData}");

            if ($jumlahData > 0) {
                Notification::make()
                    ->title('Data Berhasil Dimuat')
                    ->body("Menampilkan {$jumlahData} baris data.")
                    ->success()
                    ->send();
            } else {
                $this->laporanOngkos = [];
                Notification::make()
                    ->title('Data Kosong')
                    ->warning()
                    ->send();
            }
        } catch (\Exception $e) {
            Log::error("ERP_ERROR: " . $e->getMessage());
            Notification::make()->title('Kesalahan Sistem')->danger()->send();
            $this->laporanOngkos = [];
        }

        $this->isLoading = false;
    }

    public function exportToExcel()
    {
        // Cek jika data sudah dimuat
        if (empty($this->laporanOngkos)) {
            Notification::make()
                ->title('Gagal Export')
                ->body('Data tidak ditemukan. Silakan filter tanggal terlebih dahulu.')
                ->warning()
                ->send();
            return;
        }

        try {
            $state = $this->form->getState();
            $startDate = $state['start_date'] ?? now()->format('Y-m-d');
            $endDate = $state['end_date'] ?? now()->format('Y-m-d');

            $filename = "Rekap_Ongkos_260_{$startDate}_to_{$endDate}.xlsx";

            // Eksekusi Download
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\OngkosPekerja260Export($this->laporanOngkos),
                $filename
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Export Error: " . $e->getMessage());
            Notification::make()
                ->title('Kesalahan Sistem')
                ->body('Terjadi kesalahan saat memproses file Excel.')
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
