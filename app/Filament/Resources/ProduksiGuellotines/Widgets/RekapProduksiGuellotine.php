<?php

namespace App\Filament\Resources\ProduksiGuellotines\Widgets;

use App\Models\hasil_guellotine;
use App\Models\Pegawai;
use App\Models\pegawai_guellotine;
use App\Models\produksi_guellotine;
use App\Services\ProductionAccessService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class RekapProduksiGuellotine extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-guellotines.widgets.rekap-produksi-guellotine';
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
        // Selalu "Y-m-d" tanpa jam
        $tanggal = Carbon::parse($this->data['tanggal'] ?? now())->toDateString();

        // Semua produksi di tanggal itu (tetap mengikuti batas akses 7 hari)
        $ids = ProductionAccessService::applyDateRestriction(
            produksi_guellotine::query(),
            'tanggal_produksi',
            7
        )
            ->whereDate('tanggal_produksi', $tanggal)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->summary = ['ada_data' => false];
            return;
        }

        // ===== Total hasil =====
        $totalAll = hasil_guellotine::whereIn('id_produksi_guellotine', $ids)
            ->sum('jumlah');

        // ===== Pekerja (relasi sudah pasti: pegawai_guellotine::pegawai) =====
        $pegawaiRows = pegawai_guellotine::query()
            ->whereIn('id_produksi_guellotine', $ids)
            ->whereNotNull('id_pegawai')
            ->with('pegawai')
            ->get()
            ->unique('id_pegawai');

        $namaPegawai = $pegawaiRows
            ->map(fn($p) => $p->pegawai?->nama_pegawai
                ?? $p->pegawai?->nama
                ?? $p->pegawai?->nama_lengkap
                ?? 'ID ' . $p->id_pegawai)
            ->values()
            ->all();

        // ===== Rincian jenis kayu + ukuran (join sama dengan summary widget) =====
        $rows = hasil_guellotine::query()
            ->whereIn('hasil_guellotine.id_produksi_guellotine', $ids)
            ->leftJoin('ukurans', 'ukurans.id', '=', 'hasil_guellotine.id_ukuran')
            ->leftJoin('jenis_kayus', 'jenis_kayus.id', '=', 'hasil_guellotine.id_jenis_kayu')
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
                SUM(CAST(hasil_guellotine.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('jenis_kayus.nama_kayu', 'ukuran')
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('ukuran')
            ->toBase()
            ->get();

        $this->summary = [
            'ada_data'     => true,
            'totalAll'     => (int) $totalAll,
            'totalPegawai' => $pegawaiRows->count(),
            'rows'         => $rows,
            'namaPegawai'  => $namaPegawai,
        ];
    }
}
