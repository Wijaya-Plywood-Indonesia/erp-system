<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use App\Exports\RekapSandingExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\HasilSanding;
use Carbon\Carbon;
use BackedEnum;
use UnitEnum;

class RekapSanding extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-table-cells';
    protected string $view = 'filament.pages.rekap-sanding';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Rekap Produksi Sanding';
    protected static ?string $navigationLabel = 'Rekap Produksi Sanding';
    protected static ?int $navigationSort = 19;

    // Disembunyikan dari sidebar; diakses lewat tombol di halaman Laporan Produksi Sanding
    protected static bool $shouldRegisterNavigation = false;

    public ?string $tanggalAwal = null;
    public ?string $tanggalAkhir = null;

    /**
     * Data rekap dikelompokkan per kategori mesin.
     * Struktur:
     * [
     *   'Besar' => [
     *       'rekapTanggal' => [...],
     *       'rekapUkuran'  => [...],
     *       'daftarUkuran' => [...],
     *       'grandTotal'   => int,
     *   ],
     *   'Kecil' => [ ... ],
     * ]
     * Urutan key sengaja Besar dulu baru Kecil.
     */
    public array $rekapPerMesin = [];

    public function mount(): void
    {
        $this->tanggalAwal = now()->startOfMonth()->format('Y-m-d');
        $this->tanggalAkhir = now()->format('Y-m-d');

        $this->form->fill([
            'tanggalAwal' => $this->tanggalAwal,
            'tanggalAkhir' => $this->tanggalAkhir,
        ]);

        $this->loadRekap();
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('tanggalAwal')
                ->label('Tanggal Awal')
                ->format('Y-m-d')
                ->displayFormat('d/m/Y')
                ->native(false)
                ->live()
                ->afterStateUpdated(fn($state) => $this->handleFilterChange('tanggalAwal', $state)),

            DatePicker::make('tanggalAkhir')
                ->label('Tanggal Akhir')
                ->format('Y-m-d')
                ->displayFormat('d/m/Y')
                ->native(false)
                ->live()
                ->afterStateUpdated(fn($state) => $this->handleFilterChange('tanggalAkhir', $state)),
        ];
    }

    protected function handleFilterChange(string $field, $state): void
    {
        $this->{$field} = $state;
        $this->loadRekap();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->loadRekap()),

            Action::make('exportExcel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn() => $this->exportExcel())
                ->visible(fn() => !empty($this->rekapPerMesin)),
        ];
    }

    /**
     * Tentukan kategori mesin: 'Besar' atau 'Kecil'.
     * Aturan sama seperti di widget summary sebelumnya:
     * id_mesin == 24 ATAU nama mesin mengandung kata "besar" => Besar.
     */
    protected function kategoriMesin(?int $idMesin, ?string $namaMesin): string
    {
        $isBesar = ($idMesin === 24) || (stripos($namaMesin ?? '', 'besar') !== false);
        return $isBesar ? 'Besar' : 'Kecil';
    }

    /**
     * Ambil & susun data rekap dari HasilSanding berdasarkan rentang tanggal,
     * dipecah per kategori mesin (Besar / Kecil).
     */
    public function loadRekap(): void
    {
        if (!$this->tanggalAwal || !$this->tanggalAkhir) {
            return;
        }

        $awal = Carbon::parse($this->tanggalAwal)->startOfDay();
        $akhir = Carbon::parse($this->tanggalAkhir)->endOfDay();

        if ($awal->gt($akhir)) {
            Notification::make()
                ->warning()
                ->title('Rentang tanggal tidak valid')
                ->body('Tanggal awal harus sebelum atau sama dengan tanggal akhir.')
                ->send();

            $this->rekapPerMesin = [];
            return;
        }

        $rows = HasilSanding::query()
            ->join('produksi_sandings', 'produksi_sandings.id', '=', 'hasil_sandings.id_produksi_sanding')
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'hasil_sandings.id_barang_setengah_jadi')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->leftJoin('mesins', 'mesins.id', '=', 'produksi_sandings.id_mesin')
            ->whereBetween('produksi_sandings.tanggal', [$awal->format('Y-m-d'), $akhir->format('Y-m-d')])
            ->selectRaw('
                produksi_sandings.tanggal as tanggal,
                produksi_sandings.shift as shift,
                produksi_sandings.id_mesin as id_mesin,
                mesins.nama_mesin as nama_mesin,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(hasil_sandings.kuantitas) AS total
            ')
            ->groupBy(
                'produksi_sandings.tanggal',
                'produksi_sandings.shift',
                'produksi_sandings.id_mesin',
                'mesins.nama_mesin',
                'ukuran'
            )
            ->orderBy('produksi_sandings.tanggal')
            ->get();

        // Pecah baris mentah per kategori mesin
        $rowsPerKategori = ['Besar' => collect(), 'Kecil' => collect()];
        foreach ($rows as $r) {
            $kategori = $this->kategoriMesin($r->id_mesin, $r->nama_mesin);
            $rowsPerKategori[$kategori]->push($r);
        }

        $hasil = [];
        // Urutan tetap: Besar dulu, baru Kecil
        foreach (['Besar', 'Kecil'] as $kategori) {
            $rowsKategori = $rowsPerKategori[$kategori];
            if ($rowsKategori->isEmpty()) {
                continue;
            }
            $hasil[$kategori] = $this->susunRekap($rowsKategori);
        }

        $this->rekapPerMesin = $hasil;
    }

    /**
     * Susun rekap (per tanggal, dan per tanggal+shift+ukuran) dari koleksi baris mentah.
     */
    protected function susunRekap($rowsKategori): array
    {
        // ── Rekap Per Tanggal (Pagi / Malam / Total) ──
        $perTanggal = [];
        foreach ($rowsKategori as $r) {
            $tgl = Carbon::parse($r->tanggal)->format('Y-m-d');
            if (!isset($perTanggal[$tgl])) {
                $perTanggal[$tgl] = ['tanggal' => $tgl, 'pagi' => 0, 'malam' => 0, 'total' => 0];
            }
            $shiftKey = strtolower($r->shift ?? '') === 'malam' ? 'malam' : 'pagi';
            $perTanggal[$tgl][$shiftKey] += (int) $r->total;
            $perTanggal[$tgl]['total'] += (int) $r->total;
        }
        ksort($perTanggal);
        $rekapTanggal = array_values($perTanggal);

        // ── Daftar ukuran unik (header kolom dinamis), urut natural ──
        $ukuranUnik = $rowsKategori->pluck('ukuran')->unique()->values()->all();
        usort($ukuranUnik, function ($a, $b) {
            $pa = array_map('floatval', explode('x', $a));
            $pb = array_map('floatval', explode('x', $b));
            return $pa <=> $pb;
        });
        $daftarUkuran = $ukuranUnik;

        // ── Rekap Per Tanggal + Shift + Ukuran (pivot) ──
        $perUkuran = [];
        foreach ($rowsKategori as $r) {
            $tgl = Carbon::parse($r->tanggal)->format('Y-m-d');
            $shiftLabel = strtolower($r->shift ?? '') === 'malam' ? 'Malam' : 'Pagi';
            $key = $tgl . '|' . $shiftLabel;

            if (!isset($perUkuran[$key])) {
                $perUkuran[$key] = [
                    'tanggal' => $tgl,
                    'shift' => $shiftLabel,
                    'ukuran' => array_fill_keys($daftarUkuran, 0),
                    'total' => 0,
                ];
            }
            $perUkuran[$key]['ukuran'][$r->ukuran] = ($perUkuran[$key]['ukuran'][$r->ukuran] ?? 0) + (int) $r->total;
            $perUkuran[$key]['total'] += (int) $r->total;
        }

        // Urutkan: per tanggal (ascending), lalu Pagi selalu sebelum Malam
        uasort($perUkuran, function ($a, $b) {
            $cmpTanggal = strcmp($a['tanggal'], $b['tanggal']);
            if ($cmpTanggal !== 0) {
                return $cmpTanggal;
            }
            $urutShift = fn($shift) => $shift === 'Malam' ? 1 : 0;
            return $urutShift($a['shift']) <=> $urutShift($b['shift']);
        });
        $rekapUkuran = array_values($perUkuran);

        return [
            'rekapTanggal' => $rekapTanggal,
            'rekapUkuran' => $rekapUkuran,
            'daftarUkuran' => $daftarUkuran,
            'grandTotal' => collect($rekapTanggal)->sum('total'),
        ];
    }

    public function exportExcel()
    {
        try {
            if (empty($this->rekapPerMesin)) {
                throw new \Exception('Tidak ada data untuk diunduh.');
            }

            $namaFile = 'rekap-produksi-sanding-'
                . Carbon::parse($this->tanggalAwal)->format('d-m-Y') . '_sd_'
                . Carbon::parse($this->tanggalAkhir)->format('d-m-Y') . '.xlsx';

            return Excel::download(
                new RekapSandingExport(
                    $this->rekapPerMesin,
                    $this->tanggalAwal,
                    $this->tanggalAkhir
                ),
                $namaFile
            );
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Export Excel')
                ->body($e->getMessage())
                ->send();
        }
    }
}
