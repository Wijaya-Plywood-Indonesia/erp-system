<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\Widgets;

use App\Models\ProduksiGrajitriplek;
use App\Models\HasilGrajiTriplek;
use App\Models\PegawaiGrajiTriplek;
use App\Services\ProductionAccessService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RekapProduksiGraji extends Widget implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.resources.produksi-graji-tripleks.widgets.rekap-produksi-graji';
    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];
    public array $summary = [];

    public function mount(): void
    {
        $this->form->fill([
            'tanggal_produksi' => now()->toDateString(),
            'shift'            => 'pagi', // Default shift awal
        ]);
        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal_produksi')
                    ->label('Pilih Tanggal Produksi')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->maxDate(now())
                    ->live()
                    ->closeOnDateSelection()
                    ->afterStateUpdated(fn() => $this->loadData())
                    ->suffixIcon('heroicon-o-calendar')
                    ->suffixIconColor('primary'),

                Select::make('shift')
                    ->label('Shift')
                    ->options([
                        'pagi'   => 'Shift Pagi',
                        'malam'  => 'Shift Malam',
                    ])
                    ->default('pagi')
                    ->live()
                    ->afterStateUpdated(fn() => $this->loadData()),
            ])
            ->columns([
                'default' => 1, // HP: atas-bawah
                'sm'      => 2, // layar sedang ke atas: sejajar
            ])
            ->statePath('data');
    }

    public function loadData(): void
    {
        $tanggal = Carbon::parse($this->data['tanggal_produksi'] ?? now())->toDateString();
        $shift = $this->data['shift'] ?? 'pagi';

        // Mengambil spesifik produksi berdasarkan tanggal DAN shift yang dipilih (tidak digabung)
        $produksiRecord = ProductionAccessService::applyDateRestriction(
            ProduksiGrajitriplek::query(),
            'tanggal_produksi',
            7
        )
            ->whereDate('tanggal_produksi', $tanggal)
            ->where('shift', $shift)
            ->first();

        if (!$produksiRecord) {
            $this->summary = ['ada_data' => false];
            return;
        }

        $produksiId = $produksiRecord->id;

        // Total hasil berdasarkan ID produksi shift tersebut
        $totalAll = HasilGrajiTriplek::where('id_produksi_graji_triplek', $produksiId)->sum('isi');

        // Mengambil data pegawai yang terikat pada produksi shift ini
        // Kita join ke tabel pegawai/karyawan untuk mengambil nama pekerjanya secara langsung
        $pegawaiRows = PegawaiGrajiTriplek::query()
            ->where('id_produksi_graji_triplek', $produksiId)
            ->whereNotNull('id_pegawai')
            ->with('pegawaiGrajiTriplek') // nama relasi sesuai model kamu
            ->get()
            ->unique('id_pegawai');

        $namaPegawai = $pegawaiRows
            ->map(fn($p) => $p->pegawaiGrajiTriplek?->nama_pegawai   // TEBAKAN: cek kolom di model Pegawai
                ?? $p->pegawaiGrajiTriplek?->nama
                ?? 'ID ' . $p->id_pegawai)
            ->values()
            ->all();

        $rows = HasilGrajiTriplek::query()
            ->where('hasil_graji_triplek.id_produksi_graji_triplek', $produksiId)
            ->join('barang_setengah_jadi_hp as bsj', 'bsj.id', '=', 'hasil_graji_triplek.id_barang_setengah_jadi_hp')
            ->join('ukurans', 'ukurans.id', '=', 'bsj.id_ukuran')
            ->join('grades', 'grades.id', '=', 'bsj.id_grade')
            ->join('kategori_barang', 'kategori_barang.id', '=', 'grades.id_kategori_barang')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                CONCAT(kategori_barang.nama_kategori, " ", grades.nama_grade) as kw,
                SUM(hasil_graji_triplek.isi) AS total
            ')
            ->groupBy('ukuran', 'kategori_barang.nama_kategori', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->get();

        $totalPegawai = $pegawaiRows->count();
        $target = $totalPegawai * 750;
        $globalProgress = $target > 0 ? ($totalAll / $target) * 100 : 0;

        $this->summary = [
            'ada_data'       => true,
            'totalAll'       => (int) $totalAll,
            'totalPegawai'   => (int) $totalPegawai,
            'target'         => (int) $target,
            'globalProgress' => round($globalProgress, 1),
            'rows'           => $rows,
            'namaPegawai' => $namaPegawai,
        ];
    }
}
