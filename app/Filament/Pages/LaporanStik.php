<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\DatePicker;

use App\Models\ProduksiStik;
use App\Models\Target;
use App\Models\DetailPegawaiStik;

use Filament\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LaporanProduksiStikExport;
use Carbon\Carbon;

use BackedEnum;
use UnitEnum;


class LaporanStik extends Page
{
    use InteractsWithForms;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-stik';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Stik';
    protected static ?int $navigationSort = 5;

    public $dataStik = [];
    public $tanggal = null;
    public $summary = []; // Untuk total bawah

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
                ->reactive()
                ->format('Y-m-d')                  // format penyimpanan
                ->displayFormat('d/m/Y')
                ->live()
                ->required()
                ->maxDate(now())
                ->default(now())
                ->suffixIconColor('primary') // tidak boleh pilih tanggal di masa depan
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

    // Fungsi Pembulatan Khusus (Copy dari Rotary)
    protected function roundToNearestHundred(float $number): int
    {
        $thousands = floor($number / 1000);
        $base = $thousands * 1000;
        $remainder = $number - $base;

        if ($remainder < 300) {
            return $base;
        } elseif ($remainder < 800) {
            return $base + 500;
        } else {
            return $base + 1000;
        }
    }

    public function loadAllData()
    {
        $this->isLoading = true;
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        // Ambil Data Produksi Stik
        // Kueri hanya berdasarkan tanggal untuk menampilkan tabel berkala
        // Menambahkan relasi 'detailHasilStik' untuk menghitung hasil
        $produksiList = ProduksiStik::with(['detailPegawaiStik.pegawai', 'detailHasilStik'])
            ->whereDate('tanggal_produksi', $tanggal)
            ->get();

        // Ambil Data Target dari Database (Kode Ukuran: STIK)
        $targetRef = Target::where('kode_ukuran', 'STIK')->first();

        $stdTarget = $targetRef->target ?? 7000; // Default 7000 jika db null
        $stdJam = $targetRef->jam ?? 10;
        $stdPotonganHarga = $targetRef->potongan ?? 0; // 32.86 dari screenshot

        $this->dataStik = [];

        foreach ($produksiList as $produksi) {
            $tanggalFormat = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

            // --- HITUNG HASIL ---
            // Menggunakan 0 jika relasi detailHasilStik tidak ada (belum diisi)
            $hasil = $produksi->detailHasilStik ? $produksi->detailHasilStik->sum('total_lembar') : 0;
            // Hitung Selisih (Target - Hasil)
            $selisih = $stdTarget - $hasil;

            // --- HITUNG POTONGAN ---
            // Menggunakan 0 jika relasi detailPegawaiStik tidak ada
            $jumlahPekerja = $produksi->detailPegawaiStik ? $produksi->detailPegawaiStik->count() : 0;

            $potonganPerOrang = 0;

            // Logika Potongan: Hanya jika target tidak tercapai (Selisih > 0)
            if ($selisih > 0) {
                $totalUangPotongan = $selisih * $stdPotonganHarga;
                $potonganPerOrang = $jumlahPekerja > 0 ? $totalUangPotongan / $jumlahPekerja : 0;
            }

            // Data Pekerja
            $pekerja = [];
            if ($produksi->detailPegawaiStik) {
                foreach ($produksi->detailPegawaiStik as $detail) {
                    $pekerja[] = [
                        'id' => $detail->pegawai?->kode_pegawai ?? '-',
                        'nama' => $detail->pegawai?->nama_pegawai ?? '-',
                        'jam_masuk' => $detail->masuk ? Carbon::parse($detail->masuk)->format('H:i') : '-',
                        'jam_pulang' => $detail->pulang ? Carbon::parse($detail->pulang)->format('H:i') : '-',
                        'ijin' => $detail->ijin ?? '-',
                        'pot_target' => $potonganPerOrang > 0
                            ? number_format($this->roundToNearestHundred($potonganPerOrang), 0, '', '.')
                            : '-',
                        'keterangan' => $detail->ket ?? '-',
                    ];
                }
            }

            $this->dataStik[] = [
                'tanggal' => $tanggalFormat,
                'kode_ukuran' => 'STIK',
                'pekerja' => $pekerja,
                'kendala' => $produksi->kendala ?? 'Tidak ada kendala.',
                'target_harian' => $stdTarget,
                'hasil_harian' => $hasil,
                'selisih' => $selisih, // Nilai positif berarti kurang target
                'jam_kerja' => $stdJam,
                'summary' => [
                    'jumlah_pekerja' => count($pekerja),
                ]
            ];
        }

        // Hitung Total Summary untuk Footer (Opsional)
        $this->calculateOverallSummary();

        $this->isLoading = false;
    }

    protected function calculateOverallSummary()
    {
        // Inisialisasi Summary Total
        $this->summary = [
            'total_hasil' => 0,
            'total_pekerja' => 0,
            'total_potongan' => 0
        ];

        foreach ($this->dataStik as $data) {
            $this->summary['total_hasil'] += $data['hasil_harian'];
            $this->summary['total_pekerja'] += $data['summary']['jumlah_pekerja'];

            foreach ($data['pekerja'] as $p) {
                // Konversi string potongan menjadi integer, tangani '-'
                $val = ($p['pot_target'] !== '-') ? str_replace('.', '', $p['pot_target']) : 0;
                $val = is_numeric($val) ? (int)$val : 0;
                $this->summary['total_potongan'] += $val;
            }
        }
    }

    public function exportToExcel()
    {
        // Pastikan ada data yang dimuat sebelum diexport
        if (empty($this->dataStik)) {
            return;
        }

        $tanggal = $this->tanggal ?? now()->format('Y-m-d');
        $filename = 'Laporan-Produksi-Stik-' . Carbon::parse($tanggal)->format('Y-m-d') . '.xlsx';

        // Gunakan LaporanProduksiStikExport
        return Excel::download(new LaporanProduksiStikExport($this->dataStik), $filename);
    }
}
