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

    // Page Resource View
    protected string $view = 'filament.pages.laporan-press-dryer';

    // Navigation Group
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $title = 'Laporan Press Dryer';
    protected static ?int $navigationSort = 3;

    // Form state container (statePath => 'data')
    public array $data = [
        'tanggal' => null,
    ];

    public array $dataProduksi = [];

    public bool $isLoading = false;

    // ✅ TAMBAH LISTENERS
    protected $listeners = [
        'refreshLaporanPressDryer' => 'loadData',
    ];

    // Inisialisasi Halaman
    public function mount(): void
    {
        // set default tanggal di state (YYYY-MM-DD)
        $this->data['tanggal'] = now()->format('Y-m-d');

        // Isi awal form (opsional; InteractsWithForms akan menampilkan form berdasarkan statePath)
        $this->form->fill($this->data);

        $this->loadData();
    }

    // Fungsi DatePicker
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->native(false)                    // modern, responsive
                    ->format('Y-m-d')                     // format penyimpanan
                    ->displayFormat('d/m/Y')             // tampil di UI
                    ->live()
                    ->closeOnDateSelection()
                    ->afterStateUpdated(fn($state) => $this->onTanggalUpdated($state))
                    ->required()
                    ->maxDate(now())
                    ->default(now())
                    ->suffixIcon('heroicon-o-calendar')
                    ->suffixIconColor('primary'), // tidak boleh pilih tanggal di masa depan
            ])
            ->statePath('data'); // penting: menyambungkan schema -> $this->data
    }

    // Fungsi setelah tanggal diupdate
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
            Notification::make()
                ->danger()
                ->title('Format Tanggal Tidak Valid')
                ->body('Silakan pilih tanggal yang valid.')
                ->send();

            $this->data['tanggal'] = now()->format('Y-m-d');
            $this->form->fill($this->data);
        }
    }

    // Load data produksi berdasarkan tanggal
    public function loadData(): void
    {
        try {
            $this->isLoading = true;

            $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');

            Log::info('Loading produksi data for date: ' . $tanggal, [
                'timestamp' => now()->toDateTimeString(),
            ]);

            // ✅ FORCE CLEAR STATE
            $this->dataProduksi = [];

            // LoadProduksi::run harus mengembalikan koleksi Eloquent
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
            // Handle error dengan notification
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

    // ✅ TAMBAH METHOD REFRESH MANUAL
    public function refresh(): void
    {
        $this->loadData();

        Notification::make()
            ->success()
            ->title('Data Diperbarui')
            ->body('Data berhasil dimuat ulang')
            ->send();
    }

    // Export To Excel Button
    protected function getHeaderActions(): array
    {
        return [
            // ✅ TAMBAH TOMBOL REFRESH
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action('refresh'),

            Action::make('export')
                ->label("Download Excel")
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportToExcel'),
        ];
    }

    // Export To Excel Function
    public function exportToExcel()
    {
        $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');
        $filename = 'Laporan-Produksi-' . Carbon::parse($tanggal)->format('Y-m-d') . '.xlsx';
        return Excel::download(new LaporanPressDryerExport($this->dataProduksi), $filename);
    }
}
