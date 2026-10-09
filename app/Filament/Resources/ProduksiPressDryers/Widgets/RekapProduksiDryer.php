<?php

namespace App\Filament\Resources\ProduksiPressDryers\Widgets;

use App\Models\DetailHasil;
use App\Models\DetailPegawai;
use App\Models\Pegawai;
use App\Models\ProduksiPressDryer;
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

class RekapProduksiDryer extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-press-dryers.widgets.rekap-produksi-dryer';
    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];
    public array $summary = [];

    public function mount(): void
    {
        $this->form->fill([
            'tanggal_produksi' => now()->toDateString(),
            'shift'            => 'pagi',
        ]);

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
                    ->afterStateUpdated(fn() => $this->loadData())
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
                    ->afterStateUpdated(fn() => $this->loadData()),
            ])
            ->columns([
                'default' => 1,
                'sm'      => 2, // Karena cuma 2 field, pas jika dibagi 2 kolom
            ])
            ->statePath('data');
    }

    protected function kolomTanggal(): string
    {
        return DbSchema::hasColumn('produksi_press_dryers', 'tanggal_produksi')
            ? 'tanggal_produksi'
            : 'tanggal';
    }

    protected function punyaKolomShift(): bool
    {
        return DbSchema::hasColumn('produksi_press_dryers', 'shift');
    }

    public function loadData(): void
    {
        $tanggal = Carbon::parse($this->data['tanggal_produksi'] ?? now())->toDateString();
        $shift   = $this->data['shift'] ?? 'pagi';
        $kolom   = $this->kolomTanggal();

        // Ambil semua ID produksi press dryer yang sesuai dengan tanggal & shift terpilih
        $query = ProduksiPressDryer::query()->whereDate($kolom, $tanggal);

        if ($this->punyaKolomShift()) {
            $query->where('shift', $shift);
        }

        $produksiIds = $query->pluck('id');

        if ($produksiIds->isEmpty()) {
            $this->summary = ['ada_data' => false];
            return;
        }

        // ===== 1. Total Hasil (Lembar) =====
        $totalAll = DetailHasil::whereIn('id_produksi_dryer', $produksiIds)
            ->sum(DB::raw('CAST(isi AS UNSIGNED)'));

        // ===== 2. Total Kubikasi (P x L x T x Qty / 10.000.000) =====
        $details = DetailHasil::query()
            ->whereIn('detail_hasils.id_produksi_dryer', $produksiIds)
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasils.id_ukuran')
            ->select([
                'ukurans.panjang',
                'ukurans.lebar',
                'ukurans.tebal',
                'detail_hasils.isi',
            ])
            ->get();

        $totalKubikasi = 0;
        foreach ($details as $item) {
            $p = (float) $item->panjang;
            $l = (float) $item->lebar;
            $t = (float) $item->tebal;
            $qty = (float) $item->isi;

            $totalKubikasi += ($p * $l * $t * $qty) / 10000000;
        }

        // ===== 3. Pekerja (Unik) =====
        $idPegawai = DetailPegawai::whereIn('id_produksi_dryer', $produksiIds)
            ->whereNotNull('id_pegawai')
            ->pluck('id_pegawai')
            ->unique()
            ->values();

        $namaPegawai = Pegawai::whereIn('id', $idPegawai)
            ->get()
            ->map(fn($p) => $p->nama_pegawai ?? $p->nama ?? $p->nama_lengkap ?? 'ID ' . $p->id)
            ->values()
            ->all();

        // ===== 4. Rincian Jenis Kayu + Ukuran + KW =====
        $rows = DetailHasil::query()
            ->whereIn('detail_hasils.id_produksi_dryer', $produksiIds)
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasils.id_ukuran')
            ->leftJoin('jenis_kayus', 'jenis_kayus.id', '=', 'detail_hasils.id_jenis_kayu')
            ->selectRaw('
                COALESCE(jenis_kayus.nama_kayu, "Tanpa Jenis Kayu") AS jenis_kayu,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                detail_hasils.kw AS kw,
                SUM(CAST(detail_hasils.isi AS UNSIGNED)) AS total_lembar,
                SUM((ukurans.panjang * ukurans.lebar * ukurans.tebal * detail_hasils.isi) / 10000000) AS total_kubik
            ')
            ->groupBy('jenis_kayu', 'ukuran', 'detail_hasils.kw')
            ->orderBy('jenis_kayu')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'ada_data'      => true,
            'totalAll'      => (int) $totalAll,
            'totalKubikasi' => $totalKubikasi,
            'totalPegawai'  => (int) $idPegawai->count(),
            'rows'          => $rows,
            'namaPegawai'   => $namaPegawai,
        ];
    }
}
