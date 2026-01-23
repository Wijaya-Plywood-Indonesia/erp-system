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

// --- 2. IMPORT TRANSFORMER CLASSES ---
use App\Filament\Pages\LaporanHarian\Transformers\RotaryWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\RepairWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\PressDryerWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\StikWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\KediWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\JointWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\SandingJoinWorkerMap;
use App\Filament\Pages\LaporanHarian\Transformers\PotAfalanJoinWorkerMap;

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

            // =====================
            // DATA BEKERJA
            // =====================

            $listRotary = RotaryWorkerMap::make(
                ProduksiRotary::with(['mesin', 'detailPegawaiRotary.pegawai'])
                    ->whereDate('tgl_produksi', $tgl)
                    ->get()
            );
            $this->statistics['rotary'] = count($listRotary);

            $listRepair = RepairWorkerMap::make(
                ProduksiRepair::with(['rencanaPegawais.pegawai'])
                    ->whereDate('tanggal', $tgl)
                    ->get()
            );
            $this->statistics['repair'] = count($listRepair);

            $listDryer = PressDryerWorkerMap::make(
                ProduksiPressDryer::with(['detailPegawais.pegawai'])
                    ->whereDate('tanggal_produksi', $tgl)
                    ->get()
            );
            $this->statistics['dryer'] = count($listDryer);

            $listStik = StikWorkerMap::make(
                ProduksiStik::with(['detailPegawaiStik.pegawai:id,kode_pegawai,nama_pegawai'])
                    ->whereDate('tanggal_produksi', $tgl)
                    ->get()
            );
            $this->statistics['stik'] = count($listStik);

            $listKedi = KediWorkerMap::make(
                ProduksiKedi::with(['detailPegawaiKedi.pegawai:id,kode_pegawai,nama_pegawai'])
                    ->whereDate('tanggal', $tgl)
                    ->get()
            );
            $this->statistics['kedi'] = count($listKedi);

            $listJoint = JointWorkerMap::make(
                ProduksiJoint::with(['pegawaiJoint.pegawai:id,kode_pegawai,nama_pegawai'])
                    ->whereDate('tanggal_produksi', $tgl)
                    ->get()
            );
            $this->statistics['joint'] = count($listJoint);

            $listSandingJoin = SandingJoinWorkerMap::make(
                ProduksiSandingJoint::with(['pegawaiSandingJoint.pegawai:id,kode_pegawai,nama_pegawai'])
                    ->whereDate('tanggal_produksi', $tgl)
                    ->get()
            );
            $this->statistics['sanding'] = count($listSandingJoin);

            $listPotAfJoin = PotAfalanJoinWorkerMap::make(
                ProduksiPotAfJoint::with(['pegawaiPotAfJoint.pegawai:id,kode_pegawai,nama_pegawai'])
                    ->whereDate('tanggal_produksi', $tgl)
                    ->get()
            );
            $this->statistics['pot_afalan'] = count($listPotAfJoin);


            $pegawaiBekerja = array_merge(
                $listRotary,
                $listRepair,
                $listDryer,
                $listStik,
                $listKedi,
                $listJoint,
                $listSandingJoin,
                $listPotAfJoin
            );

            // =====================
            // DATA LIBUR
            // =====================

            $kodePegawaiKerja = array_filter(
                array_column($pegawaiBekerja, 'kodep'),
                fn($v) => $v !== '-' && $v !== null
            );

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

            // =====================
            // GABUNG + SORT (REVISI)
            // =====================

            $finalMerge = array_merge($pegawaiBekerja, $listLibur);

            usort($finalMerge, function ($a, $b) {

                $kodeA = trim((string) ($a['kodep'] ?? ''));
                $kodeB = trim((string) ($b['kodep'] ?? ''));

                $namaA = trim((string) ($a['nama'] ?? ''));
                $namaB = trim((string) ($b['nama'] ?? ''));

                // 1️⃣ Data lengkap (kodep & nama ada)
                $validA = ($kodeA !== '' && $kodeA !== '-' && $namaA !== '' && $namaA !== '-');
                $validB = ($kodeB !== '' && $kodeB !== '-' && $namaB !== '' && $namaB !== '-');

                if ($validA !== $validB) {
                    return $validA ? -1 : 1;
                }

                // 2️⃣ Kode ada tapi nama kosong / '-'
                $halfA = ($kodeA !== '' && $kodeA !== '-' && ($namaA === '' || $namaA === '-'));
                $halfB = ($kodeB !== '' && $kodeB !== '-' && ($namaB === '' || $namaB === '-'));

                if ($halfA !== $halfB) {
                    return $halfA ? 1 : -1;
                }

                // 3️⃣ Urutkan berdasarkan kodep
                $cmpKode = strcmp($kodeA, $kodeB);
                if ($cmpKode !== 0) {
                    return $cmpKode;
                }

                // 4️⃣ Jika kode sama, urutkan nama
                return strcmp($namaA, $namaB);
            });

            $this->laporanGabungan = array_values($finalMerge);
            $this->statistics['total'] = count($this->laporanGabungan);

            Notification::make()
                ->success()
                ->title('Data Dimuat')
                ->body("Total {$this->statistics['total']} pegawai")
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body($e->getMessage())
                ->send();

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
