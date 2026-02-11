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

// --- 1. IMPORT MODELS ---
use App\Models\Pegawai;
use App\Models\DetailAbsensi;
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

// --- 2. IMPORT TRANSFORMERS ---
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
use Illuminate\Support\Facades\Http;

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
    public array $listUnregistered = []; // Properti baru untuk tabel bawah
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
            ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $this->loadData();
                }),

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
        $tgl = Carbon::parse($this->data['tanggal'] ?? now())->toDateString();

        try {
            // 1. Ambil data Fingerprint & Normalisasi Kode (ltrim 0)
            $listFinger = DetailAbsensi::whereDate('tanggal', $tgl)
                ->get()
                ->map(function ($item) {
                    $item->kode_pegawai = ltrim($item->kode_pegawai, '0');
                    return $item;
                })
                ->keyBy('kode_pegawai');

            $countFingerFound = $listFinger->count();

            // 2. Ambil Master Kode Pegawai untuk validasi sinkronisasi
            $kodePegawaiDb = Pegawai::pluck('kode_pegawai')->map(fn($k) => ltrim($k, '0'))->toArray();

            // 3. Ambil Data Produksi (WorkerMap)
            $listRotary = RotaryWorkerMap::make(ProduksiRotary::with(['detailPegawaiRotary.pegawai'])->whereDate('tgl_produksi', $tgl)->get());
            $listRepair = RepairWorkerMap::make(ProduksiRepair::with(['rencanaPegawais.pegawai'])->whereDate('tanggal', $tgl)->get());
            $listDryer = PressDryerWorkerMap::make(ProduksiPressDryer::with(['detailPegawais.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listStik = StikWorkerMap::make(ProduksiStik::with(['detailPegawaiStik.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listKedi = KediWorkerMap::make(ProduksiKedi::with(['detailPegawaiKedi.pegawai'])->whereDate('tanggal', $tgl)->get());
            $listJoint = JoinWorkerMap::make(ProduksiJoint::with(['pegawaiJoint.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listSandingJoin = SandingJoinWorkerMap::make(ProduksiSandingJoint::with(['pegawaiSandingJoint.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listPotAfJoin = PotAfalanJoinWorkerMap::make(ProduksiPotAfJoint::with(['pegawaiPotAfJoint.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listLainLain = LainLainWorkerMap::make(DetailLainLain::with(['lainLains.pegawai'])->whereDate('tanggal', $tgl)->get());
            $listDempul = DempulWorkerMap::make(ProduksiDempul::with(['rencanaPegawaiDempuls.pegawai'])->whereDate('tanggal', $tgl)->get());
            $listGrajiTriplek = GrajiTriplekWorkerMap::make(ProduksiGrajitriplek::with(['pegawaiGrajiTriplek.pegawaiGrajiTriplek'])->whereDate('tanggal_produksi', $tgl)->get());
            $listNyusup = NyusupWorkerMap::make(ProduksiNyusup::with(['pegawaiNyusup.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listSanding = SandingWorkerMap::make(ProduksiSanding::with(['pegawaiSandings.pegawai'])->whereDate('tanggal', $tgl)->get());
            $listPilihPlywood = PilihPlywoodWorkerMap::make(ProduksiPilihPlywood::with(['pegawaiPilihPlywood.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listHotpress = HotpressWorkerMap::make(ProduksiHp::with(['detailPegawaiHp.pegawaiHp'])->whereDate('tanggal_produksi', $tgl)->get());
            $listPotSiku = PotSikuWorkerMap::make(ProduksiPotSiku::with(['pegawaiPotSiku.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listPotJelek = PotJelekWorkerMap::make(ProduksiPotJelek::with(['pegawaiPotJelek.pegawai'])->whereDate('tanggal_produksi', $tgl)->get());
            $listTurunKayu = TurunKayuWorkerMap::make(TurunKayu::with(['pegawaiTurunKayu.pegawai'])->whereDate('tanggal', $tgl)->get());

            $pegawaiBekerjaRaw = array_merge(
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

            // 4. Gabungkan Produksi dengan Log Finger
            $pegawaiBekerja = collect($pegawaiBekerjaRaw)
                ->groupBy('kodep')
                ->map(function ($group) use ($listFinger) {
                    $first = $group->first();
                    $kodep = ltrim($first['kodep'] ?? '-', '0');
                    $allDivisi = $group->pluck('hasil')->unique()->filter()->values()->all();

                    $finger = $listFinger->get($kodep);

                    return [
                        'kodep'      => $kodep,
                        'nama'       => $first['nama'] ?? '-',
                        'masuk'      => $first['masuk'] ?? '-',
                        'pulang'     => $first['pulang'] ?? '-',
                        'f_masuk'    => $finger?->jam_masuk ?? '-',
                        'f_pulang'   => $finger?->jam_pulang ?? '-',
                        'hasil'      => $allDivisi,
                        'ijin'       => $first['ijin'] ?? '',
                        'keterangan' => $first['keterangan'] ?? '',
                    ];
                });

            // 5. Proses Pegawai Libur (Terdaftar di DB tapi tidak ada di Produksi)
            $kodePegawaiKerja = $pegawaiBekerja->keys()->all();
            $pegawaiLibur = Pegawai::whereNotIn('kode_pegawai', $kodePegawaiKerja)->get();

            $listLibur = [];
            foreach ($pegawaiLibur as $p) {
                $cleanKode = ltrim($p->kode_pegawai, '0');
                $fingerLibur = $listFinger->get($cleanKode);

                $listLibur[] = [
                    'kodep'      => $p->kode_pegawai,
                    'nama'       => $p->nama_pegawai,
                    'masuk'      => '-',
                    'pulang'     => '-',
                    'f_masuk'    => $fingerLibur?->jam_masuk ?? '-',
                    'f_pulang'   => $fingerLibur?->jam_pulang ?? '-',
                    'hasil'      => ['-'],
                    'ijin'       => '-',
                    'keterangan' => '-',
                ];
            }

            // 6. IDENTIFIKASI KODE TIDAK TERDAFTAR (Hadir di Finger tapi tidak ada di DB Pegawai)
            $unregisteredFinal = [];
            foreach ($listFinger as $kodeFinger => $dataFinger) {
                if (!in_array($kodeFinger, $kodePegawaiDb)) {
                    $unregisteredFinal[] = [
                        'kodep'      => $kodeFinger,
                        'nama'       => '', // Sesuai permintaan: Nama Kosong
                        'masuk'      => '-',
                        'pulang'     => '-',
                        'f_masuk'    => $dataFinger->jam_masuk,
                        'f_pulang'   => $dataFinger->jam_pulang,
                        'hasil'      => ['Sync Error'],
                        'ijin'       => '-',
                        'keterangan' => 'ID Mesin tidak ada di Database',
                    ];
                }
            }

            // Gabungkan hasil untuk tabel utama (Terdaftar)
            $finalMerge = array_merge($pegawaiBekerja->values()->all(), $listLibur);
            usort($finalMerge, fn($a, $b) => strnatcasecmp((string)($a['kodep'] ?? ''), (string)($b['kodep'] ?? '')));

            $this->listAbsensi = array_values($finalMerge);
            $this->listUnregistered = $unregisteredFinal; // Masukkan ke tabel bawah

            // --- NOTIFIKASI ---
            if ($countFingerFound > 0) {
                $notif = Notification::make()->success()->title('Data Sinkron')->body("Memuat $countFingerFound data finger.");
                if (count($unregisteredFinal) > 0) {
                    $notif->warning()->body("Ada " . count($unregisteredFinal) . " kode tidak terdaftar.");
                }
                $notif->send();
            }
        } catch (\Exception $e) {
            Log::error("ABSEN ERROR: " . $e->getMessage());
            Notification::make()->danger()->title('Gagal memuat data')->send();
        }
        $this->isLoading = false;
    }

    public function syncKeWebsiteLain(): void
    {
        if (empty($this->listUnregistered)) {
            Notification::make()->warning()->title('Tidak ada data anomali untuk disinkron.')->send();
            return;
        }

        $tgl = Carbon::parse($this->data['tanggal'])->toDateString();

        try {
            // URL Website Tujuan (Web B)
            $urlTujuan = 'https://website-b.com/api/sync-absensi';

            $response = Http::post($urlTujuan, [
                'source_name' => 'Website Pusat', // Identitas pengirim
                'tanggal'     => $tgl,
                'absensi'     => $this->listUnregistered, // Kirim data ID tidak terdaftar
            ]);

            if ($response->successful()) {
                // 1. Notifikasi Sukses di Web Pengirim
                Notification::make()
                    ->success()
                    ->title('Sinkronisasi Berhasil')
                    ->body('Data telah berhasil dikirim ke Website tujuan.')
                    ->send();

                // 2. LOGIKA MENGHILANGKAN TABEL BAWAH
                // Karena data sudah dikirim, kita kosongkan array-nya
                $this->listUnregistered = [];

                // 3. Refresh data inti
                $this->loadData();
            } else {
                throw new \Exception('Koneksi gagal atau ditolak oleh server tujuan.');
            }
        } catch (\Exception $e) {
            Notification::make()->danger()->title('Gagal Sinkronisasi')->body($e->getMessage())->send();
        }
    }

    public function exportExcel()
    {
        $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');
        return Excel::download(new \App\Exports\AbsenExport($this->listAbsensi), "Absen-{$tanggal}.xlsx");
    }

    public function getViewData(): array
    {
        return [
            'listAbsensi' => $this->listAbsensi,
            'listUnregistered' => $this->listUnregistered,
            'isLoading' => $this->isLoading
        ];
    }
}
