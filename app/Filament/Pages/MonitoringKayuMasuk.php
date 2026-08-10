<?php

namespace App\Filament\Pages;

use App\Models\KayuMasuk;
use App\Models\SupplierKayu;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\WithPagination;

class MonitoringKayuMasuk extends Page
{
    use WithPagination;
    use HasPageShield;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Monitoring Kayu Masuk';
    protected static ?string $title = 'Monitoring Kayu Masuk';
    protected static ?string $slug = 'monitoring-kayu-masuk';
    protected string $view = 'filament.pages.monitoring-kayu-masuk';

    public string $search = '';
    public string $statusLogistik = 'ALL';
    public string $supplierId = 'ALL';
    public ?string $dariTanggal = null;
    public ?string $sampaiTanggal = null;
    public bool $showDokumenCol = false;
    public array $expandedRows = [];
    public string $bulan = 'ALL';
    public string $tahun;
    public array $detailsCache = [];

    private const CACHE_TTL_SUPPLIER = 21600; // 6 jam

    public function mount(): void
    {
        $this->tahun = now()->year;
        $this->bulan = now()->format('m');
        $this->applyBulanTahunFilter();
    }

    public function updatedBulan(): void
    {
        $this->applyBulanTahunFilter();
        $this->resetPage();
    }

    public function updatedTahun(): void
    {
        $this->applyBulanTahunFilter();
        $this->resetPage();
    }

    protected function applyBulanTahunFilter(): void
    {
        if ($this->bulan === 'ALL') {
            $this->dariTanggal = null;
            $this->sampaiTanggal = null;
            return;
        }

        $tanggal = Carbon::createFromDate((int) $this->tahun, (int) $this->bulan, 1);
        $this->dariTanggal = $tanggal->copy()->startOfMonth()->toDateString();
        $this->sampaiTanggal = $tanggal->copy()->endOfMonth()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedStatusLogistik(): void
    {
        $this->resetPage();
    }
    public function updatedSupplierId(): void
    {
        $this->resetPage();
    }
    public function updatedDariTanggal(): void
    {
        $this->resetPage();
    }
    public function updatedSampaiTanggal(): void
    {
        $this->resetPage();
    }

    public function toggleDokumenCol(): void
    {
        $this->showDokumenCol = ! $this->showDokumenCol;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusLogistik', 'supplierId', 'showDokumenCol']);
        $this->tahun = now()->year;
        $this->bulan = now()->format('m');
        $this->applyBulanTahunFilter();
        $this->resetPage();
    }

    public function toggleRow(int $id): void
    {
        if (in_array($id, $this->expandedRows, true)) {
            $this->expandedRows = array_diff($this->expandedRows, [$id]);
            unset($this->detailsCache[$id]); // buang dari cache biar payload gak menumpuk
            return;
        }

        $this->expandedRows[] = $id;

        // Query data SEKALI di sini, bukan setiap kali Blade dirender ulang
        if (! isset($this->detailsCache[$id])) {
            $this->detailsCache[$id] = $this->getExpandedDetail($id);
        }
    }

    public function getSuppliersProperty(): Collection
    {
        return Cache::remember(
            'list_supplier_kayu',
            self::CACHE_TTL_SUPPLIER,
            fn() => SupplierKayu::orderBy('nama_supplier')->get(['id', 'nama_supplier'])
        );
    }

    public function getMonitoringDataProperty()
    {
        $query = KayuMasuk::query()
            ->select([
                'id',
                'seri',
                'jenis_dokumen_angkut',
                'id_supplier_kayus',
                'id_kendaraan_supplier_kayus',
                'tgl_kayu_masuk', // BARU — ditambahkan
                'updated_at',
            ])
            ->with([
                'penggunaanSupplier:id,nama_supplier',
                'penggunaanKendaraanSupplier:id,jenis_kendaraan,nopol_kendaraan',
                'notaKayu:id,id_kayu_masuk,no_nota,status,status_pelunasan,penanggung_jawab,penerima,satpam',
            ])
            ->withExists('detailTurusanKayus as has_turus')
            ->withExists('detailTurunKayus as has_turun');

        if (trim($this->search) !== '') {
            $searchLower = strtolower(trim($this->search));
            $query->where(function (Builder $q) use ($searchLower) {
                $q->where('seri', 'like', "%{$searchLower}%")
                    ->orWhereHas('penggunaanSupplier', fn($s) => $s->where('nama_supplier', 'like', "%{$searchLower}%"))
                    ->orWhereHas('penggunaanKendaraanSupplier', fn($k) => $k->where('nopol_kendaraan', 'like', "%{$searchLower}%")->orWhere('pemilik_kendaraan', 'like', "%{$searchLower}%"))
                    ->orWhereHas('notaKayu', fn($n) => $n->where('no_nota', 'like', "%{$searchLower}%"));
            });
        }

        if ($this->supplierId !== 'ALL' && $this->supplierId !== '') {
            $query->where('id_supplier_kayus', $this->supplierId);
        }

        if ($this->dariTanggal) {
            $query->whereDate('updated_at', '>=', $this->dariTanggal);
        }

        if ($this->sampaiTanggal) {
            $query->whereDate('updated_at', '<=', $this->sampaiTanggal);
        }

        if ($this->statusLogistik !== 'ALL') {
            match ($this->statusLogistik) {
                'BELUM_DIAPA_APAIN' => $query->doesntHave('detailTurusanKayus')->doesntHave('notaKayu'),
                'SELESAI_TURUS' => $query->has('detailTurusanKayus')->doesntHave('notaKayu'),
                'DIBUAT_BELUM_DIPERIKSA' => $query->whereHas(
                    'notaKayu',
                    fn($n) => $n->whereRaw('UPPER(TRIM(status)) NOT LIKE ?', ['%SUDAH DIPERIKSA%'])
                ),
                'DICETAK_BELUM_LUNAS' => $query->whereHas(
                    'notaKayu',
                    fn($n) => $n->whereRaw('UPPER(TRIM(status)) LIKE ?', ['%SUDAH DIPERIKSA%'])
                        ->whereRaw('UPPER(TRIM(status_pelunasan)) NOT LIKE ?', ['LUNAS%'])
                ),
                'DICETAK_SUDAH_LUNAS' => $query->whereHas(
                    'notaKayu',
                    fn($n) => $n->whereRaw('UPPER(TRIM(status)) LIKE ?', ['%SUDAH DIPERIKSA%'])
                        ->whereRaw('UPPER(TRIM(status_pelunasan)) LIKE ?', ['LUNAS%'])
                ),
                default => null,
            };
        }

        return $query->latest('updated_at')->paginate(50);
    }

    public function getExpandedDetail(int $kayuMasukId): ?array
    {
        $item = KayuMasuk::query()
            ->with([
                'detailMasukanKayu:id,id_kayu_masuk,id_jenis_kayu,panjang,diameter,jumlah_batang',
                'detailMasukanKayu.jenisKayu:id,nama_kayu',
                'detailTurusanKayus:id,id_kayu_masuk,jenis_kayu_id,panjang,diameter,kuantitas',
                'detailTurusanKayus.jenisKayu:id,nama_kayu',
            ])
            ->find($kayuMasukId);

        if (! $item) {
            return null;
        }

        $totalBatang = $item->detailTurusanKayus->sum('kuantitas');
        $totalVolume = $item->detailTurusanKayus->sum(
            fn($t) => $t->panjang * $t->diameter * $t->diameter * $t->kuantitas * 0.785 / 1000000
        );

        return [
            'comparison' => $this->comparisonData($item),
            'total_batang' => $totalBatang,
            'total_volume' => $totalVolume,
        ];
    }

    public static function forgetSupplierCache(): void
    {
        Cache::forget('list_supplier_kayu');
    }

    public function isSudahDiperiksa(?string $status): bool
    {
        return str_contains($this->normalizeStatus($status), 'SUDAH DIPERIKSA');
    }

    public function getHasActiveAdvancedFiltersProperty(): bool
    {
        return $this->statusLogistik !== 'ALL' || $this->supplierId !== 'ALL' || $this->showDokumenCol;
    }

    protected function normalizeStatus(?string $status): string
    {
        return strtoupper(trim((string) $status));
    }

    public function isLunas(?string $status): bool
    {
        $normalized = $this->normalizeStatus($status);
        return str_contains($normalized, 'LUNAS') && ! str_contains($normalized, 'BELUM');
    }

    public function isDpPelunasan(?string $status): bool
    {
        if ($this->isLunas($status)) {
            return false;
        }

        $normalized = $this->normalizeStatus($status);

        foreach (['DP', 'SEBAGIAN', 'PARSIAL', 'CICIL'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function extractTanggalLunas(?string $status): ?string
    {
        if (! preg_match('/Lunas\s*-\s*(\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2})/', $status ?? '', $matches)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y H:i', $matches[1])->format('d/m/y H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function statusPelunasanLabel(?string $status): string
    {
        if ($this->isLunas($status)) {
            $tanggal = $this->extractTanggalLunas($status);
            return $tanggal ? "Lunas - {$tanggal}" : 'Lunas';
        }

        if ($this->isDpPelunasan($status)) {
            return 'DP (Sebagian)';
        }

        $normalized = $this->normalizeStatus($status);

        if ($normalized === '' || $normalized === 'BELUM LUNAS' || $normalized === 'BELUM_LUNAS') {
            return 'BELUM LUNAS';
        }

        return $status;
    }

    public function comparisonData($item): array
    {
        $map = [];

        foreach ($item->detailMasukanKayu as $m) {
            $key = $m->id_jenis_kayu . '_' . $m->panjang . '_' . $m->diameter;
            $map[$key] ??= [
                'jenis_kayu' => $m->jenisKayu?->nama_kayu ?? 'Kayu',
                'panjang' => $m->panjang,
                'diameter' => $m->diameter,
                'turusan_1' => 0,
                'turusan_2' => 0,
            ];
            $map[$key]['turusan_1'] += $m->jumlah_batang;
        }

        foreach ($item->detailTurusanKayus as $t) {
            $key = $t->jenis_kayu_id . '_' . $t->panjang . '_' . $t->diameter;
            $map[$key] ??= [
                'jenis_kayu' => $t->jenisKayu?->nama_kayu ?? 'Kayu',
                'panjang' => $t->panjang,
                'diameter' => $t->diameter,
                'turusan_1' => 0,
                'turusan_2' => 0,
            ];
            $map[$key]['turusan_2'] += $t->kuantitas;
        }

        foreach ($map as &$row) {
            $row['selisih'] = $row['turusan_2'] - $row['turusan_1'];
        }

        return array_values($map);
    }
}
