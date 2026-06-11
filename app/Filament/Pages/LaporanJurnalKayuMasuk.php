<?php

namespace App\Filament\Pages;

use App\Models\NotaKayu;
use App\Services\NotaKayuJurnalPayloadService;
use Filament\Pages\Page;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LaporanJurnalKayuMasukExport;
use Carbon\Carbon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanJurnalKayuMasuk extends Page implements \Filament\Forms\Contracts\HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-jurnal-kayu-masuk';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Jurnal Kayu Masuk';
    protected static ?int $navigationSort = 7;

    public $tanggal = null;
    public array $jurnalTables = [];
    public bool $isLoading = false;

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->form->fill(['tanggal' => $this->tanggal]);
        $this->loadData();
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('tanggal')
                ->label('Tanggal')
                ->reactive()
                ->format('Y-m-d')
                ->displayFormat('d/m/Y')
                ->live()
                ->required()
                ->maxDate(now())
                ->default(now())
                ->afterStateUpdated(fn($state) => $this->onTanggalUpdated($state)),
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
            
            Action::make('back')
                ->label('Kembali ke Rekap')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url('/admin/rekap-kayu-masuks'),
        ];
    }

    public function onTanggalUpdated($state): void
    {
        $this->tanggal = $state;
        $this->loadData();
    }

    public function getAccountDetails(string $jenisKayuNama, $panjang): array
    {
        $jenis = strtolower(trim($jenisKayuNama));
        $panjang = (int) $panjang;

        $isWHN = false;
        if (request()) {
            $host = request()->getHost();
            if ($host === 'wahana.wijayaplywoods.com' || env('APP_COMPANY') === 'WHN') {
                $isWHN = true;
            }
        }

        $isLunak = (str_contains($jenis, 'sengon') || str_contains($jenis, 'lunak'));
        $isMeranti = str_contains($jenis, 'meranti');
        $isRijek = str_contains($jenis, 'rijek');
        $isLogCore = str_contains($jenis, 'log core') || str_contains($jenis, 'core');
        $isBaloan = str_contains($jenis, 'baloan') || str_contains($jenis, 'balo,an');

        if ($isWHN) {
            if ($isMeranti) {
                return ['no_akun' => '1413.09', 'nama_akun' => 'Kayu Meranti WHN'];
            }
            if ($isRijek) {
                return ['no_akun' => '1413.10', 'nama_akun' => 'Kayu Rijek WHN'];
            }
            if ($isLogCore) {
                if ($panjang === 130) {
                    return ['no_akun' => '1414.00', 'nama_akun' => 'log core 130 WHN'];
                }
                return ['no_akun' => '1413.13', 'nama_akun' => 'log core 260 WHN'];
            }
            if ($isBaloan) {
                return ['no_akun' => '1413.05', 'nama_akun' => 'kayu balo,an'];
            }

            // Lunak
            if ($isLunak) {
                if ($panjang === 130) {
                    return ['no_akun' => '1413.07', 'nama_akun' => 'Kayu Lunak 130 WHN'];
                }
                if ($panjang === 230) {
                    return ['no_akun' => '1413.11', 'nama_akun' => 'kayu Lunak 230 WHN'];
                }
                if ($panjang === 100) {
                    return ['no_akun' => '1413.12', 'nama_akun' => 'kayu Lunak 100 WHN'];
                }
                return ['no_akun' => '1413.01', 'nama_akun' => 'kayu Lunak 260 WHN'];
            }

            // Keras (default)
            if ($panjang === 130) {
                return ['no_akun' => '1413.08', 'nama_akun' => 'Kayu Keras 130 WHN'];
            }
            return ['no_akun' => '1413.06', 'nama_akun' => 'Kayu Keras 260 WHN'];
        } else {
            // WJY
            if ($isMeranti) {
                return ['no_akun' => '1411.05', 'nama_akun' => 'Kayu Meranti WJY'];
            }
            if ($isRijek) {
                return ['no_akun' => '1411.06', 'nama_akun' => 'Kayu Rijek WJY'];
            }
            if ($isLogCore) {
                if ($panjang === 130) {
                    return ['no_akun' => '1413.04', 'nama_akun' => 'log core 130 WJY'];
                }
                return ['no_akun' => '1413.03', 'nama_akun' => 'log core 260 WJY'];
            }
            if ($isBaloan) {
                return ['no_akun' => '1413.05', 'nama_akun' => 'kayu balo,an'];
            }

            // Lunak
            if ($isLunak) {
                if ($panjang === 130) {
                    return ['no_akun' => '1411.03', 'nama_akun' => 'Kayu Lunak 130 WJY'];
                }
                if ($panjang === 230) {
                    return ['no_akun' => '1411.07', 'nama_akun' => 'kayu Lunak 230 WJY'];
                }
                if ($panjang === 100) {
                    return ['no_akun' => '1411.08', 'nama_akun' => 'kayu Lunak 100 WJY'];
                }
                return ['no_akun' => '1411.01', 'nama_akun' => 'kayu Lunak 260 WJY'];
            }

            // Keras (default)
            if ($panjang === 130) {
                return ['no_akun' => '1411.04', 'nama_akun' => 'Kayu Keras 130 WJY'];
            }
            return ['no_akun' => '1411.02', 'nama_akun' => 'Kayu Keras 260 WJY'];
        }
    }

    public function loadData(): void
    {
        $this->isLoading = true;

        if (empty($this->tanggal)) {
            $this->jurnalTables = [];
            $this->isLoading = false;
            return;
        }

        $dateStr = Carbon::parse($this->tanggal)->format('d/m/Y');

        // Fetch all NotaKayu marked as Lunas on this date with all related tables
        $notas = NotaKayu::with([
            'kayuMasuk.detailTurusanKayus.jenisKayu',
            'kayuMasuk.detailTurusanKayus.lahan',
            'kayuMasuk.penggunaanSupplier',
            'kayuMasuk.penggunaanKendaraanSupplier',
            'kayuMasuk.penggunaanDokumenKayu',
        ])
        ->where('status_pelunasan', 'LIKE', "Lunas - {$dateStr}%")
        ->get();

        $tables = [];

        foreach ($notas as $nota) {
            $details = $nota->kayuMasuk->detailTurusanKayus ?? collect();

            if ($details->isEmpty()) {
                continue;
            }

            // Group by Lahan, Grade, Panjang, and Jenis Kayu
            $grouped = $details->groupBy(function($item) {
                $kodeLahan = optional($item->lahan)->kode_lahan ?? '-';
                $grade = $item->grade ?? 0;
                $panjang = $item->panjang ?? '-';
                $jenis = optional($item->jenisKayu)->nama_kayu ?? '-';
                return "{$kodeLahan}|{$grade}|{$panjang}|{$jenis}";
            });

            $grandBatang = 0;
            $grandM3 = 0;
            $grandTotal = 0;
            $groupsData = [];

            foreach ($grouped as $key => $items) {
                [$kodeLahan, $grade, $panjang, $jenis] = explode('|', $key);
                $gradeText = $grade == 1 ? 'A' : ($grade == 2 ? 'B' : '-');
                
                $firstItem = $items->first();
                $idJenisKayu = optional($firstItem->jenisKayu)->id ?? $firstItem->id_jenis_kayu ?? null;
                
                $groupedByDiameter = $this->groupByRentangDiameter($items, $idJenisKayu, $grade, $panjang);
                
                $totalBatangGrup = $groupedByDiameter->sum('batang');
                $totalKubikasiGrup = $groupedByDiameter->sum('kubikasi');
                $totalHargaGrup = $groupedByDiameter->sum('total_harga');

                $grandBatang += $totalBatangGrup;
                $grandM3 += $totalKubikasiGrup;
                $grandTotal += $totalHargaGrup;

                $groupsData[] = [
                    'header' => "{$kodeLahan}    {$panjang} cm {$jenis} ({$gradeText})",
                    'kode_lahan' => $kodeLahan,
                    'grade' => $grade,
                    'panjang' => $panjang,
                    'jenis' => $jenis,
                    'rows' => $groupedByDiameter->toArray(),
                    'total_batang' => $totalBatangGrup,
                    'total_kubikasi' => round($totalKubikasiGrup, 4),
                    'total_harga' => $totalHargaGrup,
                ];
            }

            // =========================
            // BIAYA TURUN KAYU & PEMBULATAN
            // =========================
            $pembulatanManual = (int) ($nota->adjustment ?? 0);
            $biayaTurunPerM3  = 5000;

            $hasilDasar = round($grandM3 * $biayaTurunPerM3);
            $biayaFloor = floor($hasilDasar / 1000) * 1000;

            // Biaya turun dipengaruhi oleh sisa ribuan dari grand total
            $sisaRibuan = $grandTotal % 1000;
            $biayaTurunKayu = (int) ($biayaFloor + $sisaRibuan + 10000);

            // =========================
            // HARGA AKHIR (NETTO)
            // =========================
            $hargaBeliAkhir = (int) ($grandTotal - $biayaTurunKayu);

            // Tahap 1: Bulatkan ke kelipatan 5.000 terdekat
            $mod = $hargaBeliAkhir % 5000;
            $hargaBeliAkhirBulat = $mod >= 2500
                ? $hargaBeliAkhir + (5000 - $mod)
                : $hargaBeliAkhir - $mod;

            // Tahap 2: Tambahkan penyesuaian manual (Adjustment)
            $totalAkhir = (int) ($hargaBeliAkhirBulat + $pembulatanManual);

            // Tahap 3: Final pembulatan tetap harus kelipatan 5.000
            $modFinal = $totalAkhir % 5000;
            $totalAkhir = $modFinal >= 2500
                ? $totalAkhir + (5000 - $modFinal)
                : $totalAkhir - $modFinal;

            $selisih = (int) ($grandTotal - $totalAkhir);

            // Extract lunas date from status_pelunasan as tglJurnalExcel (format: "Lunas - dd/mm/yyyy hh:mm (username)")
            $tglJurnalExcel = $nota->kayuMasuk->tgl_kayu_masuk ?? null;
            if (preg_match('/Lunas\s*-\s*(\d{2})\/(\d{2})\/(\d{4})/', $nota->status_pelunasan ?? '', $matches)) {
                $tglJurnalExcel = "{$matches[3]}-{$matches[2]}-{$matches[1]}"; // "yyyy-mm-dd" for Carbon::parse
            }

            $tableRows = [];
            $tglVal = $tglJurnalExcel ? Carbon::parse($tglJurnalExcel)->format('d/m/Y') : '-';

            // 1. Add Debit entries from groups
            foreach ($groupsData as $group) {
                $jenisKayuNama = $group['jenis'];
                $panjang = $group['panjang'];
                $acc = $this->getAccountDetails($jenisKayuNama, $panjang);

                $tableRows[] = [
                    'nama_akun' => $acc['nama_akun'],
                    'tgl' => $tglVal,
                    'jur' => '',
                    'no_akun' => $acc['no_akun'],
                    'no' => $nota->kayuMasuk->seri ?? '-',
                    'nama_supplier' => $nota->kayuMasuk->penggunaanSupplier?->nama_supplier ?? '-',
                    'lahan' => $group['kode_lahan'],
                    'm' => 'd',
                    'hit_kbk' => 'm',
                    'banyak' => $group['total_batang'],
                    'm3' => $group['total_kubikasi'],
                    'harga' => $group['total_harga'],
                    'total' => $group['total_harga'],
                ];
            }

            // 2. Add Credit Row 1: hutang ongkos turun kayu
            $tableRows[] = [
                'nama_akun' => 'hutang ongkos turun kayu',
                'tgl' => $tglVal,
                'jur' => '',
                'no_akun' => '2400.01',
                'no' => $nota->kayuMasuk->seri ?? '-',
                'nama_supplier' => $nota->kayuMasuk->penggunaanSupplier?->nama_supplier ?? '-',
                'lahan' => '',
                'm' => 'k',
                'hit_kbk' => '',
                'banyak' => null,
                'm3' => null,
                'harga' => $selisih,
                'total' => $selisih,
            ];

            // 3. Add Credit Row 2: pendapatan
            $tableRows[] = [
                'nama_akun' => 'pendapatan',
                'tgl' => $tglVal,
                'jur' => '',
                'no_akun' => '4000.00',
                'no' => $nota->kayuMasuk->seri ?? '-',
                'nama_supplier' => $nota->kayuMasuk->penggunaanSupplier?->nama_supplier ?? '-',
                'lahan' => '',
                'm' => 'k',
                'hit_kbk' => '',
                'banyak' => null,
                'm3' => null,
                'harga' => null,
                'total' => null,
            ];

            // 4. Add Credit Row 3: Kas Mut
            $tableRows[] = [
                'nama_akun' => 'Kas Mut',
                'tgl' => $tglVal,
                'jur' => '',
                'no_akun' => '1111.00',
                'no' => $nota->kayuMasuk->seri ?? '-',
                'nama_supplier' => $nota->kayuMasuk->penggunaanSupplier?->nama_supplier ?? '-',
                'lahan' => '',
                'm' => 'k',
                'hit_kbk' => '',
                'banyak' => $grandBatang,
                'm3' => $grandM3,
                'harga' => $totalAkhir,
                'total' => $totalAkhir,
            ];

            $tables[] = [
                'no_nota' => $nota->no_nota,
                'seri' => $nota->kayuMasuk->seri ?? '-',
                'tgl_kayu_masuk' => $tglJurnalExcel ?? '-',
                'nama_supplier' => $nota->kayuMasuk->penggunaanSupplier?->nama_supplier ?? '-',
                'nopol_kendaraan' => $nota->kayuMasuk->penggunaanKendaraanSupplier?->nopol_kendaraan ?? '-',
                'dokumen_legal' => $nota->kayuMasuk->penggunaanDokumenKayu?->dokumen_legal ?? '-',
                'penanggung_jawab' => $nota->penanggung_jawab ?? '-',
                'penerima' => $nota->penerima ?? '-',
                'totalBatang' => $grandBatang,
                'totalKubikasi' => round($grandM3, 4),
                'grandTotal' => $grandTotal,
                'biayaTurunKayu' => $biayaTurunKayu,
                'pembulatanManual' => $pembulatanManual,
                'totalAkhir' => $hargaBeliAkhir,
                'hargaFinal' => $totalAkhir,
                'selisih' => $selisih,
                'groups' => $groupsData,
                'rows' => $tableRows,
            ];
        }

        $this->jurnalTables = $tables;
        $this->isLoading = false;
    }

    /**
     * Helper to group details by diameter range.
     */
    public function groupByRentangDiameter($details, $idJenisKayu, $grade, $panjang)
    {
        $rentangList = \App\Models\HargaKayu::where('id_jenis_kayu', $idJenisKayu)
            ->where('grade', $grade)
            ->where('panjang', $panjang)
            ->orderBy('diameter_terkecil')
            ->get();

        $hasil       = collect();
        $terpakaiIds = collect();

        foreach ($rentangList as $rentang) {
            $kelompok = $details->filter(function ($item) use ($rentang) {
                return $item->diameter >= $rentang->diameter_terkecil
                    && $item->diameter <= $rentang->diameter_terbesar;
            });

            if ($kelompok->isNotEmpty()) {
                $totalBatang   = $kelompok->sum('kuantitas');
                $totalKubikasi = $kelompok->sum(fn($item) => round($item->kubikasi, 4));

                $harga = $kelompok->first()->harga ?? 0;
                $totalHarga = round($harga * $totalKubikasi * 1000);

                $hasil->push([
                    'rentang'      => "{$rentang->diameter_terkecil} - {$rentang->diameter_terbesar}",
                    'batang'       => $totalBatang,
                    'kubikasi'     => round($totalKubikasi, 4),
                    'harga_satuan' => $harga,
                    'total_harga'  => $totalHarga,
                ]);

                $terpakaiIds = $terpakaiIds->merge($kelompok->pluck('id'));
            }
        }

        // Penanganan Item Sisa (Di luar rentang master)
        $sisa = $details->whereNotIn('id', $terpakaiIds);
        foreach ($sisa as $item) {
            $hasil->push([
                'rentang'      => "{$item->diameter} (Manual)",
                'batang'       => $item->kuantitas,
                'kubikasi'     => round($item->kubikasi, 4),
                'harga_satuan' => $item->harga ?? 0,
                'total_harga'  => round(($item->harga ?? 0) * round($item->kubikasi, 4) * 1000),
            ]);
        }

        return $hasil->sortBy(function ($i) {
            return (float) explode(' ', $i['rentang'])[0];
        })->values();
    }

    public function exportToExcel()
    {
        if (empty($this->jurnalTables)) {
            return;
        }
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');
        $filename = 'Laporan-Jurnal-Kayu-Masuk-' . Carbon::parse($tanggal)->format('Y-m-d') . '.xlsx';
        return Excel::download(new LaporanJurnalKayuMasukExport($this->jurnalTables), $filename);
    }
}
