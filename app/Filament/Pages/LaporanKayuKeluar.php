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

    public function getAccountDetails(string $jenisKayuNama, $panjang): array
    {
        $jenis = strtolower(trim($jenisKayuNama));
        $panjang = (int) $panjang;

        $isWHN = false;
        if (request()) {
            $host = request()->getHost();
            if ($host === 'wahana.wijayaplywoods.com' || env('APP_COMPANY') === 'WHN') {
                $isWHN = true;
            }
        }

        $isLunak = (str_contains($jenis, 'sengon') || str_contains($jenis, 'lunak'));
        $isMeranti = str_contains($jenis, 'meranti');
        $isRijek = str_contains($jenis, 'rijek');
        $isLogCore = str_contains($jenis, 'log core') || str_contains($jenis, 'core');
        $isBaloan = str_contains($jenis, 'baloan') || str_contains($jenis, 'balo,an');

        if ($isWHN) {
            if ($isMeranti) {
                return ['no_akun' => '1413.09', 'nama_akun' => 'Kayu Meranti WHN'];
            }
            if ($isRijek) {
                return ['no_akun' => '1413.10', 'nama_akun' => 'Kayu Rijek WHN'];
            }
            if ($isLogCore) {
                if ($panjang === 130) {
                    return ['no_akun' => '1414.00', 'nama_akun' => 'log core 130 WHN'];
                }
                return ['no_akun' => '1413.13', 'nama_akun' => 'log core 260 WHN'];
            }
            if ($isBaloan) {
                return ['no_akun' => '1413.05', 'nama_akun' => 'kayu balo,an'];
            }

            // Lunak
            if ($isLunak) {
                if ($panjang === 130) {
                    return ['no_akun' => '1413.07', 'nama_akun' => 'Kayu Lunak 130 WHN'];
                }
                if ($panjang === 230) {
                    return ['no_akun' => '1413.11', 'nama_akun' => 'kayu Lunak 230 WHN'];
                }
                if ($panjang === 100) {
                    return ['no_akun' => '1413.12', 'nama_akun' => 'kayu Lunak 100 WHN'];
                }
                return ['no_akun' => '1413.01', 'nama_akun' => 'kayu Lunak 260 WHN'];
            }

            // Keras (default)
            if ($panjang === 130) {
                return ['no_akun' => '1413.08', 'nama_akun' => 'Kayu Keras 130 WHN'];
            }
            return ['no_akun' => '1413.06', 'nama_akun' => 'Kayu Keras 260 WHN'];
        } else {
            // WJY
            if ($isMeranti) {
                return ['no_akun' => '1411.05', 'nama_akun' => 'Kayu Meranti WJY'];
            }
            if ($isRijek) {
                return ['no_akun' => '1411.06', 'nama_akun' => 'Kayu Rijek WJY'];
            }
            if ($isLogCore) {
                if ($panjang === 130) {
                    return ['no_akun' => '1413.04', 'nama_akun' => 'log core 130 WJY'];
                }
                return ['no_akun' => '1413.03', 'nama_akun' => 'log core 260 WJY'];
            }
            if ($isBaloan) {
                return ['no_akun' => '1413.05', 'nama_akun' => 'kayu balo,an'];
            }

            // Lunak
            if ($isLunak) {
                if ($panjang === 130) {
                    return ['no_akun' => '1411.03', 'nama_akun' => 'Kayu Lunak 130 WJY'];
                }
                if ($panjang === 230) {
                    return ['no_akun' => '1411.07', 'nama_akun' => 'kayu Lunak 230 WJY'];
                }
                if ($panjang === 100) {
                    return ['no_akun' => '1411.08', 'nama_akun' => 'kayu Lunak 100 WJY'];
                }
                return ['no_akun' => '1411.01', 'nama_akun' => 'kayu Lunak 260 WJY'];
            }

            // Keras (default)
            if ($panjang === 130) {
                return ['no_akun' => '1411.04', 'nama_akun' => 'Kayu Keras 130 WJY'];
            }
            return ['no_akun' => '1411.02', 'nama_akun' => 'Kayu Keras 260 WJY'];
        }
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

        // Pre-calculate totals
        foreach ($records as $record) {
            $totalBanyak += ($record->total_batang > 0 ? $record->total_batang : 0);
            $totalM3 += ($record->total_kubikasi > 0 ? $record->total_kubikasi : 0);
            $totalHarga += $record->nilai_stok;
        }

        // 1. Add Debit row first: HPP Triplek
        if (!$records->isEmpty()) {
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
                'hit_kbk' => '',
                'banyak' => $totalBanyak > 0 ? $totalBanyak : null,
                'm3' => $totalM3 > 0 ? $totalM3 : null,
                'harga' => $totalHarga,
                'total' => $totalHarga,
            ];
        }

        // 2. Add Credit rows second
        foreach ($records as $record) {
            $jenisNama = $record->jenisKayu?->nama_kayu ?? '-';
            $panjang = $record->panjang;
            $acc = $this->getAccountDetails($jenisNama, $panjang);
            $noAkun = $acc['no_akun'];
            $namaAkun = $acc['nama_akun'];

            $keteranganSpec = "lahan " . ($record->lahan->kode_lahan ?? '-');

            $banyak = $record->total_batang > 0 ? $record->total_batang : 0;
            $m3 = $record->total_kubikasi > 0 ? $record->total_kubikasi : 0;
            $totalStokValue = $record->nilai_stok;

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
                'hit_kbk' => '',
                'banyak' => $banyak > 0 ? $banyak : null,
                'm3' => $m3 > 0 ? $m3 : null,
                'harga' => $totalStokValue,
                'total' => $totalStokValue,
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
