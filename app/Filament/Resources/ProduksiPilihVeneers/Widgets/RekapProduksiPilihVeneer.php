<?php

namespace App\Filament\Resources\ProduksiPilihVeneers\Widgets;

use App\Models\HasilPilihVeneer;
use App\Models\Pegawai;
use App\Models\PegawaiPilihVeneer;
use App\Models\ProduksiPilihVeneer;
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

class RekapProduksiPilihVeneer extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-pilih-veneers.widgets.rekap-produksi-pilih-veneer';
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
        $tabel = (new ProduksiPilihVeneer)->getTable();

        return DbSchema::hasColumn($tabel, 'tanggal_produksi')
            ? 'tanggal_produksi'
            : 'tanggal';
    }

    /** 130.00 -> "130", 2.50 -> "2.5" */
    protected function angka($nilai): string
    {
        return rtrim(rtrim(number_format((float) $nilai, 2, '.', ''), '0'), '.');
    }

    public function loadData(): void
    {
        $tanggal = Carbon::parse($this->data['tanggal'] ?? now())->toDateString();
        $kolom   = $this->kolomTanggal();

        $ids = ProductionAccessService::applyDateRestriction(
            ProduksiPilihVeneer::query(),
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
        $totalAll = HasilPilihVeneer::whereIn('id_produksi_pilih_veneer', $ids)->sum('jumlah');

        // ===== Pekerja =====
        $idPegawai = PegawaiPilihVeneer::whereIn('id_produksi_pilih_veneer', $ids)
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
        $hasil = HasilPilihVeneer::query()
            ->whereIn('id_produksi_pilih_veneer', $ids)
            ->with('modalPilihVeneer.stokVeneerJadi')
            ->get();

        $ukuranMaster = DB::table('ukurans')->get()->keyBy('id');
        $kayuMaster   = DB::table('jenis_kayus')->pluck('nama_kayu', 'id');

        $rows = $hasil
            ->map(function ($h) use ($ukuranMaster, $kayuMaster) {
                $modal = $h->modalPilihVeneer;
                $stok  = $modal?->stokVeneerJadi;

                // 1) ukuran dari modal, 2) cadangan dari stok veneer jadi
                $u = $modal?->id_ukuran ? $ukuranMaster->get($modal->id_ukuran) : null;

                $panjang = $u?->panjang ?? $stok?->panjang;
                $lebar   = $u?->lebar   ?? $stok?->lebar;
                $tebal   = $u?->tebal   ?? $stok?->tebal;

                $idKayu = $modal?->id_jenis_kayu ?? $stok?->id_jenis_kayu;

                return (object) [
                    'jenis_kayu' => $kayuMaster[$idKayu] ?? '-',
                    'ukuran'     => ($panjang !== null && $lebar !== null && $tebal !== null)
                        ? $this->angka($panjang) . ' x ' . $this->angka($lebar) . ' x ' . $this->angka($tebal)
                        : 'Ukuran tidak terdeteksi',
                    'kw'         => $h->kw,
                    'jumlah'     => (float) $h->jumlah,
                ];
            })
            ->groupBy(fn($r) => $r->jenis_kayu . '|' . $r->ukuran . '|' . $r->kw)
            ->map(fn($g) => (object) [
                'jenis_kayu' => $g->first()->jenis_kayu,
                'ukuran'     => $g->first()->ukuran,
                'kw'         => $g->first()->kw,
                'total'      => (int) $g->sum('jumlah'),
            ])
            ->sortBy(['jenis_kayu', 'ukuran'])
            ->values();

        $this->summary = [
            'ada_data'     => true,
            'totalAll'     => (int) $totalAll,
            'totalPegawai' => (int) $idPegawai->count(),
            'rows'         => $rows,
            'namaPegawai'  => $namaPegawai,
        ];
    }
}
