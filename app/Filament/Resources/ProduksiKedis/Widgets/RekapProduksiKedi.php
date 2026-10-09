<?php

namespace App\Filament\Resources\ProduksiKedis\Widgets;

use App\Models\DetailBongkarKedi;
use App\Models\DetailMasukKedi;
use App\Models\DetailPegawaiKedi;
use App\Models\Mesin;
use App\Models\Pegawai;
use App\Models\ProduksiKedi;
use App\Services\ProductionAccessService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RekapProduksiKedi extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-kedis.widgets.rekap-produksi-kedi';
    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];
    public array $summary = [];

    public function mount(): void
    {
        $this->form->fill([
            'tanggal' => now()->toDateString(),
        ]);

        $this->pilihMesinPertama();
        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal')
                    ->label('Tanggal Realisasi Bongkar')
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

                Select::make('id_mesin')
                    ->label('Mesin')
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
        return 'tanggal_actual_bongkar';
    }

    /** Query dasar berdasarkan tanggal actual bongkar + pembatasan akses */
    protected function queryProduksi()
    {
        $tanggal = Carbon::parse($this->data['tanggal'] ?? now())->toDateString();
        $kolom   = $this->kolomTanggal();

        return ProductionAccessService::applyDateRestriction(
            ProduksiKedi::query(),
            $kolom,
            7
        )->whereDate($kolom, $tanggal);
    }

    /** Daftar mesin yang berproduksi pada tanggal actual bongkar terpilih */
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

    /** Hasil per jenis kayu + ukuran + kw untuk satu tabel (masuk / bongkar). */
    protected function rincian(string $tabel, Collection $ids): Collection
    {
        return DB::table($tabel)
            ->whereIn("{$tabel}.id_produksi_kedi", $ids)
            ->leftJoin('ukurans', 'ukurans.id', '=', "{$tabel}.id_ukuran")
            ->leftJoin('jenis_kayus', 'jenis_kayus.id', '=', "{$tabel}.id_jenis_kayu")
            ->selectRaw('
                COALESCE(jenis_kayus.nama_kayu, "-") AS jenis_kayu,
                COALESCE(
                    CONCAT(
                        TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                        TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                        TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                    ),
                    "Ukuran tidak terdeteksi"
                ) AS ukuran,
                ' . $tabel . '.kw AS kw,
                SUM(CAST(' . $tabel . '.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('jenis_kayus.nama_kayu', 'ukuran', "{$tabel}.kw")
            ->get();
    }

    public function loadData(): void
    {
        $idMesin = $this->data['id_mesin'] ?? null;

        if (!$idMesin) {
            $this->summary = ['ada_data' => false];
            return;
        }

        // Ambil ID produksi kedi yang sesuai tanggal actual bongkar dan mesin terpilih
        $ids = $this->queryProduksi()
            ->where('id_mesin', $idMesin)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->summary = ['ada_data' => false];
            return;
        }

        // ===== Total masuk & bongkar =====
        $totalMasuk = (int) DetailMasukKedi::whereIn('id_produksi_kedi', $ids)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        $totalBongkar = (int) DetailBongkarKedi::whereIn('id_produksi_kedi', $ids)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // ===== Pekerja =====
        $idPegawai = DetailPegawaiKedi::whereIn('id_produksi_kedi', $ids)
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

        // ===== Rincian: gabung masuk + bongkar per jenis kayu + ukuran + kw =====
        $gabung = [];
        foreach (['masuk' => 'detail_masuk_kedi', 'bongkar' => 'detail_bongkar_kedi'] as $sisi => $tabel) {
            foreach ($this->rincian($tabel, $ids) as $item) {
                $key = $item->jenis_kayu . '|' . $item->ukuran . '|' . $item->kw;

                $gabung[$key] ??= (object) [
                    'jenis_kayu' => $item->jenis_kayu,
                    'ukuran'     => $item->ukuran,
                    'kw'         => $item->kw,
                    'masuk'      => 0,
                    'bongkar'    => 0,
                ];

                $gabung[$key]->{$sisi} += (int) $item->total;
            }
        }

        $rows = collect($gabung)
            ->map(function ($r) {
                $r->selisih = $r->masuk - $r->bongkar;

                return $r;
            })
            ->sortBy([['jenis_kayu', 'asc'], ['ukuran', 'asc'], ['kw', 'asc']])
            ->values();

        $this->summary = [
            'ada_data'     => true,
            'totalMasuk'   => $totalMasuk,
            'totalBongkar' => $totalBongkar,
            'selisih'      => $totalMasuk - $totalBongkar,
            'totalPegawai' => (int) $idPegawai->count(),
            'rows'         => $rows,
            'namaPegawai'  => $namaPegawai,
        ];
    }
}
