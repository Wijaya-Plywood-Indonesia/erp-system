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
use Illuminate\Support\Facades\Log;

// --- TAMBAHAN: dependency untuk shadow-mode TargetPotonganService ---
use App\Enums\Mesin;
use App\Actions\HitungPotonganProduksiAction;
use Illuminate\Support\Facades\Log;

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

        // === DIHAPUS: blok manual $targetItem, $targetPalet, $potonganTarif, $stdJam ===
        // Semua itu sekarang tanggung jawab StikTargetResolver di dalam Action, tidak perlu diambil manual di sini.

        foreach ($produksiList as $produksi) {
            $tanggalFormat = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
            $namaMesin     = 'MESIN STIK';

            $daftarHasil = [];
            $totalLembar = 0;

            foreach ($produksi->detailHasilStik ?? [] as $index => $hasil) {
                $ukuran = $hasil->ukuran;
                $p = $ukuran?->panjang ?? '';
                $l = $ukuran?->lebar ?? '';
                $t = $ukuran?->tebal ?? '';

                $formatUkuran = ($p && $l && $t !== '') ? "{$p}mm x {$l}mm x {$t}mm" : '-';
                $lembar = (int) ($hasil->total_lembar ?? 0);
                $totalLembar += $lembar;

                $daftarHasil[] = [
                    'no_palet'     => $hasil->no_palet ?? ('ST-' . ($index + 1)),
                    'jenis_kayu'   => $hasil->jenisKayu?->nama_jenis_kayu ?? $hasil->jenis_kayu ?? 'Sengon',
                    'ukuran'       => $formatUkuran,
                    'kualitas'     => $hasil->kualitas ?? ($hasil->kw ? 'KW ' . $hasil->kw : '-'),
                    'total_lembar' => $lembar,
                ];
            }

            $hasilPalet = count($daftarHasil);

            // === BARU: bentuk daftar durasi kerja per pegawai ===
            $pekerjaInput = collect($produksi->detailPegawaiStik ?? [])->map(function ($detail) {
                $masuk  = $detail->masuk  ? Carbon::parse($detail->masuk)  : null;
                $pulang = $detail->pulang ? Carbon::parse($detail->pulang) : null;
                $menitIstirahat = $detail->menit_istirahat ?? 60; // default 1 jam kalau tidak tercatat
                $menit = ($masuk && $pulang) ? max(0, abs($pulang->diffInMinutes($masuk)) - $menitIstirahat) : (9 * 60);

                return new \App\DataTransferObjects\PekerjaKerjaInput(
                    idPegawai: $detail->pegawai?->kode_pegawai ?? '-',
                    menitKerja: $menit,
                );
            })->all();
            // dd($pekerjaInput);

            // === BARU: panggil Action, gantikan seluruh perhitungan manual selisih & potongan ===
            $result = app(\App\Actions\HitungPotonganProduksiAction::class)->execute(
                mesin: \App\Enums\Mesin::Stik,
                pekerja: $pekerjaInput,
                hasilAktual: $hasilPalet,
            );

            $targetPalet      = $result?->targetAdjusted ?? 0;
            $selisihPalet     = $hasilPalet - $targetPalet;
            $potonganPerOrang = $result?->potonganPerOrang ?? 0;

            $potonganDibulatkan = $potonganPerOrang > 0
                ? $this->roundToNearestHundred($potonganPerOrang)
                : 0;

            $jumlahPekerja = $produksi->detailPegawaiStik?->count() ?? 0;

            $pekerja = [];
            foreach ($produksi->detailPegawaiStik ?? [] as $detail) {
                $pekerja[] = [
                    'id'         => $detail->pegawai?->kode_pegawai ?? '-',
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
                'daftar_hasil'             => $daftarHasil,
                'pekerja'                  => $pekerja,
                'hasil_palet'              => $hasilPalet,
                'total_lembar'             => $totalLembar,
                'target_palet'             => round($targetPalet), // sekarang dinamis, bukan angka master mentah
                'selisih_palet'            => $selisihPalet,
                'jam_kerja'                => 9, // opsional: bisa dihapus dari view kalau tidak lagi representatif
                'total_kendala_menit'      => $totalKendalaMenit,
                'total_downtime_formatted' => $totalDowntimeFormatted,
                'kendala'                  => $produksi->kendala ?? '-',
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
