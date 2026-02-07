<?php

namespace App\Filament\Pages;

use App\Exports\AbsenExport;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

// --- MODELS ---
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
use App\Models\ProduksiPotSiku;
use App\Models\ProduksiPotJelek;
use App\Models\TurunKayu;

// --- TRANSFORMERS ---
use App\Filament\Pages\Absen\Transformers\RotaryWorkerMap;
use App\Filament\Pages\Absen\Transformers\RepairWorkerMap;
use App\Filament\Pages\Absen\Transformers\PressDryerWorkerMap;
use App\Filament\Pages\Absen\Transformers\StikWorkerMap;
use App\Filament\Pages\Absen\Transformers\KediWorkerMap;
use App\Filament\Pages\Absen\Transformers\JoinWorkerMap;
use App\Filament\Pages\Absen\Transformers\SandingJoinWorkerMap;
use App\Filament\Pages\Absen\Transformers\PotAfalanJoinWorkerMap;
use App\Filament\Pages\Absen\Transformers\LainLainWorkerMap;
use App\Filament\Pages\Absen\Transformers\DempulWorkerMap;
use App\Filament\Pages\Absen\Transformers\GrajiTriplekWorkerMap;
use App\Filament\Pages\Absen\Transformers\NyusupWorkerMap;
use App\Filament\Pages\Absen\Transformers\SandingWorkerMap;
use App\Filament\Pages\Absen\Transformers\PilihPlywoodWorkerMap;
use App\Filament\Pages\Absen\Transformers\HotpressWorkerMap;
use App\Filament\Pages\Absen\Transformers\PotSikuWorkerMap;
use App\Filament\Pages\Absen\Transformers\PotJelekWorkerMap;
use App\Filament\Pages\Absen\Transformers\TurunKayuWorkerMap;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Schemas\Schema;
use UnitEnum;

class Absen extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $title = 'Absensi Pegawai';
    protected string $view = 'filament.pages.absen';
    protected static ?int $navigationSort = 1;

    public array $data = [
        'tanggal' => null,
    ];

    public array $listAbsensi = [];
    public bool $isLoading = false;

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
                ->visible(fn() => ! empty($this->listAbsensi)),
        ];
    }

    public function loadData(): void
    {
        $this->isLoading = true;
        $tgl = $this->data['tanggal'] ?? now()->format('Y-m-d');

        try {
            Log::info("ABSEN: Memproses data tanggal {$tgl}");

            // 1. Ambil data dari semua divisi
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
            $listJoint = JoinWorkerMap::make(ProduksiJoint::with(['pegawaiJoint.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
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


            $listPotSiku = PotSikuWorkerMap::make(
                ProduksiPotSiku::with([
                    'pegawaiPotSiku.pegawai',
                    'detailBarangDikerjakanPotSiku.ukuran',
                    'detailBarangDikerjakanPotSiku.jenisKayu'
                ])->whereDate('tanggal_produksi', $tgl)->get()
            );

            $listPotJelek = PotJelekWorkerMap::make(
                ProduksiPotJelek::with([
                    'pegawaiPotJelek.pegawai',
                    'detailBarangDikerjakanPotJelek.ukuran',
                    'detailBarangDikerjakanPotJelek.jenisKayu'
                ])->whereDate('tanggal_produksi', $tgl)->get()
            );

            $listTurunKayu = TurunKayuWorkerMap::make(
                TurunKayu::with(['pegawaiTurunKayu.pegawai'])
                    ->whereDate('tanggal', $tgl)
                    ->get()
            );

            // 2. Gabungkan
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
                $listHotpress,
                $listPotSiku,
                $listPotJelek,
                $listTurunKayu
            );

            // 3. Pegawai Libur
            $kodePegawaiKerja = array_filter(array_column($pegawaiBekerja, 'kodep'), fn($v) => $v !== '-' && $v !== null);
            $pegawaiLibur = Pegawai::whereNotIn('kode_pegawai', $kodePegawaiKerja)->where('is_active', true)->get();

            $listLibur = [];
            foreach ($pegawaiLibur as $p) {
                $listLibur[] = [
                    'kodep' => $p->kode_pegawai,
                    'nama' => $p->nama_pegawai,
                    'masuk' => '-',
                    'pulang' => '-',
                    'hasil' => '-',
                    'ijin' => '',
                    'keterangan' => 'LIBUR / TIDAK ADA JADWAL',
                ];
            }

            // 4. Final Merge & Sort
            $finalMerge = array_merge($pegawaiBekerja, $listLibur);
            usort($finalMerge, fn($a, $b) => strnatcasecmp((string)($a['kodep'] ?? ''), (string)($b['kodep'] ?? '')));

            $this->listAbsensi = array_values($finalMerge);
            Log::info("ABSEN SUCCESS: Total " . count($this->listAbsensi) . " record.");
        } catch (\Exception $e) {
            Log::error("ABSEN ERROR: " . $e->getMessage());
            Notification::make()->danger()->title('Gagal memuat data')->send();
        }
        $this->isLoading = false;
    }

    public function exportExcel()
    {
        $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');
        return Excel::download(new AbsenExport($this->listAbsensi), "Absensi-{$tanggal}.xlsx");
    }

    public function getViewData(): array
    {
        return [
            'listAbsensi' => $this->listAbsensi,
            'isLoading' => $this->isLoading,
        ];
    }
}
