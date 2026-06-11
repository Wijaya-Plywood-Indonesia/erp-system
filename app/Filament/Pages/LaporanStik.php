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

    public $dataStik = [];
    public $tanggal  = null;
    public $summary  = [];
    public bool $isLoading = false;

    public function mount(): void
    {
        $this->form->fill(['tanggal' => $this->tanggal]);
        $this->tanggal = now()->format('Y-m-d');
        $this->loadAllData();
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('tanggal')
                ->label('Pilih Tanggal')
                ->reactive()
                ->format('Y-m-d')
                ->displayFormat('d/m/Y')
                ->live()
                ->required()
                ->maxDate(now())
                ->default(now())
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

        if ($remainder < 300)      return $base;
        elseif ($remainder < 800)  return $base + 500;
        else                       return $base + 1000;
    }

    /**
     * Normalisasi nilai field kw menjadi key 'kw1'/'kw2'/'kw3'/'kw4' / 'af'.
     * Menangani berbagai format: 'kw1', 'KW1', 'KW 1', '1', 1, 'kw-1', 'af', 'AF', dsb.
     */
    protected function normalizeKw(mixed $kw): string
    {
        if ($kw === null || $kw === '') return 'kw1';

        $str = strtolower(trim((string) $kw));

        // Cek apakah af
        if (str_contains($str, 'af')) {
            return 'af';
        }

        // Jika berupa angka murni (int atau string angka): langsung jadikan kw{n}
        if (is_numeric($kw)) {
            $n = (int) $kw;
            return in_array($n, [1, 2, 3, 4]) ? "kw{$n}" : 'kw1';
        }

        // Ambil angka di akhir string: 'kw1', 'KW 2', 'Kw-3', 'kw_4', dll.
        if (preg_match('/(\d)$/', $str, $m)) {
            $n = (int) $m[1];
            return in_array($n, [1, 2, 3, 4]) ? "kw{$n}" : 'kw1';
        }

        return 'kw1'; // fallback
    }

    public function loadAllData(): void
    {
        $this->isLoading = true;
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        $produksiList = ProduksiStik::with([
            'detailPegawaiStik.pegawai',
            'detailHasilStik',
            'detailHasilStik.ukuran',
            'detailHasilStik.jenisKayu',
        ])
            ->whereDate('tanggal_produksi', $tanggal)
            ->get();

        $targetRef        = Target::where('id_mesin', 8)->where('id_ukuran', 33)->first();
        $stdTarget        = $targetRef ? (float) $targetRef->target : 3000;
        $stdJam           = $targetRef ? (int) $targetRef->jam : 10;
        $stdPotonganHarga = $targetRef ? (float) $targetRef->potongan : 0;

        $this->dataStik = [];

        foreach ($produksiList as $produksi) {
            $tanggalFormat = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

            $hasil   = $produksi->detailHasilStik?->sum('total_lembar') ?? 0;
            $selisih = $stdTarget - $hasil;

            $jumlahPekerja    = $produksi->detailPegawaiStik?->count() ?? 0;
            $potonganPerOrang = 0;

            if ($selisih > 0 && $jumlahPekerja > 0) {
                $potonganPerOrang = ($selisih * $stdPotonganHarga) / $jumlahPekerja;
            }

            // ── DATA PEKERJA ─────────────────────────────────────
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
                        : '-',
                    'keterangan' => $detail->ket ?? '-',
                ];
            }

            // ── DETAIL HASIL (untuk sheet "Hasil Stik") ───────────
            // Group per kombinasi ukuran + jenis_kayu, pisahkan nilai ke kolom kw1–kw4
            $grouped = [];

            foreach ($produksi->detailHasilStik ?? [] as $dh) {
                $ukuran    = $dh->ukuran;
                $jenisKayu = $dh->jenisKayu;

                $p     = $ukuran?->panjang ?? '-';
                $l     = $ukuran?->lebar   ?? '-';
                $t     = $ukuran?->tebal   ?? '-';
                $jenis = $jenisKayu?->kode_kayu ?? ($jenisKayu?->nama_kayu ?? '-');
                $kwKey = $this->normalizeKw($dh->kw); // 'kw1'/'kw2'/'kw3'/'kw4'
                $total = (int) ($dh->total_lembar ?? 0);

                $groupKey = "{$p}|{$l}|{$t}|{$jenis}";

                if (!isset($grouped[$groupKey])) {
                    $grouped[$groupKey] = [
                        'panjang'    => $p,
                        'lebar'      => $l,
                        'tebal'      => $t,
                        'jenis_kayu' => $jenis,
                        'kw1'        => 0,
                        'kw2'        => 0,
                        'kw3'        => 0,
                        'kw4'        => 0,
                        'af'         => 0,
                        'total'      => 0,
                    ];
                }

                $grouped[$groupKey][$kwKey] += $total;
                $grouped[$groupKey]['total'] += $total;
            }

            // Ubah 0 menjadi '' supaya sel Excel kosong (lebih rapi)
            $detailHasilArray = [];
            foreach ($grouped as $item) {
                $detailHasilArray[] = [
                    'panjang'    => $item['panjang'],
                    'lebar'      => $item['lebar'],
                    'tebal'      => $item['tebal'],
                    'jenis_kayu' => $item['jenis_kayu'],
                    'kw1'        => $item['kw1'] > 0 ? $item['kw1'] : '',
                    'kw2'        => $item['kw2'] > 0 ? $item['kw2'] : '',
                    'kw3'        => $item['kw3'] > 0 ? $item['kw3'] : '',
                    'kw4'        => $item['kw4'] > 0 ? $item['kw4'] : '',
                    'af'         => $item['af'] > 0 ? $item['af'] : '',
                    'total'      => $item['total'] > 0 ? $item['total'] : '',
                ];
            }

            $this->dataStik[] = [
                'tanggal'       => $tanggalFormat,
                'kode_ukuran'   => 'STIK',
                'pekerja'       => $pekerja,
                'detail_hasil'  => $detailHasilArray, // untuk sheet "Hasil Stik"
                'kendala'       => $produksi->kendala ?? 'Tidak ada kendala.',
                'target_harian' => $stdTarget,
                'hasil_harian'  => $hasil,
                'selisih'       => $selisih,
                'jam_kerja'     => $stdJam,
                'summary'       => ['jumlah_pekerja' => count($pekerja)],
            ];
        }

        $this->calculateOverallSummary();
        $this->isLoading = false;
    }

    protected function calculateOverallSummary(): void
    {
        $this->summary = ['total_hasil' => 0, 'total_pekerja' => 0, 'total_potongan' => 0];

        foreach ($this->dataStik as $data) {
            $this->summary['total_hasil']   += $data['hasil_harian'];
            $this->summary['total_pekerja'] += $data['summary']['jumlah_pekerja'];

            foreach ($data['pekerja'] as $p) {
                $val = ($p['pot_target'] !== '-') ? str_replace('.', '', $p['pot_target']) : 0;
                $this->summary['total_potongan'] += is_numeric($val) ? (int) $val : 0;
            }
        }
    }

    public function exportToExcel()
    {
        if (empty($this->dataStik)) return;

        $tanggal  = $this->tanggal ?? now()->format('Y-m-d');
        $filename = 'Laporan-Produksi-Stik-' . Carbon::parse($tanggal)->format('Y-m-d') . '.xlsx';

        return Excel::download(new LaporanProduksiStikExport($this->dataStik), $filename);
    }
}
