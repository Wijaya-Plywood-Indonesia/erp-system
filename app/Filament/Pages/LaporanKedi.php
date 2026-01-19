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
use BackedEnum;
use UnitEnum;

class LaporanKedi extends Page
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Kedi';
    protected static ?int $navigationSort = 4;
    protected string $view = 'filament.pages.laporan-kedi';


    public $dataKedi = [];
    public $dataStik = [];
    public $tanggal = null;
    public bool $isLoading = false;

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->form->fill(['tanggal' => $this->tanggal]);
        $this->loadAllData();
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
                ->required()
                ->maxDate(now())
                ->default(now())
                ->afterStateUpdated(fn($state) => $this->loadAllData()),
        ];
    }

    public function exportToExcel()
    {
        if (empty($this->dataKedi)) {
            Notification::make()->title('Gagal Export')->body('Tidak ada data Produksi Kedi')->danger()->send();
            return;
        }

        $filename = 'Laporan-Produksi-Kedi-' . Carbon::parse($this->tanggal)->format('Y-m-d') . '.xlsx';
        return Excel::download(new LaporanProduksiKediExport($this->dataKedi), $filename);
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

    public function loadAllData()
    {
        $this->isLoading = true;

        $produksiList = ProduksiKedi::with([
            'detailMasukKedi.mesin',
            'detailMasukKedi.ukuran',
            'detailMasukKedi.jenisKayu',

            'detailBongkarKedi.mesin',
            'detailBongkarKedi.ukuran',
            'detailBongkarKedi.jenisKayu',

            'validasiTerakhir',
        ])
            ->whereDate('tanggal', $this->tanggal)
            ->whereHas('validasiTerakhir', fn($q) => $q->where('status', 'divalidasi'))
            ->get();

        $this->dataKedi = [];

        foreach ($produksiList as $produksi) {

            $detailMasuk = $produksi->detailMasukKedi->map(fn($d) => [
                'no_palet' => $d->no_palet,
                'mesin' => $d->mesin?->nama_mesin ?? '-',
                'ukuran' => $d->ukuran?->nama_ukuran ?? '-',
                'jenis_kayu' => $d->jenisKayu?->nama_kayu ?? '-',
                'kw' => $d->kw,
                'jumlah' => $d->jumlah,
                'rencana_bongkar' => $d->rencana_bongkar
                    ? Carbon::parse($d->rencana_bongkar)->format('d/m/Y')
                    : '-',
            ])->toArray();

            $detailBongkar = $produksi->detailBongkarKedi->map(fn($d) => [
                'no_palet' => $d->no_palet,
                'mesin' => $d->mesin?->nama_mesin ?? '-',
                'ukuran' => $d->ukuran?->nama_ukuran ?? '-',
                'jenis_kayu' => $d->jenisKayu?->nama_kayu ?? '-',
                'kw' => $d->kw,
                'jumlah' => $d->jumlah,
                'tanggal_bongkar' => $d->tanggal_bongkar
                    ? Carbon::parse($d->tanggal_bongkar)->format('d/m/Y')
                    : '-',
            ])->toArray();

            $this->dataKedi[] = [
                'id' => $produksi->id,
                'tanggal_produksi' => Carbon::parse($produksi->tanggal)->format('d/m/Y'),
                'status' => $produksi->status,
                'detail_masuk' => $produksi->status === 'masuk' ? $detailMasuk : [],
                'detail_bongkar' => $produksi->status === 'bongkar' ? $detailBongkar : [],
                'validasi_terakhir' => $produksi->validasiTerakhir?->status ?? '-',
                'validasi_oleh' => $produksi->validasiTerakhir?->role ?? '-',
            ];
        }

        $this->isLoading = false;
    }
}
