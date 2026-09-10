<?php

namespace App\Filament\Pages;

use App\Exports\RekapBulananExport;
use App\Models\Pegawai;
use App\Services\RekapBulananService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class RekapBulanan extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Rekap Bulanan';

    protected static ?string $title = 'Rekap Bulanan Pegawai';

    protected string $view = 'filament.pages.rekap-bulanan';

    /**
     * Tabelnya bisa sangat lebar (>60 kolom untuk rentang 1 bulan), jadi
     * halaman ini sengaja pakai lebar layar PENUH, tidak dibatasi
     * max-width default Filament (7xl / ~1280px).
     */
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * State form filter (tanggal_mulai, tanggal_selesai, pegawai_ids).
     * Dipisah dari struktur $uploadData di NewAbsensi.php karena ini page
     * baru & terpisah -- tidak berbagi property dengan page lain.
     *
     * @var array<string, mixed>
     */
    public array $filterData = [];

    /**
     * Hasil rekap terakhir yang ditampilkan di tabel (di-generate lewat
     * tombol "Tampilkan", BUKAN otomatis tiap keystroke) -- supaya query
     * berat (looping getRekap() harian per tanggal) tidak jalan berulang
     * kali saat user masih mengetik/memilih filter.
     */
    public ?Collection $rekap = null;

    /**
     * Daftar tanggal (Y-m-d) dari filter terakhir yang di-generate --
     * dipakai buat bikin kolom tabel di blade, disimpan bareng $rekap
     * supaya keduanya selalu sinkron (satu sumber "submit" yang sama).
     *
     * @var array<int, string>
     */
    public array $periode = [];

    public bool $sudahDitampilkan = false;

    public function mount(): void
    {
        $this->filterForm->fill([
            'tanggal_mulai' => now()->startOfMonth()->format('Y-m-d'),
            'tanggal_selesai' => now()->format('Y-m-d'),
            'pegawai_ids' => [],
        ]);
    }

    /**
     * Filament butuh tahu form mana saja yang aktif di halaman ini.
     */
    protected function getForms(): array
    {
        return [
            'filterForm',
        ];
    }

    public function filterForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal_mulai')
                    ->label('Dari Tanggal')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required(),
                DatePicker::make('tanggal_selesai')
                    ->label('Sampai Tanggal')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required(),
                Select::make('pegawai_ids')
                    ->label('Pegawai (kosongkan = semua pegawai)')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn() => Pegawai::query()
                        ->whereNotNull('nama_pegawai')
                        ->orderBy('nama_pegawai')
                        ->pluck('nama_pegawai', 'id')),
            ])
            ->statePath('filterData');
    }

    /**
     * Dipanggil dari tombol "Tampilkan" di blade. Menjalankan agregasi
     * lewat RekapBulananService lalu menyimpan hasilnya di property
     * $rekap & $periode supaya blade tinggal render, tidak perlu
     * menjalankan ulang query tiap render.
     */
    public function tampilkanRekap(): void
    {
        $data = $this->filterForm->getState();

        $tanggalMulai = $data['tanggal_mulai'];
        $tanggalSelesai = $data['tanggal_selesai'];
        $pegawaiIds = empty($data['pegawai_ids']) ? null : array_map('intval', $data['pegawai_ids']);

        $service = app(RekapBulananService::class);

        $this->periode = $service->buildPeriode($tanggalMulai, $tanggalSelesai);
        $this->rekap = $service->getRekap($tanggalMulai, $tanggalSelesai, $pegawaiIds);
        $this->sudahDitampilkan = true;

        if ($this->rekap->isEmpty()) {
            Notification::make()
                ->title('Tidak ada pegawai yang cocok dengan filter ini')
                ->warning()
                ->send();
        }
    }

    /**
     * Dipanggil dari tombol "Export Excel". Sengaja MEMBANGUN ULANG rekap
     * dari filter form yang sedang aktif (bukan memakai $this->rekap yang
     * sudah di-generate tombol "Tampilkan") -- supaya export selalu
     * konsisten dengan filter TERBARU walau user belum sempat klik
     * "Tampilkan" lagi setelah ganti filter.
     */
    public function exportExcel()
    {
        $data = $this->filterForm->getState();

        $tanggalMulai = $data['tanggal_mulai'];
        $tanggalSelesai = $data['tanggal_selesai'];
        $pegawaiIds = empty($data['pegawai_ids']) ? null : array_map('intval', $data['pegawai_ids']);

        $service = app(RekapBulananService::class);
        $periode = $service->buildPeriode($tanggalMulai, $tanggalSelesai);
        $rekap = $service->getRekap($tanggalMulai, $tanggalSelesai, $pegawaiIds);

        if ($rekap->isEmpty()) {
            Notification::make()
                ->title('Tidak ada data untuk diexport')
                ->body('Cek lagi rentang tanggal / pegawai yang dipilih.')
                ->warning()
                ->send();

            return;
        }

        return Excel::download(
            new RekapBulananExport($rekap, $periode),
            "Rekap-Bulanan-{$tanggalMulai}_sd_{$tanggalSelesai}.xlsx"
        );
    }
}