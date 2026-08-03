<?php

namespace App\Filament\Pages;

use App\Models\DetailHasilRepair;
use App\Models\StokVeneerJadi as StokVeneerJadiModel;
use App\Models\JenisKayu;
use App\Models\ModalRepair;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class StokVeneerJadi extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.stok-veneer-jadi';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Stok Veneer Jadi';
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title          = 'Stok Veneer Jadi';
    protected static ?int    $navigationSort = 4;

    // State untuk filtering di UI Blade
    public string $filterJenisKayu = '';
    public string $filterTebal     = '';
    public string $filterKw        = '';
    public string $filterCoreType  = '';

    public bool $showKubikasi   = false;
    public bool $showHppAverage = false;
    public bool $showNilaiStok  = false;

    public function getSummariesProperty()
    {
        return StokVeneerJadiModel::with(['jenisKayu', 'lastLog'])
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal',     $this->filterTebal))
            ->when($this->filterKw,        fn($q) => $q->where('kw_grade', $this->filterKw))
            ->when(
                $this->filterCoreType === 'long',
                fn($q) =>
                $q->where('panjang', 244)->where('lebar', 122)->where('tebal', '>', 1)
            )
            ->when(
                $this->filterCoreType === 'short',
                fn($q) =>
                $q->where('panjang', 122)->where('lebar', 244)->where('tebal', '>', 1)
            )
            ->where('stok_lembar', '>', 0)
            ->get();
    }

    public function getGroupedSummariesProperty()
    {
        return $this->summaries->groupBy('tebal')->sortKeys();
    }

    public function getKwListProperty()
    {
        return StokVeneerJadiModel::where('stok_lembar', '>', 0)->distinct()->pluck('kw_grade');
    }

    public function getTebalListProperty()
    {
        return StokVeneerJadiModel::where('stok_lembar', '>', 0)->distinct()->pluck('tebal');
    }

    public function getTotalNilaiStokProperty(): float
    {
        return (float) StokVeneerJadiModel::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->sum('nilai_stok');
    }

    public function getTotalLembarProperty(): int
    {
        return (int) StokVeneerJadiModel::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->sum('stok_lembar');
    }

    public function getCoreTypeLabel($row): ?string
    {
        if ((float) $row->tebal <= 1.0) {
            return null;
        }

        if ((float) $row->panjang === 244.0 && (float) $row->lebar === 122.0) {
            return 'Long Core';
        }

        if (
            ((float) $row->panjang === 122.0 && (float) $row->lebar === 244.0)
            || ((float) $row->panjang === 122.0 && (float) $row->lebar === 130.0)
        ) {
            return 'Short Core';
        }

        return null;
    }

    // Work in Progress
    // ─── WIP REPAIR PER SPESIFIKASI (badge per baris, gaya sama seperti StokTriplekJadi) ───

    private const SUMBER_REPAIR = 'veneer_jadi';

    public static function specKey($idJenisKayu, $panjang, $lebar, $tebal, $kw): string
    {
        return implode('|', [
            (int) $idJenisKayu,
            (float) $panjang,
            (float) $lebar,
            (float) $tebal,
            (string) $kw,
        ]);
    }

    protected ?array $wipRepairCache = null;

    public function getWipRepairMapProperty(): array
    {
        if ($this->wipRepairCache !== null) {
            return $this->wipRepairCache;
        }

        $map = [];

        ModalRepair::with(['ukuran', 'jenisKayu'])
            ->withSum('detailHasilRepairs as total_terpakai', 'jumlah')
            ->where('sumber', self::SUMBER_REPAIR)
            ->whereNull('ditutup_manual_at')
            ->get()
            ->each(function ($m) use (&$map) {
                $sisa = (float) $m->jumlah - (float) ($m->total_terpakai ?? 0);

                if ($sisa <= 0 || ! $m->ukuran) {
                    return;
                }

                $key = self::specKey($m->id_jenis_kayu, $m->ukuran->panjang, $m->ukuran->lebar, $m->ukuran->tebal, $m->kw);
                $map[$key] = ($map[$key] ?? 0) + $sisa;
            });

        return $this->wipRepairCache = $map;
    }

    /**
     * WIP repair untuk satu baris stok (dipanggil dari Blade per baris).
     */
    public function wipRepairUntuk($idJenisKayu, $panjang, $lebar, $tebal, $kw): float
    {
        $key = self::specKey($idJenisKayu, $panjang, $lebar, $tebal, $kw);

        return (float) ($this->wipRepairMap[$key] ?? 0);
    }

    /**
     * Tombol "Selesaikan WIP" — menutup semua ModalRepair yang cocok dengan
     * spesifikasi ini (sumber veneer_kering, belum ditutup). Sisa yang belum
     * terpakai dianggap susut tercatat.
     */
    public function selesaikanWipRepair($idJenisKayu, $panjang, $lebar, $tebal, $kw): void
    {
        $targetKey = self::specKey($idJenisKayu, $panjang, $lebar, $tebal, $kw);

        ModalRepair::with('ukuran')
            ->where('sumber', self::SUMBER_REPAIR)
            ->whereNull('ditutup_manual_at')
            ->get()
            ->filter(function ($m) use ($targetKey) {
                if (! $m->ukuran) {
                    return false;
                }

                return self::specKey($m->id_jenis_kayu, $m->ukuran->panjang, $m->ukuran->lebar, $m->ukuran->tebal, $m->kw) === $targetKey;
            })
            ->each(function ($m) {
                $m->update([
                    'ditutup_manual_at' => now(),
                    'ditutup_oleh'      => auth()->id(),
                ]);
            });

        $this->wipRepairCache = null; // paksa hitung ulang

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('WIP repair diselesaikan')
            ->body('Sisa WIP untuk spesifikasi ini ditandai selesai (susut tercatat).')
            ->send();
    }

    public function getHasilTanpaModalProperty()
    {
        return DetailHasilRepair::whereNull('id_modal_repair')
            ->with(['ukuran', 'jenisKayu'])
            ->latest()
            ->get();
    }
}
