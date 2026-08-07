<?php

namespace App\Filament\Pages;

use App\Models\DetailHasilRepair;
use App\Models\StokVeneerKering as ModelStok;
use App\Models\JenisKayu;
use App\Models\ModalRepair;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class StokVeneerKering extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.stok-veneer-kering';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Stok Veneer Kering';
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title          = 'Stok Veneer Kering';
    protected static ?int    $navigationSort = 3;

    public string $filterJenisKayu = '';
    public string $filterCoreType  = '';

    public bool $showM3         = false;
    public bool $showHppAverage = false;
    public bool $showNilaiStok  = false;

    public function getLatestStokProperty()
    {
        // Ambil snapshot m3/hpp/nilai dari baris terakhir per kombinasi
        $latest = ModelStok::with(['ukuran', 'jenisKayu'])
            ->select('stok_veneer_kerings.*')
            ->join(DB::raw('(SELECT MAX(id) as max_id FROM stok_veneer_kerings GROUP BY id_ukuran, id_jenis_kayu, kw) as latest'), function ($join) {
                $join->on('stok_veneer_kerings.id', '=', 'latest.max_id');
            })
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when(
                $this->filterCoreType === 'long',
                fn($q) =>
                $q->whereHas(
                    'ukuran',
                    fn($u) =>
                    $u->where('panjang', 244)->where('lebar', 122)->where('tebal', '>', 1)
                )
            )
            ->when(
                $this->filterCoreType === 'short',
                fn($q) =>
                $q->whereHas(
                    'ukuran',
                    fn($u) =>
                    $u->where('panjang', 122)->where('lebar', 244)->where('tebal', '>', 1)
                )
            )
            ->where('stok_m3_sesudah', '<>', 0)
            ->get();

        // Hitung total lembar (masuk - keluar) per kombinasi lalu inject ke collection
        return $latest->map(function ($row) {
            $masuk = ModelStok::where('id_ukuran', $row->id_ukuran)
                ->where('id_jenis_kayu', $row->id_jenis_kayu)
                ->where('kw', $row->kw)
                ->where('jenis_transaksi', 'masuk')
                ->sum('qty');

            $keluar = ModelStok::where('id_ukuran', $row->id_ukuran)
                ->where('id_jenis_kayu', $row->id_jenis_kayu)
                ->where('kw', $row->kw)
                ->where('jenis_transaksi', 'keluar')
                ->sum('qty');

            $row->total_lembar = (int) ($masuk - $keluar);
            return $row;
        });
    }

    public function getGroupedStokProperty()
    {
        // Mengelompokkan berdasarkan tebal dari relasi ukuran
        return $this->latestStok->groupBy(fn($item) => (string) ($item->ukuran->tebal ?? '0'))->sortKeys();
    }

    public function getTotalM3Property(): float
    {
        return $this->latestStok->sum('stok_m3_sesudah');
    }

    public function getTotalNilaiStokProperty(): float
    {
        return $this->latestStok->sum('nilai_stok_sesudah');
    }

    public function getCoreTypeLabel($row): ?string
    {
        $tebal   = (float) ($row->ukuran->tebal ?? 0);
        $panjang = (float) ($row->ukuran->panjang ?? 0);
        $lebar   = (float) ($row->ukuran->lebar ?? 0);

        if ($tebal <= 1.0) {
            return null;
        }

        if ($panjang === 244.0 && $lebar === 122.0) {
            return 'Long Core';
        }

        if (
            ($panjang === 122.0 && $lebar === 244.0)
            || ($panjang === 122.0 && $lebar === 130.0)
        ) {
            return 'Short Core';
        }

        return null;
    }

    // Work In Progress
    private const SUMBER_REPAIR = 'veneer_kering';

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

    public function wipRepairUntuk($idJenisKayu, $panjang, $lebar, $tebal, $kw): float
    {
        $key = self::specKey($idJenisKayu, $panjang, $lebar, $tebal, $kw);

        return (float) ($this->wipRepairMap[$key] ?? 0);
    }

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

        $this->wipRepairCache = null;

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