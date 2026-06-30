<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanDempulExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ProduksiDempul;
use Carbon\Carbon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanProduksiDempul extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-produksi-dempul';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Dempul';
    protected static ?string $navigationLabel = 'Laporan Produksi Dempul';
    protected static ?int $navigationSort = 14;

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
                new LaporanDempulExport($this->reportData, $this->tanggal),
                "laporan-produksi-dempul-{$tglFile}.xlsx"
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

        $produksiList = ProduksiDempul::with([
            'detailDempuls.barangSetengahJadi.ukuran',
            'detailDempuls.barangSetengahJadi.grade',
            'detailDempuls.barangSetengahJadi.jenisBarang',
            'detailDempuls.pegawais'
        ])
            ->whereDate('tanggal', $tanggal)
            ->get();

        $detail = [];
        $summary = [];

        foreach ($produksiList as $prod) {
            $uniqueWorkers = collect();
            foreach ($prod->detailDempuls as $item) {
                $b = $item->barangSetengahJadi;
                $u = $b->ukuran ?? null;
                $p = $u->panjang ?? 0;
                $l = $u->lebar ?? 0;
                $t = $u->tebal ?? 0;
                $byk = $item->hasil ?? 0;

                $detail[] = [
                    'tanggal' => Carbon::parse($prod->tanggal)->format('d-M-y'),
                    'p' => $p,
                    'l' => $l,
                    't' => $t,
                    'jenis' => $b->jenisBarang->nama_jenis_barang ?? '-',
                    'grade' => $b->grade->nama_grade ?? '-',
                    'byk' => $byk,
                    'm3' => '',
                ];

                foreach ($item->pegawais as $pegawai) {
                    $uniqueWorkers->push($pegawai->id);
                }
            }

            $summary[] = [
                'tanggal' => Carbon::parse($prod->tanggal)->format('d-M-y'),
                'ttl_pkj' => $uniqueWorkers->unique()->count(),
                'm3_total' => '',
            ];
        }

        $this->reportData = [
            'detail' => $detail,
            'summary' => $summary
        ];
    }
}
