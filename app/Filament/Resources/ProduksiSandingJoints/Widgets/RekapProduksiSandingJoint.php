<?php

namespace App\Filament\Resources\ProduksiSandingJoints\Widgets;

use App\Models\HasilSandingJoint;
use App\Models\Pegawai;
use App\Models\PegawaiSandingJoint;
use App\Models\ProduksiSandingJoint;
use App\Services\ProductionAccessService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DbSchema;

class RekapProduksiSandingJoint extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-sanding-joints.widgets.rekap-produksi-sanding-joint';
    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];
    public array $summary = [];

    public function mount(): void
    {
        $this->form->fill(['tanggal' => now()->toDateString()]);
        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal')
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
            ])
            ->statePath('data');
    }

    /** Nama kolom tanggal dipilih otomatis, tidak ditebak. */
    protected function kolomTanggal(): string
    {
        $tabel = (new ProduksiSandingJoint)->getTable();

        return DbSchema::hasColumn($tabel, 'tanggal_produksi')
            ? 'tanggal_produksi'
            : 'tanggal';
    }

    public function loadData(): void
    {
        // Selalu "Y-m-d" tanpa jam
        $tanggal = Carbon::parse($this->data['tanggal'] ?? now())->toDateString();
        $kolom   = $this->kolomTanggal();

        // Semua produksi di tanggal itu (tetap mengikuti batas akses 7 hari)
        $ids = ProductionAccessService::applyDateRestriction(
            ProduksiSandingJoint::query(),
            $kolom,
            7
        )
            ->whereDate($kolom, $tanggal)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->summary = ['ada_data' => false];
            return;
        }

        // ===== Total hasil =====
        $totalAll = HasilSandingJoint::whereIn('id_produksi_sanding_joint', $ids)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // ===== Pekerja =====
        $idPegawai = PegawaiSandingJoint::whereIn('id_produksi_sanding_joint', $ids)
            ->whereNotNull('id_pegawai')
            ->pluck('id_pegawai')
            ->unique()
            ->values();

        $namaPegawai = Pegawai::whereIn('id', $idPegawai)
            ->get()
            ->map(fn($p) => $p->nama_pegawai
                ?? $p->nama
                ?? $p->nama_lengkap
                ?? 'ID ' . $p->id)
            ->values()
            ->all();

        // ===== Rincian jenis kayu + ukuran + kw (join sama dengan summary widget) =====
        $rows = HasilSandingJoint::query()
            ->whereIn('hasil_sanding_joint.id_produksi_sanding_joint', $ids)
            ->leftJoin('ukurans', 'ukurans.id', '=', 'hasil_sanding_joint.id_ukuran')
            ->leftJoin('jenis_kayus', 'jenis_kayus.id', '=', 'hasil_sanding_joint.id_jenis_kayu')
            ->selectRaw('
                COALESCE(jenis_kayus.nama_kayu, "-") AS jenis_kayu,
                COALESCE(
                    CONCAT(
                        TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
                        TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
                        TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                    ),
                    "Ukuran tidak terdeteksi"
                ) AS ukuran,
                hasil_sanding_joint.kw AS kw,
                SUM(CAST(hasil_sanding_joint.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('jenis_kayus.nama_kayu', 'ukuran', 'hasil_sanding_joint.kw')
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('ukuran')
            ->toBase()
            ->get();

        $this->summary = [
            'ada_data'     => true,
            'totalAll'     => (int) $totalAll,
            'totalPegawai' => (int) $idPegawai->count(),
            'rows'         => $rows,
            'namaPegawai'  => $namaPegawai,
        ];
    }
}
