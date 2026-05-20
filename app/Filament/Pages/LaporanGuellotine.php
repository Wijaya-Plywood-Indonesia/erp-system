<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LaporanGuellotineExport;
use App\Models\produksi_guellotine;
use BackedEnum;
use UnitEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class LaporanGuellotine extends Page
{
    use HasPageShield;

    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $title = 'Laporan Guellotine';
    protected string $view = 'filament.pages.laporan-guellotine';
    protected static ?int $navigationSort = 8;

    public array $data = ['tanggal' => null];
    public array $laporan = [];
    public bool $isLoading = false;

    public function mount(): void
    {
        $this->form->fill($this->data);
        $this->data['tanggal'] = now()->format('Y-m-d');
        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal')
                    ->label('Pilih Tanggal Laporan Guellotine')
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
                    ->helperText('Pilih tanggal untuk melihat laporan guellotine'),
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
            $tanggal = $state instanceof Carbon
                ? $state->format('Y-m-d')
                : Carbon::parse($state)->format('Y-m-d');

            $this->data['tanggal'] = $tanggal;
            $this->loadData();
        } catch (Exception $e) {
            Notification::make()
                ->danger()
                ->title('Format Tanggal Tidak Valid')
                ->body('Silakan pilih tanggal yang benar.')
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
            $this->laporan = [];

            $raw = produksi_guellotine::with([
                'pegawaiGuellotine.pegawai',
                'hasilGuellotine.ukuran',
                'hasilGuellotine.jenisKayu',
            ])
                ->whereDate('tanggal_produksi', $tanggal)
                ->get();

            if ($raw->isNotEmpty()) {
                $this->laporan = $this->transformData($raw);
            } else {
                Notification::make()
                    ->warning()
                    ->title('Tidak Ada Data')
                    ->body('Tidak ditemukan data guellotine untuk tanggal ' . Carbon::parse($tanggal)->format('d/m/Y'))
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Error loading guellotine data', ['message' => $e->getMessage()]);
            Notification::make()
                ->danger()
                ->title('Error Memuat Data')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->send();
            $this->laporan = [];
        } finally {
            $this->isLoading = false;
        }
    }

    private function transformData($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {
            $tanggal       = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
            $jumlahPekerja = $produksi->pegawaiGuellotine->count();

            // Group hasilGuellotine per ukuran + jenis kayu
            $hasilGroups = $produksi->hasilGuellotine
                ->groupBy(fn($h) => $h->id_ukuran . '|' . $h->id_jenis_kayu);

            foreach ($hasilGroups as $hasilItems) {
                $firstHasil  = $hasilItems->first();
                $ukuranModel = $firstHasil->ukuran;
                $jenisKayu   = $firstHasil->jenisKayu;

                $byk = (int) $hasilItems->sum('jumlah');

                $p = $ukuranModel->panjang ?? 0;
                $l = $ukuranModel->lebar   ?? 0;
                $t = $ukuranModel->tebal   ?? 0;

                $m3 = ($p && $l && $t && $byk)
                    ? round(($p * $l * $t * $byk) / 1000000000, 3)
                    : 0;

                $result[] = [
                    'tanggal'   => $tanggal,
                    'p'         => $p,
                    'l'         => $l,
                    't'         => $t,
                    'jenis'     => strtolower($jenisKayu->kode_kayu ?? $jenisKayu->nama_kayu ?? '-'),
                    'byk'       => $byk,
                    'm3'        => $m3,
                    'ttl_pkj'   => $jumlahPekerja,
                ];
            }
        }

        return $result;
    }

    public function refresh(): void
    {
        $this->loadData();
        Notification::make()
            ->success()
            ->title('Data Diperbarui')
            ->send();
    }

    public function exportExcel()
    {
        try {
            $tanggalQuery = Carbon::parse($this->data['tanggal'])->format('Y-m-d');
            $tanggalFile  = Carbon::parse($this->data['tanggal'])->format('d-m-Y');

            if (empty($this->laporan)) {
                Notification::make()
                    ->warning()
                    ->title('Tidak Ada Data')
                    ->body('Tidak ada data untuk diekspor.')
                    ->send();
                return;
            }

            return Excel::download(
                new LaporanGuellotineExport($this->laporan, $tanggalQuery),
                "laporan-guellotine-{$tanggalFile}.xlsx"
            );
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
            'laporan'   => $this->laporan,
            'isLoading' => $this->isLoading,
        ];
    }
}
