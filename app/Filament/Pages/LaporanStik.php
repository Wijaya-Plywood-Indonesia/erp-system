<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\DatePicker;

use App\Models\ProduksiStik;
use App\Models\Target;

use Filament\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LaporanProduksiStikExport;
use Carbon\Carbon;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanStik extends Page
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-stik';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Stik';
    protected static ?int $navigationSort = 5;
    protected static bool $shouldRegisterNavigation = false;

    public $dataProduksi = [];
    public $dataStik = [];
    public $tanggal  = null;
    public $summary  = [];
    public bool $isLoading = false;

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->form->fill(['tanggal' => $this->tanggal]);
        $this->loadAllData();
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('tanggal')
                ->label('Pilih Tanggal')
                ->format('Y-m-d')
                ->displayFormat('d/m/Y')
                ->live()
                ->required()
                ->maxDate(now())
                ->default(now())
                ->native(false)
                ->closeOnDateSelection()
                ->suffixIconColor('primary')
                ->afterStateUpdated(function ($state) {
                    $this->tanggal = $state;
                    $this->loadAllData();
                }),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportToExcel'),
        ];
    }

    protected function roundToNearestHundred(float $number): int
    {
        $thousands = floor($number / 1000);
        $base      = $thousands * 1000;
        $remainder = $number - $base;

        if ($remainder < 300) {
            return (int) $base;
        } elseif ($remainder < 800) {
            return (int) ($base + 500);
        } else {
            return (int) ($base + 1000);
        }
    }

    public function loadAllData(): void
    {
        $this->isLoading = true;
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        $produksiList = ProduksiStik::with([
            'detailPegawaiStik.pegawai',
            'detailHasilStik.ukuran',
            'detailHasilStik.jenisKayu',
        ])
            ->whereDate('tanggal_produksi', $tanggal)
            ->get();

        $this->dataProduksi = [];
        $this->dataStik = [];

        foreach ($produksiList as $produksi) {
            $tanggalFormat = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
            $namaMesin     = 'MESIN STIK';

            // 1. Grouping detail hasil berdasarkan Ukuran (id_ukuran)
            $groupedByUkuran = collect($produksi->detailHasilStik ?? [])->groupBy(function ($dh) {
                return $dh->id_ukuran ?? 0;
            });

            // 2. Loop per Ukuran
            foreach ($groupedByUkuran as $idUkuranKey => $hasilList) {
                $firstHasil = $hasilList->first();
                $ukuran     = $firstHasil?->ukuran;
                $idUkuran   = $firstHasil?->id_ukuran;
                $idJenisKayu = $firstHasil?->id_jenis_kayu;

                // Format kode ukuran (contoh: 2441220.5)
                $p = $ukuran?->panjang ?? '';
                $l = $ukuran?->lebar ?? '';
                $t = $ukuran?->tebal ?? '';
                $pureKodeUkuran = ($p && $l && $t !== '') ? "{$p}{$l}{$t}" : 'STIK';

                // Total hasil khusus ukuran ini
                $hasilUkuran = (int) $hasilList->sum('total_lembar');

                // 3. Query Target Harian Master dari Tabel Targets
                $targetItem = Target::where('id_mesin', 8)
                    ->when($idUkuran, fn($q) => $q->where('id_ukuran', $idUkuran))
                    ->when($idJenisKayu, fn($q) => $q->where('id_jenis_kayu', $idJenisKayu))
                    ->orderByDesc('id')
                    ->first();

                if (!$targetItem && $pureKodeUkuran !== 'STIK') {
                    $targetItem = Target::where('id_mesin', 8)
                        ->where('kode_ukuran', $pureKodeUkuran)
                        ->orderByDesc('id')
                        ->first();
                }

                if ($targetItem) {
                    $targetNormal  = (float) ($targetItem->target ?? 0);   // Membaca: 1.600
                    $potonganTarif = (float) ($targetItem->potongan ?? 0); // Membaca: 143.75
                    $stdJam        = (float) ($targetItem->jam ?: 9);      // Membaca: 9.0
                } else {
                    $targetNormal  = 0;
                    $potonganTarif = 0;
                    $stdJam        = 9.0;
                }

                // 4. Hitung Selisih: (Hasil - Target)
                $selisih = $hasilUkuran - $targetNormal;

                // 5. Hitung Potongan per Orang
                $jumlahPekerja    = $produksi->detailPegawaiStik?->count() ?? 0;
                $potonganPerOrang = 0;

                if ($selisih < 0 && $jumlahPekerja > 0 && $potonganTarif > 0) {
                    $kurangTarget     = abs($selisih);
                    $potonganPerOrang = ($kurangTarget * $potonganTarif) / $jumlahPekerja;
                }

                // 6. Data Pekerja
                $pekerja = [];
                foreach ($produksi->detailPegawaiStik ?? [] as $detail) {
                    $pekerja[] = [
                        'id'         => $detail->pegawai?->kode_pegawai ?? '-',
                        'nama'       => $detail->pegawai?->nama_pegawai ?? '-',
                        'jam_masuk'  => $detail->masuk  ? Carbon::parse($detail->masuk)->format('H:i')  : '-',
                        'jam_pulang' => $detail->pulang ? Carbon::parse($detail->pulang)->format('H:i') : '-',
                        'ijin'       => $detail->ijin   ?? '-',
                        'pot_target' => $potonganPerOrang > 0
                            ? number_format($this->roundToNearestHundred($potonganPerOrang), 0, '', '.')
                            : 0,
                        'keterangan' => $detail->ket ?? '-',
                    ];
                }

                // 7. Kendala
                $daftarKendala = [];
                if (!empty($produksi->kendala) && $produksi->kendala !== 'Tidak ada kendala.') {
                    $daftarKendala[] = [
                        'kendala'      => $produksi->kendala,
                        'durasi_menit' => $produksi->total_kendala_menit ?? null,
                        'jam_mulai'    => null,
                        'jam_selesai'  => null,
                        'keterangan'   => '-',
                    ];
                }

                $totalKendalaMenit      = $produksi->total_kendala_menit ?? 0;
                $totalDowntimeFormatted = $totalKendalaMenit > 0 ? "{$totalKendalaMenit} menit" : '-';

                $itemData = [
                    'group_key'                => $produksi->id . '_' . $idUkuranKey,
                    'mesin'                    => $namaMesin,
                    'ukuran'                   => $pureKodeUkuran, // Contoh: '2441220.5'
                    'tanggal'                  => $tanggalFormat,
                    'pekerja'                  => $pekerja,
                    'hasil'                    => $hasilUkuran,
                    'target'                   => $targetNormal,   // Menampilkan Target Master (misal 1.600)
                    'target_normal'            => $targetNormal,
                    'selisih'                  => $selisih,
                    'jam_kerja'                => $stdJam,         // Menampilkan Jam Produksi Master (misal 9.0 jam)
                    'total_kendala_menit'      => $totalKendalaMenit,
                    'total_downtime_formatted' => $totalDowntimeFormatted,
                    'kendala'                  => $produksi->kendala ?? '-',
                    'daftar_kendala'           => $daftarKendala,
                ];

                $this->dataProduksi[] = $itemData;
                $this->dataStik[]     = $itemData;
            }
        }

        $this->isLoading = false;
    }

    public function exportToExcel()
    {
        if (empty($this->dataProduksi)) return;

        $tanggal  = $this->tanggal ?? now()->format('Y-m-d');
        $filename = 'Laporan-Produksi-Stik-' . Carbon::parse($tanggal)->format('Y-m-d') . '.xlsx';

        return Excel::download(new LaporanProduksiStikExport($this->dataProduksi), $filename);
    }
}
