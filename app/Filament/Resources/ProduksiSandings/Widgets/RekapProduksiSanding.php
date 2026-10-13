<?php

namespace App\Filament\Resources\ProduksiSandings\Widgets;

use App\Models\HasilSanding;
use App\Models\Pegawai;
use App\Models\ProduksiSanding;
use App\Services\ProductionAccessService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema as DbSchema;

class RekapProduksiSanding extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-sandings.widgets.rekap-produksi-sanding';
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
                    ->label('Mesin')
                    ->options(fn() => $this->opsiMesin())
                    ->placeholder('Tidak ada produksi')
                    ->live()
                    ->afterStateUpdated(fn() => $this->loadData()),
            ])
            ->columns([
                'default' => 1, // HP: atas-bawah
                'sm'      => 3, // layar sedang ke atas: sejajar tiga
            ])
            ->statePath('data');
    }

    /** Nama kolom tanggal dipilih otomatis, tidak ditebak. */
    protected function kolomTanggal(): string
    {
        return DbSchema::hasColumn('produksi_sandings', 'tanggal_produksi')
            ? 'tanggal_produksi'
            : 'tanggal';
    }

    protected function punyaKolomShift(): bool
    {
        return DbSchema::hasColumn('produksi_sandings', 'shift');
    }

    /** Query dasar: tanggal + shift (kalau ada), tetap mengikuti batas akses 7 hari. */
    protected function queryProduksi()
    {
        $tanggal = Carbon::parse($this->data['tanggal_produksi'] ?? now())->toDateString();
        $kolom   = $this->kolomTanggal();

        $query = ProductionAccessService::applyDateRestriction(
            ProduksiSanding::query(),
            $kolom,
            7
        )->whereDate($kolom, $tanggal);

        if ($this->punyaKolomShift()) {
            $query->where('shift', $this->data['shift'] ?? 'pagi');
        }

        return $query;
    }

    /** Daftar mesin yang berproduksi pada tanggal + shift terpilih: [id_mesin => nama]. */
    protected function opsiMesin(): array
    {
        return $this->queryProduksi()
            ->with('mesin')
            ->get()
            ->unique('id_mesin')
            ->mapWithKeys(fn($p) => [
                $p->id_mesin => $p->mesin?->nama_mesin ?? 'Mesin #' . $p->id_mesin,
            ])
            ->all();
    }

    /** Dipanggil saat tanggal / shift berubah, supaya mesin selalu valid. */
    protected function pilihMesinPertama(): void
    {
        $this->data['id_mesin'] = array_key_first($this->opsiMesin());
    }

    public function loadData(): void
    {
        $idMesin = $this->data['id_mesin'] ?? null;

        $record = $idMesin
            ? $this->queryProduksi()->where('id_mesin', $idMesin)->first()
            : null;

        if (! $record) {
            $this->summary = ['ada_data' => false];
            return;
        }

        $produksiId = $record->id;

        // ===== Total hasil =====
        $totalAll = HasilSanding::where('id_produksi_sanding', $produksiId)->sum('kuantitas');

        // ===== Pekerja =====
        $idPegawai = $record->pegawaiSandings()
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

        // ===== Rincian jenis kayu + ukuran + kw =====
        $rows = HasilSanding::query()
            ->where('hasil_sandings.id_produksi_sanding', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'hasil_sandings.id_barang_setengah_jadi')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->join('jenis_barang', 'jenis_barang.id', '=', 'barang_setengah_jadi_hp.id_jenis_barang')
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->join('kategori_barang', 'kategori_barang.id', '=', 'grades.id_kategori_barang')
            ->selectRaw('
                jenis_barang.nama_jenis_barang AS jenis_kayu,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                CONCAT(kategori_barang.nama_kategori, " ", grades.nama_grade) AS kw,
                SUM(hasil_sandings.kuantitas) AS total
            ')
            ->groupBy('jenis_barang.nama_jenis_barang', 'ukuran', 'kategori_barang.nama_kategori', 'grades.nama_grade')
            ->orderBy('jenis_barang.nama_jenis_barang')
            ->orderBy('ukuran')
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
