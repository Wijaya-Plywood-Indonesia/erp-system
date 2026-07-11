<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\Widgets;

use App\Models\BahanTerimaGudangSatu;
use App\Models\HasilTerimaGudangSatu;
use App\Models\PegawaiTerimaGudangSatu;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class ProduksiTerimaGudangSatuSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-terima-gudang-satu.widgets.summary';

    public ?Model $record = null;

    public array $summary = [];

    protected int|string|array $columnSpan = 'full';

    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (! $id) {
            return [];
        }

        return [
            "echo:production.terima_gudang_satu.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    public function refreshSummary(): void
    {
        if (! $this->record) {
            return;
        }

        $produksiId = $this->record->id;

        $totalPegawai = PegawaiTerimaGudangSatu::where('id_produksi_terima_gudang_satu', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // ── TABEL BAHAN (modal) ─────────────────────────────
        // ── TABEL BAHAN (modal) ─────────────────────────────
        $bahan = BahanTerimaGudangSatu::where('id_produksi_terima_gudang_satu', $produksiId)
            ->with(['barangSetengahJadiHp.jenisBarang', 'barangSetengahJadiHp.ukuran', 'barangSetengahJadiHp.grade'])
            ->get()
            ->groupBy(fn ($b) => $b->id_barang_setengah_jadi_hp)
            ->map(function ($items) {
                $bsj = $items->first()->barangSetengahJadiHp;

                return [
                    'jenis' => $bsj?->jenisBarang?->nama_jenis_barang ?? '-',
                    'ukuran' => $bsj?->ukuran?->dimensi ?? '-',
                    'grade' => $bsj?->grade?->nama_grade ?? '-',
                    'jumlah' => $items->sum('jumlah'),
                ];
            })
            ->sortBy('ukuran')
            ->values();

        // ── TABEL HASIL ─────────────────────────────
        $hasil = HasilTerimaGudangSatu::where('id_produksi_terima_gudang_satu', $produksiId)
            ->with(['jenisBarang', 'ukuran', 'grade'])
            ->get()
            ->groupBy(fn ($h) => $h->id_jenis_barang.'-'.$h->id_ukuran.'-'.$h->id_grade)
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'jenis' => $first->jenisBarang?->nama_jenis_barang ?? '-',
                    'ukuran' => $first->ukuran?->dimensi ?? '-',
                    'grade' => $first->grade?->nama_grade ?? '-',
                    'jumlah' => $items->sum('jumlah'),
                ];
            })
            ->sortBy('ukuran')
            ->values();

        $this->summary = [
            'totalPegawai' => $totalPegawai,
            'bahan' => $bahan,
            'hasil' => $hasil,
            'totalBahan' => $bahan->sum('jumlah'),
            'totalHasil' => $hasil->sum('jumlah'),
        ];
    }
}
