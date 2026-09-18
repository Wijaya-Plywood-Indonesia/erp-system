<?php

namespace App\Filament\Pages;

use App\Exports\LaporanDempulExport;
use App\Models\ProduksiDempul;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class LaporanProduksiDempul extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected string $view = 'filament.pages.laporan-produksi-dempul';

    protected static UnitEnum|string|null $navigationGroup = 'Laporan';

    protected static ?string $title = 'Laporan Produksi Dempul';

    protected static ?string $navigationLabel = 'Laporan Produksi Dempul';

    protected static ?int $navigationSort = 14;

    protected static bool $shouldRegisterNavigation = false;

    public $reportData = [
        'detail' => [],
        'summary' => [],
    ];

    public $tanggal = null;

    // cache kolom tanggal yang benar-benar ada di tabel
    protected static ?string $kolomTanggal = null;

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
                ->action(fn () => $this->loadAllData()),

            Action::make('exportExcel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => $this->exportExcel())
                ->visible(fn () => ! empty($this->reportData['detail'])),
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

    /**
     * Deteksi nama kolom tanggal yang tersedia di tabel produksi_dempuls.
     * Prioritas: 'tanggal' dulu, kalau tidak ada baru 'tanggal_produksi'.
     */
    protected function getKolomTanggal(): string
    {
        if (static::$kolomTanggal !== null) {
            return static::$kolomTanggal;
        }

        $table = (new ProduksiDempul)->getTable();

        if (Schema::hasColumn($table, 'tanggal')) {
            return static::$kolomTanggal = 'tanggal';
        }

        if (Schema::hasColumn($table, 'tanggal_produksi')) {
            return static::$kolomTanggal = 'tanggal_produksi';
        }

        throw new \Exception("Kolom tanggal tidak ditemukan di tabel {$table}. Pastikan tabel memiliki kolom 'tanggal' atau 'tanggal_produksi'.");
    }

    public function loadAllData()
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');
        $kolomTanggal = $this->getKolomTanggal();

        $produksiList = ProduksiDempul::with([
            'detailDempuls.barangSetengahJadi.ukuran',
            'detailDempuls.barangSetengahJadi.grade',
            'detailDempuls.barangSetengahJadi.jenisBarang',
            'detailDempuls.pegawais',
        ])
            ->whereDate($kolomTanggal, $tanggal)
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
                    'tanggal' => Carbon::parse($prod->{$kolomTanggal})->format('d-M-y'),
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
                'tanggal' => Carbon::parse($prod->{$kolomTanggal})->format('d-M-y'),
                'ttl_pkj' => $uniqueWorkers->unique()->count(),
                'm3_total' => '',
            ];
        }

        $this->reportData = [
            'detail' => $detail,
            'summary' => $summary,
        ];
    }
}
