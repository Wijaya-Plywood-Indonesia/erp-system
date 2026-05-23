<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LaporanProduksiKediExport;
use App\Models\ProduksiKedi;
use Filament\Actions\Action;
use Carbon\Carbon;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Actions;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanKedi extends Page
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Kedi';
    protected static ?int $navigationSort = 4;
    protected string $view = 'filament.pages.laporan-kedi';

    public array $dataKedi = [];
    public ?string $tanggal = null;

    public bool $isLoading = false;

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->form->fill([
            'tanggal' => $this->tanggal,
        ]);
        $this->loadAllData();
    }

    protected function getFormSchema(): array
    {
        return [
            Grid::make(3)
                ->schema([
                    DatePicker::make('tanggal')
                        ->label('Pilih Tanggal')
                        ->format('Y-m-d')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->default(now())
                        ->live()
                        ->afterStateUpdated(function ($state) {
                            $this->tanggal = $state;
                            $this->loadAllData();
                        }),
                    Actions::make([
                        Action::make('filter')
                            ->label('Tampilkan Laporan')
                            ->icon('heroicon-o-magnifying-glass')
                            ->action(function () {
                                $data = $this->form->getState();
                                $this->tanggal = $data['tanggal'];
                                $this->loadAllData();
                            }),
                    ])->alignEnd(),
                ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export ke Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('exportToExcel'),
        ];
    }

    public function exportToExcel()
    {
        if (empty($this->dataKedi)) {
            Notification::make()
                ->title('Gagal Export')
                ->body('Tidak ada data Produksi Kedi untuk rentang tanggal ini.')
                ->danger()
                ->send();
            return;
        }

        $filename = 'Laporan-Produksi-Kedi-' . $this->tanggal . '.xlsx';
        return Excel::download(new LaporanProduksiKediExport($this->dataKedi), $filename);
    }

    public function loadAllData(): void
    {
        $this->isLoading = true;

        $produksiList = ProduksiKedi::with([
            'mesin',
            'detailMasukKedi.ukuran',
            'detailMasukKedi.jenisKayu',
            'detailBongkarKedi.ukuran',
            'detailBongkarKedi.jenisKayu',
            'detailPegawaiKedi',
            'validasiTerakhir',
        ])
            ->whereDate('tanggal_actual_bongkar', $this->tanggal)
            ->orderBy('tanggal_actual_bongkar')
            ->get();

        $this->dataKedi = [];

        if ($produksiList->isEmpty()) {
            Notification::make()
                ->title('Data tidak ditemukan')
                ->body('Tidak ada data Produksi Kedi pada tanggal ini.')
                ->warning()
                ->send();
        }

        foreach ($produksiList as $produksi) {
            $status = strtolower($produksi->status);

            // Group Detail Masuk
            $detailMasuk = $produksi->detailMasukKedi
                ->groupBy(fn($d) => $d->id_ukuran . '-' . $d->id_jenis_kayu . '-' . $d->kw)
                ->map(fn($group) => [
                    'no_palet' => $group->pluck('no_palet')->unique()->implode(', '),
                    'mesin' => $produksi->mesin?->nama_mesin ?? '-',
                    'ukuran' => $group->first()->ukuran?->dimensi ?? '-',
                    'jenis_kayu' => $group->first()->jenisKayu?->nama_kayu ?? '-',
                    'kw' => $group->first()->kw,
                    'jumlah' => $group->sum('jumlah'),
                    'rencana_bongkar' => $produksi->rencana_bongkar
                        ? Carbon::parse($produksi->rencana_bongkar)->format('d/m/Y')
                        : '-',
                ])->values()->toArray();

            // Group Detail Bongkar
            $detailBongkar = $produksi->detailBongkarKedi
                ->groupBy(fn($d) => $d->id_ukuran . '-' . $d->id_jenis_kayu . '-' . $d->kw)
                ->map(fn($group) => [
                    'no_palet' => $group->pluck('no_palet')->unique()->implode(', '),
                    'mesin' => $produksi->mesin?->nama_mesin ?? '-',
                    'ukuran' => $group->first()->ukuran?->dimensi ?? '-',
                    'jenis_kayu' => $group->first()->jenisKayu?->nama_kayu ?? '-',
                    'kw' => $group->first()->kw,
                    'jumlah' => $group->sum('jumlah'),
                ])->values()->toArray();

            $this->dataKedi[] = [
                'id' => $produksi->id,
                'tanggal_masuk' => $produksi->tanggal ? Carbon::parse($produksi->tanggal)->format('d/m/Y') : '-',
                'tanggal_keluar' => $produksi->tanggal_actual_bongkar
                    ? Carbon::parse($produksi->tanggal_actual_bongkar)->format('d/m/Y')
                    : '-',
                
                // TAMBAHKAN BARIS INI AGAR BISA DIBACA EXCEL:
                'tanggal_actual_bongkar' => $produksi->tanggal_actual_bongkar 
                    ? Carbon::parse($produksi->tanggal_actual_bongkar)->format('d/m/Y') 
                    : null,

                'status' => $produksi->status,
                'detail_masuk' => $detailMasuk,
                'detail_bongkar' => $detailBongkar,
                'validasi_terakhir' => $produksi->validasiTerakhir?->status ?? '-',
                'validasi_oleh' => $produksi->validasiTerakhir?->role ?? '-',
                'total_pekerja' => $produksi->detailPegawaiKedi->count(),
                'ongkos_mesin' => (float) ($produksi->mesin?->ongkos_mesin ?? 0),
            ];
        }

        $this->isLoading = false;
    }
}
