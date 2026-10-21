<?php

namespace App\Filament\Resources\ProduksiStiks\Widgets;

use App\Models\DetailHasilStik;
use App\Models\DetailPegawaiStik;
use App\Models\Pegawai;
use App\Models\ProduksiStik;
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

class RekapProduksiStik extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-stiks.widgets.rekap-produksi-stik';
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
        $tabel = (new ProduksiStik)->getTable();

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
            ProduksiStik::query(),
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
        $totalAll = DetailHasilStik::whereIn('id_produksi_stik', $ids)
            ->sum(DB::raw('CAST(total_lembar AS UNSIGNED)'));

        // ===== Pekerja =====
        $idPegawai = DetailPegawaiStik::whereIn('id_produksi_stik', $ids)
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

        // ===== Rincian hasil per jenis kayu + ukuran + kw =====
        $rows = DetailHasilStik::query()
            ->whereIn('detail_hasil_stik.id_produksi_stik', $ids)
            ->leftJoin('ukurans', 'ukurans.id', '=', 'detail_hasil_stik.id_ukuran')
            ->leftJoin('jenis_kayus', 'jenis_kayus.id', '=', 'detail_hasil_stik.id_jenis_kayu')
            ->selectRaw("
                detail_hasil_stik.id_jenis_kayu AS id_jenis_kayu,
                detail_hasil_stik.id_ukuran AS id_ukuran,
                COALESCE(jenis_kayus.nama_kayu, '-') AS jenis_kayu,
                COALESCE(
                    CONCAT(
                        TRIM(TRAILING '.00' FROM CAST(ukurans.panjang AS CHAR)), ' x ',
                        TRIM(TRAILING '.00' FROM CAST(ukurans.lebar AS CHAR)), ' x ',
                        TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(ukurans.tebal AS CHAR)))
                    ),
                    'Ukuran tidak terdeteksi'
                ) AS ukuran,
                detail_hasil_stik.kw AS kw,
                SUM(CAST(detail_hasil_stik.total_lembar AS UNSIGNED)) AS total
            ")
            ->groupBy(
                'detail_hasil_stik.id_jenis_kayu',
                'detail_hasil_stik.id_ukuran',
                'jenis_kayus.nama_kayu',
                'ukuran',
                'detail_hasil_stik.kw'
            )
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('ukuran')
            ->orderBy('detail_hasil_stik.kw')
            ->toBase()
            ->get();

        // ===== Bahan masuk: satu query, lalu dicocokkan per baris di PHP =====
        $bahan = DB::table('detail_masuk_stik')
            ->whereIn('id_produksi_stik', $ids)
            ->selectRaw('id_jenis_kayu, id_ukuran, kw, SUM(CAST(isi AS UNSIGNED)) AS total_bahan')
            ->groupBy('id_jenis_kayu', 'id_ukuran', 'kw')
            ->get()
            ->keyBy(fn($b) => $b->id_jenis_kayu . '|' . $b->id_ukuran . '|' . $b->kw);

        $rows->each(function ($r) use ($bahan) {
            $r->total_bahan = (int) ($bahan[$r->id_jenis_kayu . '|' . $r->id_ukuran . '|' . $r->kw]->total_bahan ?? 0);
        });

        // ===== Target global mesin STIK (sama seperti summary widget) =====
        $mesinIds = DB::table('mesins')
            ->join('kategori_mesins', 'mesins.kategori_mesin_id', '=', 'kategori_mesins.id')
            ->where('kategori_mesins.nama_kategori_mesin', 'STIK')
            ->pluck('mesins.id')
            ->toArray();

        if (empty($mesinIds)) {
            $mesinIds = [8];
        }

        $tgt = DB::table('targets')
            ->whereIn('id_mesin', $mesinIds)
            ->where('id_ukuran', 33)
            ->first();

        // ASUMSI: satu target per produksi, jadi dikalikan jumlah produksi di tanggal itu
        $target   = $tgt ? (float) $tgt->target * $ids->count() : 0;
        $progress = $target > 0 ? min(round(($totalAll / $target) * 100, 1), 100) : 0;

        $this->summary = [
            'ada_data'     => true,
            'totalAll'     => (int) $totalAll,
            'totalPegawai' => (int) $idPegawai->count(),
            'rows'         => $rows,
            'namaPegawai'  => $namaPegawai,
        ];
    }
}
