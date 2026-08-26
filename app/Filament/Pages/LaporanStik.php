<?php

namespace App\Filament\Pages;

use App\DataTransferObjects\PekerjaKerjaInput;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\DatePicker;

use App\Models\ProduksiStik;
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
    public $tanggal = null;
    public bool $isLoading = false;

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->form->fill(['tanggal' => $this->tanggal]);
        $this->loadAllData();
    }

    private function getProduksiStikData(string $tanggal)
    {
        return ProduksiStik::with([
            'detailPegawaiStik.pegawai:id,kode_pegawai,nama_pegawai',
            'detailHasilStik.ukuran',
            'detailHasilStik.jenisKayu',
        ])
            ->whereDate('tanggal_produksi', $tanggal)
            ->get();
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

        $produksiList = $this->getProduksiStikData($tanggal);

        $this->dataProduksi = [];

        foreach ($produksiList as $produksi) {
            $tanggalFormat = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
            $namaMesin     = 'MESIN STIK';

            $daftarHasil = [];
            $totalLembar = 0;

            // Grouping data untuk Sheet 2 (Matriks Rekap KW)
            $groupedMatrix = [];

            foreach ($produksi->detailHasilStik ?? [] as $index => $hasil) {
                $ukuran = $hasil->ukuran;
                $p = $ukuran?->panjang ?? '';
                $l = $ukuran?->lebar ?? '';
                $t = $ukuran?->tebal ?? '';

                $formatUkuran = ($p && $l && $t !== '') ? "{$p}mm x {$l}mm x {$t}mm" : '-';
                $lembar = (int) ($hasil->total_lembar ?? 0);
                $totalLembar += $lembar;

                // 1. Data untuk Sheet 1 (Rincian Palet)
                $daftarHasil[] = [
                    'no_palet'     => $hasil->no_palet ?? ('ST-' . ($index + 1)),
                    'jenis_kayu'   => $hasil->jenisKayu?->nama_jenis_kayu ?? $hasil->jenis_kayu ?? 'Sengon',
                    'ukuran'       => $formatUkuran,
                    'kualitas'     => $hasil->kualitas ?? ($hasil->kw ? 'KW ' . $hasil->kw : '-'),
                    'total_lembar' => $lembar,
                ];

                // 2. Logic Grouping untuk Sheet 2 (Berdasarkan Ukuran p, l, t & Jenis Kayu)
                $jenisKayuNama = $hasil->jenisKayu?->nama_jenis_kayu ?? $hasil->jenis_kayu ?? 'Sengon';
                $jenisSingkat  = strtolower(substr($jenisKayuNama, 0, 1));

                $groupKey = "{$p}_{$l}_{$t}_{$jenisSingkat}";

                if (!isset($groupedMatrix[$groupKey])) {
                    $groupedMatrix[$groupKey] = [
                        'panjang'    => $p ?: '-',
                        'lebar'      => $l ?: '-',
                        'tebal'      => $t ?: '-',
                        'jenis_kayu' => $jenisSingkat,
                        'kw1'        => 0,
                        'kw2'        => 0,
                        'kw3'        => 0,
                        'kw4'        => 0,
                        'af'         => 0,
                        'total'      => 0,
                    ];
                }

                // Identifikasi Kualitas KW
                $kwRaw = strtolower((string) ($hasil->kualitas ?? $hasil->kw ?? ''));

                if (str_contains($kwRaw, 'af') || str_contains($kwRaw, 'afval')) {
                    $groupedMatrix[$groupKey]['af'] += $lembar;
                } elseif (str_contains($kwRaw, '4') || str_contains($kwRaw, 'kw4')) {
                    $groupedMatrix[$groupKey]['kw4'] += $lembar;
                } elseif (str_contains($kwRaw, '3') || str_contains($kwRaw, 'kw3')) {
                    $groupedMatrix[$groupKey]['kw3'] += $lembar;
                } elseif (str_contains($kwRaw, '2') || str_contains($kwRaw, 'kw2')) {
                    $groupedMatrix[$groupKey]['kw2'] += $lembar;
                } elseif (str_contains($kwRaw, '1') || str_contains($kwRaw, 'kw1')) {
                    $groupedMatrix[$groupKey]['kw1'] += $lembar;
                } else {
                    $groupedMatrix[$groupKey]['kw1'] += $lembar;
                }

                $groupedMatrix[$groupKey]['total'] += $lembar;
            }

            $detailHasilMatrix = array_values($groupedMatrix);
            $hasilPalet = count($daftarHasil);

            // Pekerja Input
            $pekerjaInput = collect($produksi->detailPegawaiStik ?? [])->map(function ($detail) {
                $masuk  = $detail->masuk  ? Carbon::parse($detail->masuk)  : null;
                $pulang = $detail->pulang ? Carbon::parse($detail->pulang) : null;
                $menitIstirahat = $detail->menit_istirahat ?? 60;
                $menit = ($masuk && $pulang) ? max(0, abs($pulang->diffInMinutes($masuk)) - $menitIstirahat) : (9 * 60);

                return new PekerjaKerjaInput(
                    idPegawai: $detail->pegawai?->kode_pegawai ?? '-',
                    menitKerja: $menit,
                );
            })->all();

            $result = app(\App\Actions\HitungPotonganProduksiAction::class)->execute(
                mesin: \App\Enums\Mesin::Stik,
                strategi: \App\Enums\StrategiPembagian::Kolektif,
                pekerja: $pekerjaInput,
                hasilAktual: $hasilPalet,
            );

            $targetPalet        = $result?->targetAdjusted ?? 0;
            $selisihPalet       = $hasilPalet - $targetPalet;
            $potonganPerPegawai = $result?->potonganPerPegawai ?? [];

            $pekerja = [];
            foreach ($produksi->detailPegawaiStik ?? [] as $detail) {
                $idPegawai = $detail->pegawai?->kode_pegawai ?? '-';
                $potonganPegawaiIni = $potonganPerPegawai[$idPegawai] ?? 0;
                $potonganDibulatkan = $potonganPegawaiIni > 0
                    ? $this->roundToNearestHundred($potonganPegawaiIni)
                    : 0;

                $pekerja[] = [
                    'id'         => $idPegawai,
                    'nama'       => $detail->pegawai?->nama_pegawai ?? '-',
                    'jam_masuk'  => $detail->masuk ? Carbon::parse($detail->masuk)->format('H:i') : '-',
                    'jam_pulang' => $detail->pulang ? Carbon::parse($detail->pulang)->format('H:i') : '-',
                    'ijin'       => $detail->ijin ?? '-',
                    'pot_target' => $potonganDibulatkan > 0
                        ? 'Rp ' . number_format($potonganDibulatkan, 0, ',', '.')
                        : 'Rp 0',
                    'keterangan' => $detail->ket ?? '-',
                ];
            }

            $totalKendalaMenit      = $produksi->total_kendala_menit ?? 0;
            $totalDowntimeFormatted = $totalKendalaMenit > 0 ? "{$totalKendalaMenit} menit" : '-';

            $this->dataProduksi[] = [
                'id'                       => $produksi->id,
                'mesin'                    => $namaMesin,
                'tanggal'                  => $tanggalFormat,
                'daftar_hasil'             => $daftarHasil,       // Sheet 1
                'detail_hasil'             => $detailHasilMatrix, // Sheet 2
                'pekerja'                  => $pekerja,
                'hasil_palet'              => $hasilPalet,
                'total_lembar'             => $totalLembar,
                'target_palet'             => round($targetPalet),
                'selisih_palet'            => $selisihPalet,
                'jam_kerja'                => 9,
                'total_kendala_menit'      => $totalKendalaMenit,
                'total_downtime_formatted' => $totalDowntimeFormatted,
                'kendala'                  => $produksi->kendala ?? '-', // Dikirim ke Sheet 2
            ];
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
