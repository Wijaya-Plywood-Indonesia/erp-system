<?php

namespace App\Filament\Resources\ProduksiNyusups\Widgets;

use App\Models\DetailBarangDikerjakan;
use App\Models\PegawaiNyusup;
use App\Models\ProduksiNyusup;
use App\Services\ProductionAccessService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RekapProduksiNyusup extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-nyusups.widget.rekap-produksi-nyusup';
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

    public function loadData(): void
    {
        // Selalu bersihkan jadi "Y-m-d" (tanpa jam)
        $tanggal = Carbon::parse($this->data['tanggal'] ?? now())->toDateString();

        // 1) Semua produksi di tanggal itu (tetap mengikuti batas akses 7 hari)
        $ids = ProductionAccessService::applyDateRestriction(
            ProduksiNyusup::query(),
            'tanggal_produksi',
            7
        )
            ->whereDate('tanggal_produksi', $tanggal)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->summary = ['ada_data' => false];
            return;
        }

        // 2) Total produksi
        $totalAll = DetailBarangDikerjakan::whereIn('id_produksi_nyusup', $ids)
            ->sum(DB::raw('CAST(hasil AS UNSIGNED)'));

        // 3) Hasil per jenis kayu + ukuran + grade (sama dengan summary widget)
        $rows = DetailBarangDikerjakan::query()
            ->whereIn('detail_barang_dikerjakan.id_produksi_nyusup', $ids)
            ->join('barang_setengah_jadi_hp as bsj', 'bsj.id', '=', 'detail_barang_dikerjakan.id_barang_setengah_jadi_hp')
            ->join('ukurans', 'ukurans.id', '=', 'bsj.id_ukuran')
            ->join('grades', 'grades.id', '=', 'bsj.id_grade')
            ->join('jenis_barang', 'jenis_barang.id', '=', 'bsj.id_jenis_barang')
            ->selectRaw('
                jenis_barang.nama_jenis_barang AS jenis_kayu,
                CONCAT(
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                grades.nama_grade AS kw,
                SUM(CAST(detail_barang_dikerjakan.hasil AS UNSIGNED)) AS total
            ')
            ->groupBy('jenis_barang.nama_jenis_barang', 'ukuran', 'grades.nama_grade')
            ->orderBy('jenis_barang.nama_jenis_barang')
            ->orderBy('ukuran')
            ->toBase()
            ->get();

        // 4) Pekerja
        $pegawaiQuery = PegawaiNyusup::query()
            ->whereIn('id_produksi_nyusup', $ids)
            ->whereNotNull('id_pegawai');

        // TEBAKAN: relasi bernama "pegawai". Hanya dipakai kalau memang ada.
        if (method_exists(PegawaiNyusup::class, 'pegawai')) {
            $pegawaiQuery->with('pegawai');
        }

        $pegawaiRows = $pegawaiQuery->get()->unique('id_pegawai');

        $namaPegawai = $pegawaiRows
            ->map(fn($p) => $p->pegawai?->nama_pegawai   // TEBAKAN: cek nama kolom di model pegawai
                ?? $p->pegawai?->nama
                ?? 'ID ' . $p->id_pegawai)                // fallback supaya tidak kosong
            ->values()
            ->all();

        $this->summary = [
            'ada_data'      => true,
            'totalAll'      => (int) $totalAll,
            'totalPegawai'  => $pegawaiRows->count(),
            'rows'          => $rows,
            'namaPegawai'   => $namaPegawai,
        ];
    }
}
