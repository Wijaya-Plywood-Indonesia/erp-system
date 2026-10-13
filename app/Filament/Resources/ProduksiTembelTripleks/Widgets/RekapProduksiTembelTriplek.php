<?php

namespace App\Filament\Resources\ProduksiTembelTripleks\Widgets; // sesuaikan dengan folder resource-mu

use App\Models\HasilTembeltriplek;
use App\Models\PegawaiTembeltriplek;
use App\Models\ProduksiTembeltriplek;
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

class RekapProduksiTembelTriplek extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-tembel-tripleks.widgets.rekap-produksi-tembel-triplek';
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

    /** Pilih kolom yang benar-benar ada di tabel, dari daftar kandidat. */
    protected function kolomAda(string $tabel, array $kandidat): ?string
    {
        foreach ($kandidat as $kolom) {
            if (DbSchema::hasColumn($tabel, $kolom)) {
                return $kolom;
            }
        }

        return null;
    }

    public function loadData(): void
    {
        $tanggal = Carbon::parse($this->data['tanggal'] ?? now())->toDateString();

        // Semua produksi di tanggal itu (tetap mengikuti batas akses 7 hari)
        $ids = ProductionAccessService::applyDateRestriction(
            ProduksiTembeltriplek::query(),
            'tanggal',
            7
        )
            ->whereDate('tanggal', $tanggal)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->summary = ['ada_data' => false];
            return;
        }

        // ===== Pekerja (relasi sudah pasti: PegawaiTembeltriplek::pegawai) =====
        $pegawaiRows = PegawaiTembeltriplek::query()
            ->whereIn('id_produksi_tembel_triplek', $ids)
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

        // ===== Hasil (kolom dideteksi otomatis karena model belum dikirim) =====
        $tabelHasil   = (new HasilTembeltriplek)->getTable();
        $kolomJumlah  = $this->kolomAda($tabelHasil, ['hasil', 'isi', 'kuantitas', 'jumlah']);
        $kolomBarang  = $this->kolomAda($tabelHasil, ['id_barang_setengah_jadi_hp', 'id_barang_setengah_jadi']);

        $totalAll = 0;
        $rows     = collect();

        if ($kolomJumlah) {
            $totalAll = (int) HasilTembeltriplek::whereIn('id_produksi_tembel_triplek', $ids)
                ->sum(DB::raw("CAST({$kolomJumlah} AS UNSIGNED)"));

            if ($kolomBarang) {
                $rows = $this->ambilRincian($tabelHasil, $kolomJumlah, $kolomBarang, $ids);
            }
        }

        $this->summary = [
            'ada_data'     => true,
            'totalAll'     => $totalAll,
            'totalPegawai' => $pegawaiRows->count(),
            'rows'         => $rows,
            'namaPegawai'  => $namaPegawai,
            'kolomJumlah'  => $kolomJumlah, // dipakai Blade untuk pesan bantuan
        ];
    }

    /** Rincian per jenis kayu + ukuran + kw (pola join sama seperti widget lain). */
    protected function ambilRincian(string $tabel, string $kolomJumlah, string $kolomBarang, Collection $ids): Collection
    {
        return DB::table($tabel)
            ->whereIn("{$tabel}.id_produksi_tembel_triplek", $ids)
            ->join('barang_setengah_jadi_hp as bsj', 'bsj.id', '=', "{$tabel}.{$kolomBarang}")
            ->join('ukurans', 'ukurans.id', '=', 'bsj.id_ukuran')
            ->join('jenis_barang', 'jenis_barang.id', '=', 'bsj.id_jenis_barang')
            ->join('grades', 'grades.id', '=', 'bsj.id_grade')
            ->join('kategori_barang', 'kategori_barang.id', '=', 'grades.id_kategori_barang')
            ->selectRaw("
                jenis_barang.nama_jenis_barang AS jenis_kayu,
                CONCAT(
                    TRIM(TRAILING \".00\" FROM CAST(ukurans.panjang AS CHAR)), \" x \",
                    TRIM(TRAILING \".00\" FROM CAST(ukurans.lebar AS CHAR)), \" x \",
                    TRIM(TRAILING \".\" FROM TRIM(TRAILING \"0\" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                CONCAT(kategori_barang.nama_kategori, \" \", grades.nama_grade) AS kw,
                SUM(CAST({$tabel}.{$kolomJumlah} AS UNSIGNED)) AS total
            ")
            ->groupBy('jenis_barang.nama_jenis_barang', 'ukuran', 'kategori_barang.nama_kategori', 'grades.nama_grade')
            ->orderBy('jenis_barang.nama_jenis_barang')
            ->orderBy('ukuran')
            ->get();
    }
}
