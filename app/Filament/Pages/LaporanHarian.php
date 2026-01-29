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
use App\Models\Pegawai;
use App\Models\ProduksiRotary;
use App\Models\ProduksiRepair;
use App\Models\ProduksiPressDryer;
use App\Models\ProduksiStik;
use App\Models\ProduksiKedi;
use App\Models\ProduksiJoint;
use App\Models\ProduksiSandingJoint;
use App\Models\ProduksiPotAfJoint;
use App\Models\DetailLainLain;
use App\Models\ProduksiDempul;
use App\Models\ProduksiGrajitriplek;
use App\Models\ProduksiNyusup;
use App\Models\ProduksiSanding;
use App\Models\ProduksiPilihPlywood;
use App\Models\ProduksiHp;

// --- 2. IMPORT TRANSFORMER CLASSES ---
use App\Filament\Pages\LaporanHarian\Transformers\RotaryWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\RepairWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\PressDryerWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\StikWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\KediWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\JointWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\SandingJoinWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\PotAfalanJoinWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\LainLainWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\DempulWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\GrajiTriplekWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\NyusupWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\SandingWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\PilihPlywoodWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\HotpressWorkerMap;

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

    public array $statistics = [
        'rotary' => 0,
        'repair' => 0,
        'dryer' => 0,
        'kedi' => 0,
        'stik' => 0,
        'libur' => 0,
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
                ->visible(fn() => ! empty($this->laporanGabungan)),
        ];
    }

    public function loadData(): void
    {
        $this->isLoading = true;
        $tgl = Carbon::parse($this->data['tanggal'] ?? now())->format('Y-m-d');

        try {
            $this->statistics = [
                'rotary' => 0,
                'repair' => 0,
                'dryer' => 0,
                'kedi' => 0,
                'stik' => 0,
                'libur' => 0,
                'total' => 0,
            ];

            // 1. DATA BEKERJA PER DIVISI
            $listRotary = RotaryWorkerMap::make(ProduksiRotary::with(['mesin', 'detailPegawaiRotary.pegawai'])->whereDate('tgl_produksi', $tgl)->get());
            $listRepair = RepairWorkerMap::make(
                ProduksiRepair::with([
                    'rencanaPegawais.pegawai',
                    'modalRepairs.ukuran',
                    'modalRepairs.jenisKayu',
                    'rencanaPegawais.rencanaRepairs.hasilRepairs'
                ])
                    ->whereDate('tanggal', $tgl) // Filter tanggal di level Produksi
                    ->get()
            );
            $listDryer = PressDryerWorkerMap::make(ProduksiPressDryer::with(['detailPegawais.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listStik = StikWorkerMap::make(ProduksiStik::with(['detailPegawaiStik.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listKedi = KediWorkerMap::make(ProduksiKedi::with(['detailPegawaiKedi.pegawai'])->whereDate('tanggal', $tgl)->get());
            $listJoint = JointWorkerMap::make(ProduksiJoint::with(['pegawaiJoint.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listSandingJoin = SandingJoinWorkerMap::make(ProduksiSandingJoint::with(['pegawaiSandingJoint.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listPotAfJoin = PotAfalanJoinWorkerMap::make(ProduksiPotAfJoint::with(['pegawaiPotAfJoint.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listLainLain = LainLainWorkerMap::make(DetailLainLain::with(['lainLains.pegawai'])->whereDate('tanggal', $tgl)->get());

            $listDempul = DempulWorkerMap::make(
                ProduksiDempul::with(['rencanaPegawaiDempuls.pegawai', 'detailDempuls.barangSetengahJadi.ukuran', 'detailDempuls.barangSetengahJadi.jenisBarang', 'detailDempuls.barangSetengahJadi.grade.kategoriBarang'])->whereDate('tanggal', $tgl)->get()
            );

            $listGrajiTriplek = GrajiTriplekWorkerMap::make(
                ProduksiGrajitriplek::with(['pegawaiGrajiTriplek.pegawaiGrajiTriplek', 'hasilGrajiTriplek.barangSetengahJadiHp.ukuran', 'hasilGrajiTriplek.barangSetengahJadiHp.jenisBarang', 'hasilGrajiTriplek.barangSetengahJadiHp.grade.kategoriBarang'])->whereDate('tanggal_produksi', $tgl)->get()
            );

            $listNyusup = NyusupWorkerMap::make(
                ProduksiNyusup::with(['pegawaiNyusup.pegawai', 'detailBarangDikerjakan.barangSetengahJadiHp.ukuran', 'detailBarangDikerjakan.barangSetengahJadiHp.jenisBarang', 'detailBarangDikerjakan.barangSetengahJadiHp.grade.kategoriBarang'])->whereDate('tanggal_produksi', $tgl)->get()
            );

            $listSanding = SandingWorkerMap::make(
                ProduksiSanding::with(['pegawaiSandings.pegawai', 'hasilSandings.barangSetengahJadi.ukuran', 'hasilSandings.barangSetengahJadi.jenisBarang', 'hasilSandings.barangSetengahJadi.grade.kategoriBarang'])->whereDate('tanggal', $tgl)->get()
            );

            $listPilihPlywood = PilihPlywoodWorkerMap::make(
                ProduksiPilihPlywood::with(['pegawaiPilihPlywood.pegawai', 'hasilPilihPlywood.barangSetengahJadiHp.ukuran', 'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang', 'hasilPilihPlywood.barangSetengahJadiHp.grade.kategoriBarang'])->whereDate('tanggal_produksi', $tgl)->get()
            );

            $listHotpress = HotpressWorkerMap::make(
                ProduksiHp::with([
                    'detailPegawaiHp.pegawaiHp',
                    'platformHasilHp.barangSetengahJadi.ukuran',
                    'platformHasilHp.barangSetengahJadi.grade.kategoriBarang',
                    'triplekHasilHp.barangSetengahJadi.ukuran',
                    'triplekHasilHp.barangSetengahJadi.grade.kategoriBarang',
                ])
                    ->whereDate('tanggal_produksi', $tgl)
                    ->get()
            );

            $pegawaiBekerja = array_merge(
                $listRotary,
                $listRepair,
                $listDryer,
                $listStik,
                $listKedi,
                $listJoint,
                $listSandingJoin,
                $listPotAfJoin,
                $listLainLain,
                $listDempul,
                $listGrajiTriplek,
                $listNyusup,
                $listSanding,
                $listPilihPlywood,
                $listHotpress
            );

            // Update Statistics
            $this->statistics['rotary'] = count($listRotary);
            $this->statistics['repair'] = count($listRepair);
            $this->statistics['dryer'] = count($listDryer);
            $this->statistics['kedi'] = count($listKedi);
            $this->statistics['stik'] = count($listStik);

            // 2. DATA LIBUR (Pegawai yang tidak ada di list kerja)
            $kodePegawaiKerja = array_filter(array_column($pegawaiBekerja, 'kodep'), fn($v) => $v !== '-' && $v !== null);
            $pegawaiLibur = Pegawai::whereNotIn('kode_pegawai', $kodePegawaiKerja)->get();

            $listLibur = [];
            foreach ($pegawaiLibur as $p) {
                $listLibur[] = [
                    'kodep' => $p->kode_pegawai,
                    'nama' => $p->nama_pegawai,
                    'masuk' => '-',
                    'pulang' => '-',
                    'hasil' => '-',
                    'ijin' => '',
                    'potongan_targ' => 0,
                    'keterangan' => '',
                ];
            }
            $this->statistics['libur'] = count($listLibur);

            // 3. GABUNG DAN SORTING (Murni Berdasarkan Kode Pegawai)
            $finalMerge = array_merge($pegawaiBekerja, $listLibur);

            usort($finalMerge, function ($a, $b) {
                $kodeA = (string) ($a['kodep'] ?? '');
                $kodeB = (string) ($b['kodep'] ?? '');

                // Prioritaskan yang punya kode di atas yang kodenya '-' atau kosong
                $isEmptyA = ($kodeA === '' || $kodeA === '-');
                $isEmptyB = ($kodeB === '' || $kodeB === '-');

                if ($isEmptyA && !$isEmptyB) return 1;
                if (!$isEmptyA && $isEmptyB) return -1;

                // Natural sorting (Mengatasi P1, P2, P10 agar berurutan benar)
                return strnatcasecmp($kodeA, $kodeB);
            });

            $this->laporanGabungan = array_values($finalMerge);
            $this->statistics['total'] = count($this->laporanGabungan);

            Notification::make()->success()->title('Data Dimuat')->body("Total {$this->statistics['total']} pegawai")->send();
        } catch (Exception $e) {
            Notification::make()->danger()->title('Error')->body($e->getMessage())->send();
            Log::error($e->getMessage());
            $this->laporanGabungan = [];
        } finally {
            $this->isLoading = false;
        }
    }

    public function exportExcel()
    {
        return Excel::download(
            new LaporanHarianExport($this->laporanGabungan),
            "Laporan-Harian-{$this->data['tanggal']}.xlsx"
        );
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
