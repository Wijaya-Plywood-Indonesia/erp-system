<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanPotSikuExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ProduksiPotSiku;
use App\Filament\Pages\LaporanPotSiku\Transformers\PotSikuDataMap;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanPotSiku extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-pot-siku';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Pot Siku';
    protected static ?int $navigationSort = 6;
    protected static bool $shouldRegisterNavigation = false;

    public $dataSiku = [];
    public $tanggal = null;
    public bool $isLoading = false;

    // Menandakan apakah tanggal yang ditampilkan adalah hasil fallback
    // (bukan hari ini), supaya bisa ditampilkan info ke user di Blade.
    public bool $isFallbackDate = false;

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
                ->visible(fn() => !empty($this->dataSiku)),
        ];
    }

    public function exportExcel()
    {
        try {
            if (empty($this->dataSiku)) {
                throw new Exception('Tidak ada data untuk diunduh.');
            }

            $tglFile = Carbon::parse($this->tanggal)->format('d-m-Y');

            return Excel::download(
                new LaporanPotSikuExport($this->dataSiku, $this->tanggal),
                "laporan-pot-siku-{$tglFile}.xlsx"
            );
        } catch (Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Export Excel')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function getListeners(): array
    {
        return [
            'echo:production.pot_siku,.ProductionUpdated' => 'loadAllData',
        ];
    }

    public function mount(): void
    {
        // Selalu mulai dari HARI INI sebagai default.
        $this->tanggal = now()->format('Y-m-d');
        $this->isFallbackDate = false;

        $this->form->fill(['tanggal' => $this->tanggal]);
        $this->loadAllData();
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('tanggal')
                ->label('Pilih Tanggal Laporan')
                ->native(false)
                ->format('Y-m-d')
                ->displayFormat('d/m/Y')
                ->live()
                ->closeOnDateSelection()
                ->afterStateUpdated(function ($state) {
                    $this->tanggal = $state;
                    $this->loadAllData();
                })
                ->required()
                ->maxDate(now())
                ->default(now())
                ->suffixIcon('heroicon-o-calendar')
                ->suffixIconColor('primary'),
        ];
    }

    public function onTanggalUpdated($state)
    {
        $this->tanggal = $state;
        $this->loadAllData();
    }

    /**
     * Ambil semua produksi Pot Siku di tanggal terpilih, transform TIAP
     * produksi lewat PotSikuDataMap::make() (target per ukuran dari DB,
     * capaian global per individu — lihat README Join untuk konsep
     * rumusnya, Pot Siku pakai pola yang sama tapi per-orang).
     *
     * CATATAN PERILAKU TANGGAL:
     * - Tanggal yang dipakai untuk query adalah $this->tanggal apa adanya
     *   (baik dari mount() = hari ini, maupun dari perubahan DatePicker
     *   oleh user).
     * - Fallback ke "tanggal terakhir yang ada data" HANYA terjadi saat
     *   load awal (mount) jika hari ini kosong. Saat user memilih tanggal
     *   secara manual lalu datanya kosong, TIDAK ada fallback — sistem
     *   cukup menampilkan pesan "data tidak ditemukan", supaya user tidak
     *   bingung tanggalnya berubah sendiri di luar kehendaknya.
     */
    public function loadAllData()
    {
        $this->isLoading = true;

        $tanggal = $this->normalizeTanggal($this->tanggal);

        $produksiList = $this->queryProduksi($tanggal);

        // Fallback HANYA berlaku ketika tanggal yang sedang aktif adalah
        // hari ini dan datanya kosong (skenario load pertama kali dibuka).
        if ($produksiList->isEmpty() && $tanggal === now()->format('Y-m-d')) {
            $lastDate = ProduksiPotSiku::latest('tanggal_produksi')->value('tanggal_produksi');

            if ($lastDate) {
                $lastDateFormatted = $lastDate instanceof Carbon
                    ? $lastDate->format('Y-m-d')
                    : Carbon::parse($lastDate)->format('Y-m-d');

                if ($lastDateFormatted !== $tanggal) {
                    $tanggal = $lastDateFormatted;
                    $this->tanggal = $tanggal;
                    $this->isFallbackDate = true;

                    // Sinkronkan tampilan DatePicker dengan tanggal fallback.
                    $this->form->fill(['tanggal' => $this->tanggal]);

                    $produksiList = $this->queryProduksi($tanggal);
                }
            }
        } else {
            $this->isFallbackDate = false;
        }

        if ($produksiList->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Data Tidak Ditemukan')
                ->body('Tidak ada data Produksi Pot Siku untuk tanggal ' . Carbon::parse($tanggal)->format('d/m/Y'))
                ->send();
        } else {
            if ($this->isFallbackDate) {
                Notification::make()
                    ->info()
                    ->title('Menampilkan Data Terakhir')
                    ->body('Belum ada data hari ini. Menampilkan data terakhir tanggal ' . Carbon::parse($tanggal)->format('d/m/Y') . '.')
                    ->send();
            } else {
                Notification::make()
                    ->success()
                    ->title('Data Ditemukan')
                    ->body('Ditemukan ' . $produksiList->count() . ' data produksi.')
                    ->send();
            }
        }

        // PENTING: setiap produksi ditransform lewat PotSikuDataMap::make(),
        // bukan dibangun manual di sini lagi (target flat 300 sudah dibuang).
        $this->dataSiku = $produksiList
            ->map(fn (ProduksiPotSiku $produksi) => PotSikuDataMap::make($produksi))
            ->values()
            ->toArray();

        $this->isLoading = false;
    }

    /**
     * Normalisasi berbagai kemungkinan format input tanggal (Carbon,
     * 'Y-m-d', atau 'd/m/Y') menjadi string 'Y-m-d' yang konsisten
     * untuk query database.
     */
    protected function normalizeTanggal($tanggal): string
    {
        if (!$tanggal) {
            return now()->format('Y-m-d');
        }

        try {
            if ($tanggal instanceof Carbon) {
                return $tanggal->format('Y-m-d');
            }

            if (is_string($tanggal)) {
                if (str_contains($tanggal, '/')) {
                    return Carbon::createFromFormat('d/m/Y', $tanggal)->format('Y-m-d');
                }

                return Carbon::parse($tanggal)->format('Y-m-d');
            }
        } catch (Exception $e) {
            Log::error('Error parsing date in LaporanPotSiku: ' . $e->getMessage());
        }

        return now()->format('Y-m-d');
    }

    protected function queryProduksi(string $tanggal)
    {
        return ProduksiPotSiku::with([
            'pegawaiPotSiku.pegawai',
            'detailBarangDikerjakanPotSiku.jenisKayu',
            'detailBarangDikerjakanPotSiku.ukuran',
            'detailBarangDikerjakanPotSiku.pegawaiPotSiku.pegawai',
            'validasiTerakhir',
        ])
            ->whereDate('tanggal_produksi', $tanggal)
            ->get();
    }
}