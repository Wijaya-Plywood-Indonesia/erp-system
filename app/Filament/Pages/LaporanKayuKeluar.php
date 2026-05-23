<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use BackedEnum;
use UnitEnum;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LaporanKayuKeluarExport;
use App\Models\HppAverageLog;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;

class LaporanKayuKeluar extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Laporan Kayu Keluar';
    protected static ?string $title = 'Laporan Kayu Keluar';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.laporan-kayu-keluar';

    // State untuk tanggal filter
    public ?string $tanggal = null;
    public array $previewRows = [];
    public bool $isLoading = false;

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->form->fill(['tanggal' => $this->tanggal]);
        $this->loadPreview();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Laporan';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportKayuKeluar'),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('tanggal')
                ->label('Tanggal')
                ->reactive()
                ->format('Y-m-d')
                ->displayFormat('d/m/Y')
                ->live()
                ->required()
                ->maxDate(now())
                ->default(now())
                ->afterStateUpdated(fn($state) => $this->updatedTanggal($state)),
        ];
    }

    public function updatedTanggal($state): void
    {
        $this->tanggal = $state;
        $this->loadPreview();
    }

    /**
     * Load live data preview from database matching Excel columns
     */
    public function loadPreview(): void
    {
        $this->isLoading = true;

        if (empty($this->tanggal)) {
            $this->previewRows = [];
            $this->isLoading = false;
            return;
        }

        $tgl = Carbon::parse($this->tanggal)->startOfDay();

        // Fetch HppAverageLog records where stock goes to 0 on target date
        $records = HppAverageLog::with(['lahan', 'jenisKayu'])
            ->whereDate('tanggal', $tgl)
            ->where('tipe_transaksi', 'keluar')
            ->where('stok_batang_after', 0)
            ->orderBy('id', 'asc')
            ->get();

        $rows = [];
        $tglVal = Carbon::parse($this->tanggal)->format('d-m-Y');

        $totalBanyak = 0;
        $totalM3 = 0;
        $totalHarga = 0;

        foreach ($records as $record) {
            $jenisNama = $record->jenisKayu?->nama_kayu ?? '-';
            $isSengon = (stripos($jenisNama, 'sengon') !== false);
            $is130 = ($record->panjang == 130);

            // Account determination
            if ($isSengon) {
                if (!$is130) {
                    $noAkun = '1411.01';
                    $namaAkun = 'Kayu Lunak 260 WJY';
                } else {
                    $noAkun = '1411.03';
                    $namaAkun = 'Kayu Lunak 130 WJY';
                }
            } else {
                if (!$is130) {
                    $noAkun = '1411.02';
                    $namaAkun = 'Kayu Keras 260 WJY';
                } else {
                    $noAkun = '1411.04';
                    $namaAkun = 'Kayu Keras 130 WJY';
                }
            }

            $keteranganSpec = "lahan " . ($record->lahan->kode_lahan ?? '-');

            $banyak = $record->total_batang > 0 ? $record->total_batang : 0;
            $m3 = $record->total_kubikasi > 0 ? $record->total_kubikasi : 0;
            $harga = $record->nilai_stok;

            $totalBanyak += $banyak;
            $totalM3 += $m3;
            $totalHarga += $harga;

            $rows[] = [
                'nama_akun' => $namaAkun,
                'tgl' => $tglVal,
                'jurnal' => '',
                'no_akun' => $noAkun,
                'no' => '',
                'mm' => '',
                'nama' => 'kayu keluar',
                'keterangan' => $keteranganSpec,
                'map' => 'k',
                'banyak' => $record->total_batang > 0 ? $record->total_batang : null,
                'm3' => $record->total_kubikasi > 0 ? $record->total_kubikasi : null,
                'harga' => $harga,
            ];
        }

        if (!empty($rows)) {
            $rows[] = [
                'nama_akun' => 'HPP Triplek',
                'tgl' => $tglVal,
                'jurnal' => '',
                'no_akun' => '6111.00',
                'no' => '',
                'mm' => '',
                'nama' => 'kayu habis',
                'keterangan' => '',
                'map' => 'd',
                'banyak' => $totalBanyak > 0 ? $totalBanyak : null,
                'm3' => $totalM3 > 0 ? $totalM3 : null,
                'harga' => $totalHarga,
            ];
        }

        $this->previewRows = $rows;
        $this->isLoading = false;
    }

    /**
     * Livewire Action untuk Ekspor Excel Laporan Kayu Keluar
     */
    public function exportKayuKeluar()
    {
        if (empty($this->tanggal)) {
            Notification::make()
                ->danger()
                ->title('Tanggal Wajib Dipilih')
                ->send();
            return;
        }

        $tanggal = Carbon::parse($this->tanggal);
        $filename = 'Laporan-Kayu-Keluar-' . $tanggal->format('Y-m-d') . '.xlsx';

        Notification::make()
            ->success()
            ->title('Mengekspor Laporan')
            ->body('Mengekspor data kayu keluar tanggal ' . $tanggal->format('d/m/Y'))
            ->send();

        // Download Excel class yang baru dibuat
        return Excel::download(new LaporanKayuKeluarExport($tanggal->toDateString()), $filename);
    }
}
