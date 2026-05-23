<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanPilihPlywoodExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ProduksiPilihPlywood;
use Carbon\Carbon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanProduksiPilihPlywood extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-produksi-pilih-plywood';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Pilih Plywood';
    protected static ?string $navigationLabel = 'Laporan Produksi Pilih Plywood';
    protected static ?int $navigationSort = 17;

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
                new LaporanPilihPlywoodExport($this->reportData, $this->tanggal),
                "laporan-produksi-pilih-plywood-{$tglFile}.xlsx"
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

        $produksiList = ProduksiPilihPlywood::with([
            'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
            'hasilPilihPlywood.barangSetengahJadiHp.grade',
            'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
            'pegawaiPilihPlywood'
        ])
            ->whereDate('tanggal_produksi', $tanggal)
            ->get();

        $detail = [];
        $summary = [];

        foreach ($produksiList as $prod) {
            foreach ($prod->hasilPilihPlywood as $item) {
                $b = $item->barangSetengahJadiHp;
                $u = $b->ukuran ?? null;
                $p = $u->panjang ?? 0;
                $l = $u->lebar ?? 0;
                $t = $u->tebal ?? 0;
                $cacat = $item->jumlah ?? 0;
                $bagus = $item->jumlah_bagus ?? 0;
                $total = $cacat + $bagus;

                $jenisCode = $b->jenisBarang->kode_jenis_barang ?? '';
                $gradeName = $b->grade->nama_grade ?? '';
                $jenisStr = trim($jenisCode . ' ' . $gradeName);

                $detail[] = [
                    'tanggal' => Carbon::parse($prod->tanggal_produksi)->format('d-M-y'),
                    'p' => $p,
                    'l' => $l,
                    't' => $t,
                    'jenis' => $jenisStr ?: '-',
                    'bagus' => $bagus,
                    'cacat' => $cacat,
                    'total' => $total,
                    'm3' => '',
                ];
            }

            $summary[] = [
                'tanggal' => Carbon::parse($prod->tanggal_produksi)->format('d-M-y'),
                'ttl_pkj' => $prod->pegawaiPilihPlywood->count(),
            ];
        }

        $this->reportData = [
            'detail' => $detail,
            'summary' => $summary
        ];
    }
}
