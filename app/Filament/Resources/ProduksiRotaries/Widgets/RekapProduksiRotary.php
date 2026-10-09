<?php

namespace App\Filament\Resources\ProduksiRotaries\Widgets;

use App\Models\DetailHasilPaletRotary;
use App\Models\Mesin;
use App\Models\Pegawai;
use App\Models\ProduksiRotary;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DbSchema;

class RekapProduksiRotary extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-rotaries.widgets.rekap-produksi-rotary';
    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];
    public array $summary = [];

    public function mount(): void
    {
        $this->form->fill([
            'tanggal_produksi' => now()->toDateString(),
            'shift'            => 'pagi',
        ]);

        $this->pilihMesinPertama();
        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal_produksi')
                    ->label('Tanggal Produksi')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->maxDate(now())
                    ->live()
                    ->closeOnDateSelection()
                    ->afterStateUpdated(function () {
                        $this->pilihMesinPertama();
                        $this->loadData();
                    })
                    ->suffixIcon('heroicon-o-calendar')
                    ->suffixIconColor('primary'),

                Select::make('shift')
                    ->label('Shift')
                    ->options([
                        'pagi'  => 'Shift Pagi',
                        'malam' => 'Shift Malam',
                    ])
                    ->visible(fn() => $this->punyaKolomShift())
                    ->live()
                    ->afterStateUpdated(function () {
                        $this->pilihMesinPertama();
                        $this->loadData();
                    }),

                Select::make('id_mesin')
                    ->label('Mesin Rotary')
                    ->options(fn() => $this->opsiMesin())
                    ->placeholder('Tidak ada produksi')
                    ->live()
                    ->afterStateUpdated(fn() => $this->loadData()),
            ])
            ->columns([
                'default' => 1,
                'sm'      => 2,
            ])
            ->statePath('data');
    }

    protected function kolomTanggal(): string
    {
        return DbSchema::hasColumn('produksi_rotaries', 'tgl_produksi')
            ? 'tgl_produksi'
            : 'tanggal_produksi';
    }

    protected function punyaKolomShift(): bool
    {
        return DbSchema::hasColumn('produksi_rotaries', 'shift');
    }

    protected function queryProduksi()
    {
        $tanggal = Carbon::parse($this->data['tanggal_produksi'] ?? now())->toDateString();
        $kolom   = $this->kolomTanggal();

        $query = ProduksiRotary::query()->whereDate($kolom, $tanggal);

        if ($this->punyaKolomShift()) {
            $query->where('shift', $this->data['shift'] ?? 'pagi');
        }

        return $query;
    }

    protected function opsiMesin(): array
    {
        $idMesinList = $this->queryProduksi()
            ->whereNotNull('id_mesin')
            ->pluck('id_mesin')
            ->unique();

        if ($idMesinList->isEmpty()) {
            return [];
        }

        return Mesin::whereIn('id', $idMesinList)
            ->pluck('nama_mesin', 'id')
            ->all();
    }

    protected function pilihMesinPertama(): void
    {
        $opsi = $this->opsiMesin();
        $this->data['id_mesin'] = !empty($opsi) ? array_key_first($opsi) : null;
    }

    public function loadData(): void
    {
        $idMesin = $this->data['id_mesin'] ?? null;
        $tanggal = Carbon::parse($this->data['tanggal_produksi'] ?? now())->toDateString();
        $shift   = $this->data['shift'] ?? 'pagi';
        $kolom   = $this->kolomTanggal();

        $query = ProduksiRotary::query()
            ->whereDate($kolom, $tanggal)
            ->where('id_mesin', $idMesin);

        if ($this->punyaKolomShift()) {
            $query->where('shift', $shift);
        }

        $record = $query->first();

        if (!$record) {
            $this->summary = ['ada_data' => false];
            return;
        }

        $produksiId = $record->id;

        // ===== 1. Total Hasil (Lembar) =====
        $totalAll = DetailHasilPaletRotary::where('id_produksi', $produksiId)
            ->sum(DB::raw('CAST(total_lembar AS UNSIGNED)'));

        // ===== 2. Total Pegawai (Unik) =====
        $idPegawai = DB::table('pegawai_rotaries')
            ->where('id_produksi', $produksiId)
            ->whereNotNull('id_pegawai')
            ->pluck('id_pegawai')
            ->unique()
            ->values();

        $namaPegawai = Pegawai::whereIn('id', $idPegawai)
            ->get()
            ->map(fn($p) => $p->nama_pegawai ?? $p->nama ?? $p->nama_lengkap ?? 'ID ' . $p->id)
            ->values()
            ->all();

        // ===== 3. Rincian Utama (Jenis Kayu, Ukuran, KW) =====
        $rows = DetailHasilPaletRotary::query()
            ->where('detail_hasil_palet_rotaries.id_produksi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasil_palet_rotaries.id_ukuran')
            ->join('penggunaan_lahan_rotaries', 'penggunaan_lahan_rotaries.id', '=', 'detail_hasil_palet_rotaries.id_penggunaan_lahan')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'penggunaan_lahan_rotaries.id_jenis_kayu')
            ->selectRaw('
                jenis_kayus.nama_kayu AS jenis_kayu,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                detail_hasil_palet_rotaries.kw AS kw,
                SUM(CAST(detail_hasil_palet_rotaries.total_lembar AS UNSIGNED)) AS total
            ')
            ->groupBy('jenis_kayus.nama_kayu', 'ukuran', 'detail_hasil_palet_rotaries.kw')
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('ukuran')
            ->get();

        // ===== 4. Rincian Per Lahan & Ukuran =====
        $rowsLahan = DetailHasilPaletRotary::query()
            ->where('detail_hasil_palet_rotaries.id_produksi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasil_palet_rotaries.id_ukuran')
            ->join('penggunaan_lahan_rotaries', 'penggunaan_lahan_rotaries.id', '=', 'detail_hasil_palet_rotaries.id_penggunaan_lahan')
            ->join('lahans', 'lahans.id', '=', 'penggunaan_lahan_rotaries.id_lahan')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'penggunaan_lahan_rotaries.id_jenis_kayu')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                CONCAT(COALESCE(lahans.kode_lahan, ""), " - ", COALESCE(lahans.nama_lahan, "")) as nama_lahan,
                jenis_kayus.nama_kayu as jenis_kayu,
                SUM(CAST(detail_hasil_palet_rotaries.total_lembar AS UNSIGNED)) AS total
            ')
            ->groupBy('lahans.kode_lahan', 'lahans.nama_lahan', 'jenis_kayus.nama_kayu', 'ukurans.panjang', 'ukurans.lebar', 'ukurans.tebal')
            ->orderBy('nama_lahan')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'ada_data'     => true,
            'totalAll'     => (int) $totalAll,
            'totalPegawai' => (int) $idPegawai->count(),
            'rows'         => $rows,
            'rowsLahan'    => $rowsLahan,
            'namaPegawai'  => $namaPegawai,
        ];
    }
}
