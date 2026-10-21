<?php

namespace App\Filament\Resources\ProduksiHotPresses\Widgets;

use App\Models\Pegawai;
use App\Models\ProduksiHp;
use App\Services\ProductionAccessService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DbSchema;

class RekapProduksiHotPress extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-hot-presses.widgets.rekap-produksi-hot-press';
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
        $tabel = (new ProduksiHp)->getTable();

        return DbSchema::hasColumn($tabel, 'tanggal_produksi')
            ? 'tanggal_produksi'
            : 'tanggal';
    }

    /** Hasil per jenis kayu + ukuran + kw untuk satu tabel hasil (platform / triplek). */
    protected function rincian(string $tabel, Collection $ids): Collection
    {
        return DB::table($tabel)
            ->whereIn("{$tabel}.id_produksi_hp", $ids)
            ->join('barang_setengah_jadi_hp as bsj', 'bsj.id', '=', "{$tabel}.id_barang_setengah_jadi")
            ->join('ukurans', 'ukurans.id', '=', 'bsj.id_ukuran')
            ->join('jenis_barang', 'jenis_barang.id', '=', 'bsj.id_jenis_barang')
            ->join('grades', 'grades.id', '=', 'bsj.id_grade')
            ->selectRaw('
                jenis_barang.nama_jenis_barang AS jenis_kayu,
                bsj.id_ukuran AS id_ukuran,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                grades.nama_grade AS kw,
                SUM(' . $tabel . '.isi) AS total
            ')
            ->groupBy('jenis_barang.nama_jenis_barang', 'bsj.id_ukuran', 'ukuran', 'grades.nama_grade')
            ->get();
    }

    public function loadData(): void
    {
        $tanggal = Carbon::parse($this->data['tanggal'] ?? now())->toDateString();
        $kolom   = $this->kolomTanggal();

        // Semua produksi di tanggal itu (tetap mengikuti batas akses 7 hari)
        $ids = ProductionAccessService::applyDateRestriction(
            ProduksiHp::query(),
            $kolom,
            7
        )
            ->whereDate($kolom, $tanggal)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->summary = ['ada_data' => false];
            return;
        }

        // ===== Pekerja (lewat relasi detailPegawaiHp milik ProduksiHp) =====
        $idPegawai = ProduksiHp::whereIn('id', $ids)
            ->with('detailPegawaiHp')
            ->get()
            ->flatMap(fn($p) => $p->detailPegawaiHp->pluck('id_pegawai'))
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

        // ===== Hasil platform & triplek =====
        $platform = $this->rincian('platform_hasil_hp', $ids);
        $triplek  = $this->rincian('triplek_hasil_hp', $ids);

        $totalPlatform = (int) $platform->sum('total');
        $totalTriplek  = (int) $triplek->sum('total');

        // Gabung per jenis kayu + ukuran + kw, dengan kolom platform dan triplek terpisah
        $gabung = [];
        foreach (['platform' => $platform, 'triplek' => $triplek] as $sumber => $koleksi) {
            foreach ($koleksi as $item) {
                $key = $item->jenis_kayu . '|' . $item->ukuran . '|' . $item->kw;

                $gabung[$key] ??= (object) [
                    'jenis_kayu' => $item->jenis_kayu,
                    'ukuran'     => $item->ukuran,
                    'kw'         => $item->kw,
                    'platform'   => 0,
                    'triplek'    => 0,
                ];

                $gabung[$key]->{$sumber} += (int) $item->total;
            }
        }

        $rows = collect($gabung)
            ->sortBy([['jenis_kayu', 'asc'], ['ukuran', 'asc'], ['kw', 'asc']])
            ->values();

        $this->summary = [
            'ada_data'      => true,
            'totalPlatform' => $totalPlatform,
            'totalTriplek'  => $totalTriplek,
            'totalPegawai'  => (int) $idPegawai->count(),
            'rows'          => $rows,
            'namaPegawai'   => $namaPegawai,
        ];
    }
}
