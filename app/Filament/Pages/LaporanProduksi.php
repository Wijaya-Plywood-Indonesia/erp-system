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

    // Form state container (statePath => 'data')
    public array $data = [
        'tanggal' => null,
    ];

    public array $dataProduksi = [];
    public bool $isLoading = false;

    // Inisialisasi halaman
    public function mount(): void
    {
        // set default tanggal di state (YYYY-MM-DD)
        $this->data['tanggal'] = now()->format('Y-m-d');

        // Isi awal form (opsional; InteractsWithForms akan menampilkan form berdasarkan statePath)
        $this->form->fill($this->data);

        $this->loadData();
    }

    // Header untuk download button
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label("Donwload Excel")
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportToExcel'),
        ];
    }

    // DatePicker untuk memilih tanggal laporan
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\DatePicker::make('tanggal')
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

    // handler ketika tanggal berubah
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

        // normalisasi format tanggal menjadi YYYY-MM-DD agar konsisten

    }

    // Load data produksi berdasarkan tanggal
    public function loadData(): void
    {
        try {
            $this->isLoading = true;

            $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');

            Log::info('Loading produksi data for date: ' . $tanggal);

            // LoadProduksi::run harus mengembalikan koleksi Eloquent
            $raw = LoadProduksi::run($tanggal);

            Log::info('Found ' . $raw->count() . ' production records');

            $this->dataProduksi = ProduksiDataMap::make($raw);

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

    // Function untuk refresh
    public function refresh(): void
    {
        $this->loadData();

        Notification::make()
            ->success()
            ->title('Data Diperbarui')
            ->body('Data produksi telah dimuat ulang.')
            ->send();
    }

    // Export To Excel Function
    public function exportToExcel()
    {
        $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');
        $filename = 'Laporan-Produksi-' . Carbon::parse($tanggal)->format('Y-m-d') . '.xlsx';
        return Excel::download(new LaporanProduksiExport($this->dataProduksi), $filename);
    }
}
