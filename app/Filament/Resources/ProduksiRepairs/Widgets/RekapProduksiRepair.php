<?php

namespace App\Filament\Resources\ProduksiRepairs\Widgets;

use App\Models\DetailHasilRepair;
use App\Models\Pegawai;
use App\Models\ProduksiRepair;
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

class RekapProduksiRepair extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-repairs.widgets.rekap-produksi-repair';
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
        $tabel = (new ProduksiRepair)->getTable();

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
            ProduksiRepair::query(),
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
        $totalAll = DetailHasilRepair::whereIn('id_produksi_repair', $ids)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // ===== Pekerja (lewat pivot, sama seperti summary widget) =====
        $idRencana = DB::table('detail_repair_pegawai')
            ->join('detail_hasil_repairs', 'detail_hasil_repairs.id', '=', 'detail_repair_pegawai.detail_hasil_repair_id')
            ->whereIn('detail_hasil_repairs.id_produksi_repair', $ids)
            ->pluck('detail_repair_pegawai.rencana_pegawai_repair_id')
            ->filter()
            ->unique()
            ->values();

        $namaPegawai = [];

        // Nama tabel rencana pegawai belum diketahui: dicek dari kandidat
        $tabelRencana = collect(['rencana_pegawai_repairs', 'rencana_pegawai_repair'])
            ->first(fn($t) => DbSchema::hasTable($t));

        if ($tabelRencana && DbSchema::hasColumn($tabelRencana, 'id_pegawai') && $idRencana->isNotEmpty()) {
            $idPegawai = DB::table($tabelRencana)
                ->whereIn('id', $idRencana)
                ->pluck('id_pegawai')
                ->filter()
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
        }

        // ===== Rincian jenis kayu + ukuran + kw (join sama dengan summary widget) =====
        $jenisKayu = 'COALESCE(jk_modal.nama_kayu, jk_direct.nama_kayu, "-")';

        $rows = DetailHasilRepair::query()
            ->whereIn('detail_hasil_repairs.id_produksi_repair', $ids)
            ->leftJoin('modal_repairs', 'modal_repairs.id', '=', 'detail_hasil_repairs.id_modal_repair')
            ->leftJoin('jenis_kayus AS jk_modal', 'jk_modal.id', '=', 'modal_repairs.id_jenis_kayu')
            ->leftJoin('jenis_kayus AS jk_direct', 'jk_direct.id', '=', 'detail_hasil_repairs.id_jenis_kayu')
            ->leftJoin('ukurans', 'ukurans.id', '=', 'detail_hasil_repairs.id_ukuran')
            ->selectRaw('
                ' . $jenisKayu . ' AS jenis_kayu,
                COALESCE(
                    CONCAT(
                        TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                        TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                        TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                    ),
                    "Ukuran tidak terdeteksi"
                ) AS ukuran,
                detail_hasil_repairs.kw AS kw,
                SUM(CAST(detail_hasil_repairs.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy(DB::raw($jenisKayu), 'ukuran', 'detail_hasil_repairs.kw')
            ->orderBy(DB::raw($jenisKayu))
            ->orderBy('ukuran')
            ->orderBy('detail_hasil_repairs.kw')
            ->toBase()
            ->get();

        $this->summary = [
            'ada_data'     => true,
            'totalAll'     => (int) $totalAll,
            'totalPegawai' => (int) $idRencana->count(),
            'rows'         => $rows,
            'namaPegawai'  => $namaPegawai,
        ];
    }
}
