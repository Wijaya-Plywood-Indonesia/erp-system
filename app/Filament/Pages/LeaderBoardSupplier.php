<?php

namespace App\Filament\Pages;

use App\Models\NotaKayu;
use App\Models\SupplierKayu;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class LeaderBoardSupplier extends Page
{
    protected static ?string $navigationLabel = 'Leaderboard Supplier';
    protected static ?string $title = 'Leaderboard Supplier Kayu';
    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.leader-board-supplier';

    public string $search = '';
    public string $filterDari = '';
    public string $filterSampai = '';

    // Sort metric: 'total_pembelian' | 'nota_dicetak' | 'total_kubikasi'
    public string $sortBy = 'total_pembelian';

    // State untuk membatasi tampilan leaderboard ke 20 item teratas
    public bool $showAll = false;

    #[\Livewire\Attributes\On('updated')]
    public function updatedFilterDari(): void {}

    #[\Livewire\Attributes\On('updated')]
    public function updatedFilterSampai(): void {}

    #[\Livewire\Attributes\On('updated')]
    public function updatedSearch(): void {}

    /**
     * Solusi Race Condition: Update kedua tanggal secara atomik dalam 1 request
     */
    public function applyDateFilter(string $dari, string $sampai): void
    {
        $dari = trim($dari);
        $sampai = trim($sampai);

        // Validasi format tanggal supaya input tak terduga tidak lolos ke query.
        // String kosong tetap diperbolehkan (artinya: tidak difilter dari/sampai sisi itu).
        $this->filterDari = $this->isValidDateString($dari) ? $dari : '';
        $this->filterSampai = $this->isValidDateString($sampai) ? $sampai : '';
    }

    private function isValidDateString(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    public function setSortBy(string $metric): void
    {
        if (in_array($metric, ['total_pembelian', 'nota_dicetak', 'total_kubikasi'])) {
            $this->sortBy = $metric;
        }
    }

    public function toggleShowAll(): void
    {
        $this->showAll = !$this->showAll;
    }

    /**
     * Menghitung kalkulasi finansial & kubikasi 1 Nota Kayu
     */
    public function calculateNotaTotals(NotaKayu $nota): array
    {
        $details = $nota->kayuMasuk?->detailTurusanKayus ?? collect();

        if ($details->isEmpty()) {
            return [
                'totalBatang'   => 0,
                'totalKubikasi' => 0.0,
                'grandTotal'    => 0,
                'totalAkhir'    => 0,
            ];
        }

        $totalBatang   = (int) $details->sum('kuantitas');
        $totalKubikasi = (float) $details->sum(fn($item) => $item->kubikasi);

        // 1. Hitung Grand Total (Rupiah)
        $grandTotal = 0;
        foreach ($details as $item) {
            $harga = $item->harga ?? 0;
            $kubikasi = round($item->kubikasi ?? 0, 4);
            $grandTotal += round($harga * $kubikasi * 1000);
        }
        $grandTotal = (int) round($grandTotal);

        // 2. Biaya Turun Kayu & Pembulatan
        $pembulatanManual = (int) ($nota->adjustment ?? 0);
        $biayaTurunPerM3  = 5000;

        $hasilDasar = round($totalKubikasi * $biayaTurunPerM3);
        $biayaFloor = floor($hasilDasar / 1000) * 1000;

        $sisaRibuan     = $grandTotal % 1000;
        $biayaTurunKayu = (int) ($biayaFloor + $sisaRibuan + 10000);

        // 3. Harga Akhir (Netto)
        $hargaBeliAkhir = (int) ($grandTotal - $biayaTurunKayu);

        // Tahap 1: Bulatkan ke kelipatan 5.000 terdekat
        $mod = $hargaBeliAkhir % 5000;
        $hargaBeliAkhirBulat = $mod >= 2500
            ? $hargaBeliAkhir + (5000 - $mod)
            : $hargaBeliAkhir - $mod;

        // Tahap 2: Tambahkan penyesuaian manual (Adjustment)
        $totalAkhir = (int) ($hargaBeliAkhirBulat + $pembulatanManual);

        // Tahap 3: Final pembulatan kelipatan 5.000
        $modFinal = $totalAkhir % 5000;
        $totalAkhir = $modFinal >= 2500
            ? $totalAkhir + (5000 - $modFinal)
            : $totalAkhir - $modFinal;

        return [
            'totalBatang'   => $totalBatang,
            'totalKubikasi' => round($totalKubikasi, 4),
            'grandTotal'    => $grandTotal,
            'totalAkhir'    => $totalAkhir,
        ];
    }

    private function getParsedDateRange(): array
    {
        $dari = !empty($this->filterDari) ? $this->filterDari : null;
        $sampai = !empty($this->filterSampai) ? $this->filterSampai : null;

        // Jaga-jaga: kalau urutannya kebalik, tukar supaya query tidak menghasilkan 0 baris tanpa alasan jelas
        if ($dari && $sampai && $dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'hasFilter' => !is_null($dari) || !is_null($sampai),
        ];
    }

    /**
     * Apply date filter securely to an Eloquent query for kayuMasuk
     */
    public function applyDateQuery($query, ?string $dari = null, ?string $sampai = null): void
    {
        $dari = $dari ?? (!empty($this->filterDari) ? $this->filterDari : null);
        $sampai = $sampai ?? (!empty($this->filterSampai) ? $this->filterSampai : null);

        if ($dari && $sampai && $dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        if (!$dari && !$sampai) {
            return;
        }
        if ($dari) {
            $query->whereDate('updated_at', '>=', $dari);
        }
        if ($sampai) {
            $query->whereDate('updated_at', '<=', $sampai);
        }
    }

    /**
     * Ambil Rekap Per Supplier untuk Tabel Leaderboard
     */
    public function getLeaderboardData(): array
    {
        $query = NotaKayu::query()
            ->with([
                'kayuMasuk.penggunaanSupplier',
                'kayuMasuk.detailTurusanKayus',
            ])
            ->whereHas('kayuMasuk', function ($q) {
                $q->whereNotNull('id_supplier_kayus');
            })
            // Samakan aturannya dengan slide-over: hanya nota yang statusnya "Lunas..." yang dihitung
            ->whereRaw("LOWER(TRIM(status_pelunasan)) LIKE 'lunas%'");

        // Terapkan filter tanggal yang sudah diperbaiki
        $this->applyDateQuery($query);

        $allNotas = $query->get();

        // Grouping berdasarkan Supplier
        $grouped = $allNotas->groupBy(function ($nota) {
            return $nota->kayuMasuk?->id_supplier_kayus;
        });

        $leaderboard = collect();

        foreach ($grouped as $supplierId => $notas) {
            if (!$supplierId) continue;

            $firstKayuMasuk = $notas->first()?->kayuMasuk;
            $supplier = $firstKayuMasuk?->penggunaanSupplier;
            $supplierName = $supplier?->nama_supplier ?? $supplier?->nama ?? 'Supplier #' . $supplierId;

            // Filter nama pencarian
            if (!empty($this->search) && !str_contains(strtolower($supplierName), strtolower($this->search))) {
                continue;
            }

            $totalPembelian = 0;
            $totalKubikasi = 0.0;
            $notaCount = $notas->count();

            foreach ($notas as $nota) {
                $calc = $this->calculateNotaTotals($nota);
                $totalPembelian += $calc['totalAkhir'];
                $totalKubikasi  += $calc['totalKubikasi'];
            }

            $leaderboard->push((object) [
                'supplier_id'     => $supplierId,
                'supplier_name'   => $supplierName,
                'total_pembelian' => $totalPembelian,
                'nota_dicetak'    => $notaCount,
                'total_kubikasi'  => round($totalKubikasi, 4),
            ]);
        }

        // Sorting sesuai tab terpilih
        $sortColumn = $this->sortBy;
        $sorted = $leaderboard->sortByDesc($sortColumn)->values();

        $totalCount = $sorted->count();

        // Berikan nomor peringkat
        $mapped = $sorted->map(function ($item, $index) {
            $item->rank = $index + 1;
            return $item;
        });

        // Limit 20 jika $showAll false
        $items = $this->showAll ? $mapped : $mapped->take(20);

        return [
            'items'      => $items,
            'totalCount' => $totalCount,
            'hasMore'    => $totalCount > 20,
        ];
    }

    /**
     * Ambil Rincian Nota per Supplier untuk Drawer Slide-Over
     */
    public function getSupplierDetail(int $supplierId): array
    {
        $supplier = SupplierKayu::find($supplierId);
        $supplierName = $supplier?->nama_supplier ?? $supplier?->nama ?? 'Supplier #' . $supplierId;

        $query = NotaKayu::query()
            ->with(['kayuMasuk.detailTurusanKayus'])
            ->whereHas('kayuMasuk', function ($q) use ($supplierId) {
                $q->where('id_supplier_kayus', $supplierId);
            })
            // Slide-over hanya menampilkan nota yang sudah lunas.
            // Format aslinya "Lunas - 12/03/2025 14:30 (username)", jadi dicek pakai LIKE 'lunas%'
            // (di-lowercase & di-trim dulu supaya konsisten dengan pola yang dipakai di seluruh aplikasi).
            ->whereRaw("LOWER(TRIM(status_pelunasan)) LIKE 'lunas%'");

        // Terapkan filter tanggal
        $this->applyDateQuery($query);

        $notas = $query->get()->sortByDesc(function ($nota) {
            $updatedAt = $nota->updated_at ? $nota->updated_at->format('Y-m-d H:i:s') : '0000-00-00 00:00:00';
            return $updatedAt . '_' . str_pad((string) $nota->id, 10, '0', STR_PAD_LEFT);
        })->values();

        $invoices = [];
        $totalPembelian = 0;
        $totalKubikasi = 0.0;

        foreach ($notas as $nota) {
            $calc = $this->calculateNotaTotals($nota);
            $totalPembelian += $calc['totalAkhir'];
            $totalKubikasi  += $calc['totalKubikasi'];

            $invoices[] = [
                'id'          => $nota->id,
                'nomor_nota'  => $nota->no_nota ?? 'NOTA-' . $nota->id,
                'tanggal'     => $nota->kayuMasuk?->tgl_kayu_masuk ? (string) $nota->kayuMasuk->tgl_kayu_masuk : '-',
                'grand_total' => (int) $calc['totalAkhir'],
                'kubikasi'    => (float) round($calc['totalKubikasi'], 4),
                'status'      => 'lunas',
            ];
        }
        return [
            'supplier_id'     => $supplierId,
            'name'            => $supplierName,
            'total_pembelian' => (int) $totalPembelian,
            'nota_dicetak'    => count($invoices),
            'total_kubikasi'  => (float) round($totalKubikasi, 4),
            'invoices'        => $invoices,
        ];
    }

    protected function getViewData(): array
    {
        $leaderboardData = $this->getLeaderboardData();

        return [
            'leaderboard' => $leaderboardData['items'],
            'totalCount'  => $leaderboardData['totalCount'],
            'hasMore'     => $leaderboardData['hasMore'],
        ];
    }
}
