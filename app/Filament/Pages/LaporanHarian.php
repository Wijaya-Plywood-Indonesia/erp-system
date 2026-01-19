<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;
use BackedEnum;

// --- 1. IMPORT MODELS ---
use App\Models\Pegawai; // <--- WAJIB TAMBAH INI (Master Pegawai)
use App\Models\ProduksiRotary;
use App\Models\ProduksiRepair;
use App\Models\ProduksiPressDryer;
use App\Models\ProduksiStik;
use App\Models\ProduksiKedi;

// --- 2. IMPORT TRANSFORMER CLASSES ---
use App\Filament\Pages\LaporanHarian\Transformers\RotaryWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\RepairWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\PressDryerWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\StikWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\KediWorkerMap;

use App\Exports\LaporanHarianExport;

class LaporanHarian extends Page implements HasForms
{
    use InteractsWithForms;

    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $title = 'Laporan Harian';

    protected string $view = 'filament.pages.laporan-harian';
    protected static ?int $navigationSort = 1;

    public ?array $data = [
        'tanggal' => null,
    ];

    public array $laporanGabungan = [];
    public bool $isLoading = false;

    // Statistics Ringkasan
    public array $statistics = [
        'rotary' => 0,
        'repair' => 0,
        'dryer' => 0,
        'kedi' => 0,
        'stik' => 0,
        'libur' => 0, // Tambahan stat untuk yang libur
        'total' => 0,
    ];

    public function mount(): void
    {
        $this->data['tanggal'] = now()->format('Y-m-d');
        $this->form->fill($this->data);
        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal')
                    ->label('Pilih Tanggal Laporan')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->maxDate(now())
                    ->default(now())
                    ->live()
                    ->closeOnDateSelection()
                    ->afterStateUpdated(fn() => $this->loadData())
                    ->suffixIcon('heroicon-o-calendar')
                    ->suffixIconColor('primary')
                    ->helperText('Menampilkan status seluruh pegawai (Bekerja & Tidak).'),
            ])
            ->statePath('data')
            ->columns(1);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->loadData()),

            Action::make('export')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn() => $this->exportExcel())
                ->visible(fn() => !empty($this->laporanGabungan)),
        ];
    }

    public function loadData(): void
    {
        $this->isLoading = true;
        $rawTgl = $this->data['tanggal'] ?? now();
        $tgl = Carbon::parse($rawTgl)->format('Y-m-d');

        try {
            Log::info('LaporanHarian: Memuat data untuk tanggal ' . $tgl);

            // Reset Stats
            $this->statistics = ['rotary' => 0, 'repair' => 0, 'dryer' => 0, 'kedi' => 0, 'stik' => 0, 'libur' => 0, 'total' => 0];

            $listRotary = [];
            $listRepair = [];
            $listDryer = [];
            $listStik = [];
            $listKedi = [];

            // ---------------------------------------------------------
            // 1. AMBIL DATA PRODUKSI (YANG BEKERJA)
            // ---------------------------------------------------------

            // Rotary
            try {
                $rawRotary = ProduksiRotary::with(['mesin', 'detailPegawaiRotary.pegawai', 'detailPaletRotary', 'detailGantiPisauRotary'])
                    ->whereDate('tgl_produksi', $tgl)->get();
                $listRotary = RotaryWorkerMap::make($rawRotary);
                $this->statistics['rotary'] = count($listRotary);
            } catch (Exception $e) {
                Log::error("Rotary Error: " . $e->getMessage());
            }

            // Repair
            try {
                $rawRepair = ProduksiRepair::with(['modalRepairs.ukuran', 'modalRepairs.jenisKayu', 'rencanaPegawais.pegawai', 'rencanaPegawais.rencanaRepairs.hasilRepairs'])
                    ->whereDate('tanggal', $tgl)->get();
                $listRepair = RepairWorkerMap::make($rawRepair);
                $this->statistics['repair'] = count($listRepair);
            } catch (Exception $e) {
                Log::error("Repair Error: " . $e->getMessage());
            }

            // Dryer
            try {
                $rawDryer = ProduksiPressDryer::with(['detailPegawais.pegawai', 'detailHasils', 'detailMesins.mesin', 'detailMesins.kategoriMesin'])
                    ->whereDate('tanggal_produksi', $tgl)->orderBy('shift', 'asc')->get();
                $listDryer = PressDryerWorkerMap::make($rawDryer);
                $this->statistics['dryer'] = count($listDryer);
            } catch (Exception $e) {
                Log::error("Dryer Error: " . $e->getMessage());
            }

            // Stik
            try {
                $rawStik = ProduksiStik::with(['detailPegawaiStik.pegawai:id,kode_pegawai,nama_pegawai'])
                    ->whereDate('tanggal_produksi', $tgl)->get();
                $listStik = StikWorkerMap::make($rawStik);
                $this->statistics['stik'] = count($listStik);
            } catch (Exception $e) {
                Log::error("Stik Error: " . $e->getMessage());
            }

            // Kedi
            try {
                $rawKedi = ProduksiKedi::with(['detailPegawaiKedi.pegawai:id,kode_pegawai,nama_pegawai', 'detailMasukKedi', 'detailBongkarKedi'])
                    ->whereDate('tanggal', $tgl)->get();
                $listKedi = KediWorkerMap::make($rawKedi);
                $this->statistics['kedi'] = count($listKedi);
            } catch (Exception $e) {
                Log::error("Kedi Error: " . $e->getMessage());
            }

            // Gabungkan semua yang bekerja
            $pegawaiBekerja = array_merge($listRotary, $listRepair, $listDryer, $listStik, $listKedi);

            // ---------------------------------------------------------
            // 2. CARI PEGAWAI YANG TIDAK BEKERJA
            // ---------------------------------------------------------

            // Ambil semua Kode Pegawai yang sudah ada di list kerja
            // Kita pakai 'kodep' karena itu unik
            $kodePegawaiKerja = array_column($pegawaiBekerja, 'kodep');
            $kodePegawaiKerja = array_filter($kodePegawaiKerja, fn($val) => $val !== '-'); // Hapus yang invalid

            // Ambil Data Master Pegawai yang TIDAK ada di list kerja
            // Sesuaikan query ini jika Anda punya kolom status aktif
            $pegawaiLibur = Pegawai::whereNotIn('kode_pegawai', $kodePegawaiKerja)
                // ->where('status', 'aktif') // UNCOMMENT JIKA INGIN HANYA PEGAWAI AKTIF
                ->orderBy('nama_pegawai', 'asc')
                ->get();

            $listLibur = [];
            foreach ($pegawaiLibur as $p) {
                $listLibur[] = [
                    'kodep' => $p->kode_pegawai,
                    'nama' => $p->nama_pegawai,
                    'masuk' => '-',
                    'pulang' => '-',

                    // LABEL PEMBEDA UTAMA
                    'hasil' => '-',
                    'ijin' => '',
                    'potongan_targ' => 0,
                    'keterangan' => '',
                ];
            }

            $this->statistics['libur'] = count($listLibur);

            // ---------------------------------------------------------
            // 3. GABUNGKAN SEMUA & SORTING
            // ---------------------------------------------------------

            $finalMerge = array_merge($pegawaiBekerja, $listLibur);

            // Sort A-Z berdasarkan Nama
            usort($finalMerge, function ($a, $b) {
                return strcmp($a['nama'] ?? '', $b['nama'] ?? '');
            });

            $this->laporanGabungan = $finalMerge;
            $this->statistics['total'] = count($finalMerge);

            // Notifikasi
            if (empty($finalMerge)) {
                Notification::make()->warning()->title('Data Kosong')->body('Tidak ada data pegawai sama sekali.')->send();
            } else {
                Notification::make()->success()->title('Data Dimuat')->body("Total {$this->statistics['total']} pegawai (Termasuk yang libur).")->send();
            }
        } catch (Exception $e) {
            Notification::make()->danger()->title('Error')->body($e->getMessage())->send();
            Log::error('LaporanHarian Critical Error: ' . $e->getMessage());
            $this->laporanGabungan = [];
        } finally {
            $this->isLoading = false;
        }
    }

    public function exportExcel()
    {
        try {
            $tgl = $this->data['tanggal'];
            return Excel::download(
                new LaporanHarianExport($this->laporanGabungan),
                "Laporan-Harian-Full-{$tgl}.xlsx"
            );
        } catch (Exception $e) {
            Notification::make()->danger()->title('Gagal Export')->body($e->getMessage())->send();
        }
    }

    public function getViewData(): array
    {
        return [
            'laporanGabungan' => $this->laporanGabungan,
            'isLoading' => $this->isLoading,
            'statistics' => $this->statistics,
        ];
    }
}
