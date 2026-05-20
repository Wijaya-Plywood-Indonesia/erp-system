<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanGrajiTriplekExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ProduksiGrajitriplek;
use Carbon\Carbon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanProduksiGrajiTriplek extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-produksi-graji-triplek';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Graji Triplek';
    protected static ?string $navigationLabel = 'Laporan Produksi Graji Triplek';
    protected static ?int $navigationSort = 15;

    public $reportData = [
        'detail' => [],
        'summary' => []
    ];
    public ?array $data = [];
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
                new LaporanGrajiTriplekExport($this->reportData, $this->tanggal),
                "laporan-produksi-graji-triplek-{$tglFile}.xlsx"
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

        $produksiList = ProduksiGrajitriplek::with([
            'hasilGrajiTriplek.barangSetengahJadiHp.ukuran',
            'hasilGrajiTriplek.barangSetengahJadiHp.grade',
            'hasilGrajiTriplek.barangSetengahJadiHp.jenisBarang',
            'pegawaiGrajiTriplek'
        ])
            ->whereDate('tanggal_produksi', $tanggal)
            ->get();

        $detail = [];
        $summary = [];

        foreach ($produksiList as $prod) {
            foreach ($prod->hasilGrajiTriplek as $item) {
                $b = $item->barangSetengahJadiHp;
                $u = $b->ukuran ?? null;
                $p = $u->panjang ?? 0;
                $l = $u->lebar ?? 0;
                $t = $u->tebal ?? 0;
                $byk = $item->isi ?? 0;

                $detail[] = [
                    'tanggal' => Carbon::parse($prod->tanggal_produksi)->format('d-M-y'),
                    'p' => $p,
                    'l' => $l,
                    't' => $t,
                    'jenis' => $b->jenisBarang->nama_jenis_barang ?? '-',
                    'grade' => $b->grade->nama_grade ?? '-',
                    'byk' => $byk,
                    'm3' => '',
                ];
            }

            $summary[] = [
                'tanggal' => Carbon::parse($prod->tanggal_produksi)->format('d-M-y'),
                'ttl_pkj' => $prod->pegawaiGrajiTriplek->count(),
                'm3_total' => '',
            ];
        }

        $this->reportData = [
            'detail' => $detail,
            'summary' => $summary
        ];
    }
}
