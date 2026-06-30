<?php

namespace App\Filament\Pages;

use App\Services\ProduksiInflowService;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use UnitEnum;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class PersentaseKayu extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Persentase Kayu';

    protected string $view = 'filament.pages.persentase-kayu';

    public array $full_data = [];

    // Menghubungkan variabel ke Query String URL
    protected $queryString = [
        'month' => ['except' => ''],
        'year' => ['except' => ''],
        'nama_lahan' => ['except' => ''],
        'perPage' => ['except' => 10], // Daftarkan di sini
    ];

    public ?string $month = null;
    public ?string $year = null;
    public ?string $nama_lahan = null;
    public $lahans = [];
    public int $perPage = 10;

    public function mount()
    {
        // Default ke bulan & tahun sekarang jika kosong
        $this->month = request()->query('month', date('m'));
        $this->year = request()->query('year', date('Y'));

        $service = new ProduksiInflowService();
        $sheets = $service->getActiveLahanSheets($this->month, $this->year);
        $this->lahans[] = "Semua Lahan";
        $this->lahans = $sheets;

        $lahanPertama = $sheets[0] ?? null;
        $this->nama_lahan= request()->query('nama_lahan', $lahanPertama);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Laporan';
    }
    protected function getViewData(): array
    {
        $service = new ProduksiInflowService();

        // Data diambil berdasarkan paginasi yang diproses di Service
        $dataLaporan = $service->getLaporanBatch($this->month, $this->year, $this->nama_lahan, $this->perPage);
        $rekap = $service->getSummaryLaporanLahan(collect($dataLaporan->items()));
        return [
            'laporan' => $dataLaporan,
            'listLahan' => \App\Models\Lahan::orderBy('nama_lahan')
            ->groupBy('nama_lahan')
            ->pluck('nama_lahan'),
            'rekap' => $rekap,
        ];
    }

    // Fungsi untuk mengupdate filter (dipanggil dari Blade)
    public function updatedFilter()
    {
        $this->resetPage(); // Reset pagination saat filter berubah
    }
}
