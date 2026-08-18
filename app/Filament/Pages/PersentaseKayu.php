<?php

namespace App\Filament\Pages;

use App\Models\Lahan;
use App\Services\ProduksiInflowService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use UnitEnum;

class PersentaseKayu extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

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
        'perPage' => ['except' => 10],
    ];

    public ?string $month = null;

    public ?string $year = null;

    public ?string $nama_lahan = null;

    public $lahans = [];

    public int $perPage = 10;

    // Sementara untuk testing (pagination UI dinonaktifkan di Blade):
    // set true supaya SEMUA data bulan tsb diambil tanpa batas perPage.
    protected bool $loadAllForTesting = true;

    // Flag: true setelah data laporan selesai dimuat (dipicu via wire:init di Blade)
    public bool $dataLoaded = false;

    public function mount()
    {
        // Default ke bulan & tahun sekarang jika kosong -> halaman langsung tampil dengan filter ini
        $this->month = request()->query('month', date('m'));
        $this->year = request()->query('year', date('Y'));

        $service = new ProduksiInflowService;
        $sheets = $service->getActiveLahanSheets($this->month, $this->year);
        $this->lahans = $sheets;

        $lahanPertama = $sheets[0] ?? null;
        $this->nama_lahan = request()->query('nama_lahan', $lahanPertama);

        // NOTE: query laporan yang berat SENGAJA tidak dijalankan di sini.
        // Halaman akan langsung dirender (dataLoaded = false), lalu Blade
        // memicu method loadData() via wire:init setelah komponen tampil di browser.
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Laporan';
    }

    /**
     * Dipicu otomatis dari Blade via wire:init="loadData" setelah halaman pertama render,
     * dan juga dipanggil ulang setiap kali filter berubah (lihat updatedMonth dkk di bawah).
     */
    public function loadData(): void
    {
        $this->dataLoaded = true;
    }

    /**
     * Setiap ganti filter, reset pagination dan biarkan getViewData() menghitung ulang.
     * dataLoaded tetap true supaya tidak balik ke skeleton kosong, cukup spinner overlay
     * (lihat wire:loading di Blade) yang menandakan sedang memuat ulang.
     */
    public function updatedMonth(): void
    {
        $this->refreshLahanOptions();
        $this->resetPage();
    }

    public function updatedYear(): void
    {
        $this->refreshLahanOptions();
        $this->resetPage();
    }

    public function updatedNamaLahan(): void
    {
        $this->resetPage();
    }

    /**
     * Hitung ulang daftar lahan aktif & pastikan nama_lahan yang terpilih masih valid
     * untuk bulan/tahun yang baru. Dipanggil setiap kali filter month/year berubah,
     * supaya tidak terjadi filter nama_lahan "nyangkut" ke lahan yang tidak aktif
     * di periode baru (yang menyebabkan hasil laporan kosong).
     */
    protected function refreshLahanOptions(): void
    {
        $service = new ProduksiInflowService;
        $sheets = $service->getActiveLahanSheets($this->month, $this->year);

        $this->lahans = $sheets;

        // Kalau lahan yang sedang dipilih tidak ada di daftar lahan aktif bulan baru
        // (dan bukan opsi "Semua Lahan"), fallback ke lahan pertama yang tersedia.
        if ($this->nama_lahan !== 'Semua Lahan' && ! in_array($this->nama_lahan, $sheets, true)) {
            $this->nama_lahan = $sheets[0] ?? 'Semua Lahan';
        }
    }

    // Fungsi untuk mengupdate filter (dipanggil dari Blade)
    public function updatedFilter()
    {
        $this->resetPage(); // Reset pagination saat filter berubah
    }

    protected function getViewData(): array
    {
        $listLahan = Lahan::orderBy('nama_lahan')
            ->groupBy('nama_lahan')
            ->pluck('nama_lahan');

        // Sebelum wire:init memicu loadData(), jangan jalankan query berat sama sekali.
        // Ini yang membuat render pertama halaman terasa instan.
        if (! $this->dataLoaded) {
            return [
                'laporan' => null,
                'listLahan' => $listLahan,
                'rekap' => [
                    'total_kayu_masuk' => 0,
                    'total_kubikasi_kayu_masuk' => 0,
                    'total_kubikasi_veneer' => 0,
                    'rata_rata_rendemen' => '0%',
                    'total_poin_masuk' => 0,
                    'total_harga_veneer' => 0,
                    'total_harga_v_ongkos' => 0,
                    'total_harga_vop' => 0,
                    // FIX: tambahkan default untuk kolom baru
                    // "Veneer+Ongkos+Susut+Bahan Penolong" supaya tidak
                    // undefined-array-key saat halaman pertama render
                    // (sebelum wire:init memicu loadData()).
                    'total_harga_vopb' => 0,
                ],
            ];
        }

        $service = new ProduksiInflowService;

        // Data diambil berdasarkan paginasi yang diproses di Service.
        // Selama testing (pagination UI nonaktif), ambil semua data dengan perPage besar
        // supaya tidak ada baris yang "hilang" karena limit halaman pertama saja.
        $effectivePerPage = $this->loadAllForTesting ? 1000000 : $this->perPage;

        $dataLaporan = $service->getLaporanBatch($this->month, $this->year, $this->nama_lahan, $effectivePerPage);
        $rekap = $service->getSummaryLaporanLahan(collect($dataLaporan->items()));

        return [
            'laporan' => $dataLaporan,
            'listLahan' => $listLahan,
            'rekap' => $rekap,
        ];
    }
}
