<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanPilihVeneerExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ProduksiPilihVeneer;
use Carbon\Carbon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

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

    public $reportData = [
        'detail' => [],
        'summary' => []
    ];
    public $tanggal = null;

    public function mount(): void
    {
        $this->form->fill(['tanggal' => $this->tanggal]);
        $this->tanggal = now()->format('Y-m-d');
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
                ->visible(fn() => !empty($this->reportData['detail'])),
        ];
    }

    public function exportExcel()
    {
        try {
            if (empty($this->reportData['detail'])) {
                throw new \Exception('Tidak ada data untuk diunduh.');
            }

            $tglFile = Carbon::parse($this->tanggal)->format('d-m-Y');

            return Excel::download(
                new LaporanPilihVeneerExport($this->reportData, $this->tanggal),
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
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        $produksiList = ProduksiPilihVeneer::with([
            'hasilPilihVeneer.modalPilihVeneer.ukuran',
            'hasilPilihVeneer.modalPilihVeneer.jenisKayu',
            'pegawaiPilihVeneer'
        ])
            ->whereDate('tanggal_produksi', $tanggal)
            ->get();

        $detail = [];
        $summary = [];

        foreach ($produksiList as $prod) {
            $prodM3 = 0;
            foreach ($prod->hasilPilihVeneer as $hasil) {
                $m = $hasil->modalPilihVeneer;
                $u = $m->ukuran;
                $p = $u->panjang ?? 0;
                $l = $u->lebar ?? 0;
                $t = $u->tebal ?? 0;
                $byk = $hasil->jumlah ?? 0;
                $kodeKayu = strtolower($m->jenisKayu->kode_kayu ?? '');
                $grade = strtolower($hasil->kw ?? '');

                $detail[] = [
                    'tanggal' => Carbon::parse($prod->tanggal_produksi)->format('d-M-y'),
                    'p' => $p,
                    'l' => $l,
                    't' => $t,
                    'jenis' => $kodeKayu ?: '-',
                    'kw' => $grade ?: '-',
                    'byk' => $byk,
                    'm3' => '',
                ];
            }

            $summary[] = [
                'tanggal' => Carbon::parse($prod->tanggal_produksi)->format('d-M-y'),
                'ttl_pkj' => $prod->pegawaiPilihVeneer->count(),
                'm3_total' => '',
            ];
        }

        $this->reportData = [
            'detail' => $detail,
            'summary' => $summary
        ];
    }
}
