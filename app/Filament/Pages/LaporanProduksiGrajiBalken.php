<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanProduksiGrajiBalkenExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\HasilGrajiBalken;
use Carbon\Carbon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanProduksiGrajiBalken extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-produksi-graji-balken';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Gergaji Balken';
    protected static ?string $navigationLabel = 'Laporan Produksi Graji Balken';
    protected static ?int $navigationSort = 12;

    public $dataBalken = [];
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
                ->visible(fn() => !empty($this->dataBalken)),
        ];
    }

    public function exportExcel()
    {
        try {
            if (empty($this->dataBalken)) {
                throw new \Exception('Tidak ada data untuk diunduh.');
            }

            $tglFile = Carbon::parse($this->tanggal)->format('d-m-Y');

            return Excel::download(
                new LaporanProduksiGrajiBalkenExport($this->tanggal),
                "laporan-gergaji-balken-{$tglFile}.xlsx"
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

        $data = HasilGrajiBalken::with(['produksiGrajiBalken', 'ukuran', 'jenisKayu'])
            ->whereHas('produksiGrajiBalken', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get();

        $this->dataBalken = [];
        foreach ($data as $item) {
            $u = $item->ukuran;
            $p = $u->panjang ?? 0;
            $l = $u->lebar ?? 0;
            $t = $u->tebal ?? 0;
            $banyak = $item->jumlah;
            $m3 = ($p * $l * $t * $banyak) / 10000000;

            $this->dataBalken[] = [
                'tanggal' => Carbon::parse($item->produksiGrajiBalken->tanggal_produksi)->format('d-M-Y'),
                'p' => $p,
                'l' => $l,
                't' => $t,
                'jenis' => $item->jenisKayu->nama_kayu ?? '-',
                'banyak' => $banyak,
                'm3' => round($m3, 3),
            ];
        }
    }
}
