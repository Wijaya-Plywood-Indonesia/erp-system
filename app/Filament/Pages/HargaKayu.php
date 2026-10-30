<?php

namespace App\Filament\Pages;

use App\Models\HargaKayu as ModelsHargaKayu;
use App\Models\HargaKayuLog;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class HargaKayu extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.harga-kayu';

    protected static ?string $navigationLabel = 'Table Harga Kayu';

    protected static string|UnitEnum|null $navigationGroup = 'Kayu';

    protected static ?string $title = 'Tabel Harga Kayu';

    public Collection $prices;

    public ?string $filterDate = null;

    public function mount(): void
    {
        $this->filterDate = now()->toDateString();
        $this->loadPrices();
    }

    public function updatedFilterDate(): void
    {
        $this->loadPrices();
    }

    public function loadPrices(): void
    {
        if ($this->filterDate) {
            $endOfDay = $this->filterDate.' 23:59:59';

            // Ambil semua master harga yang sudah ada pada tanggal filter
            $basePrices = ModelsHargaKayu::with('jenisKayu')
                ->whereHas('jenisKayu')
                ->where('created_at', '<=', $endOfDay)
                ->get();

            $priceIds = $basePrices->pluck('id');

            // Ambil log tertua SETELAH tanggal filter per id_harga_kayu
            // harga_lama di log itu = harga yang aktif PADA tanggal filter
            $logs = HargaKayuLog::whereIn('id_harga_kayu', $priceIds)
                ->where('aksi', 'Persetujuan Harga')
                ->where('created_at', '>', $endOfDay)
                ->orderBy('created_at', 'asc')
                ->get()
                ->groupBy('id_harga_kayu');

            foreach ($basePrices as $price) {
                if (isset($logs[$price->id])) {
                    // Log tertua setelah tanggal = harga_lama adalah harga aktif saat itu
                    $price->harga_beli = $logs[$price->id]->first()->harga_lama;
                }
                // Tidak ada log setelah tanggal = harga_beli master sudah benar (belum pernah berubah)
            }

            $this->prices = $basePrices;
        } else {
            // Tanpa filter = harga aktif sekarang
            $this->prices = ModelsHargaKayu::with('jenisKayu')
                ->whereHas('jenisKayu')
                ->get();
        }
    }

    public function getMatrixHeaderProperty(): Collection
    {
        return $this->prices
            ->filter(fn ($item) => optional($item->jenisKayu)->nama_kayu !== null)
            ->groupBy('jenisKayu.nama_kayu')
            // Urutan: Sengon, Jabon, baru kayu lain (Mahoni, Waru, dll) di samping
            ->sortBy(fn ($items, $name) => [self::urutanKayu($name), strtolower($name)])
            ->map(function ($itemsByWood) {
                return $itemsByWood->groupBy('panjang')
                    ->map(function ($itemsByLength) {
                        return $itemsByLength->pluck('grade')->unique()->sort();
                    })->sortKeysDesc();
            });
    }

    /**
     * Urutan kolom kayu: Sengon (0), Jabon (1), kayu lain (2).
     */
    public static function urutanKayu(string $nama): int
    {
        $nama = strtolower(trim($nama));

        return match (true) {
            str_contains($nama, 'sengon') => 0,
            str_contains($nama, 'jabon') => 1,
            default => 2,
        };
    }

    public static function kayuLain(string $nama): bool
    {
        return self::urutanKayu($nama) === 2;
    }

    /**
     * Tanggal harga kayu terakhir diperbarui (keseluruhan), yaitu
     * persetujuan harga terakhir. Mengikuti filter tanggal bila dipilih.
     */
    public function getLastUpdatedAtProperty(): ?\Illuminate\Support\Carbon
    {
        $query = HargaKayuLog::where('aksi', 'Persetujuan Harga');

        if ($this->filterDate) {
            $query->where('created_at', '<=', $this->filterDate.' 23:59:59');
        }

        $last = $query->max('created_at');

        return $last ? \Illuminate\Support\Carbon::parse($last) : null;
    }

    public function getDiameterRangesProperty(): Collection
    {
        $query = ModelsHargaKayu::query()
            ->whereHas('jenisKayu');

        if ($this->filterDate) {
            $query->where('created_at', '<=', $this->filterDate.' 23:59:59');
        }

        return $query
            ->select('diameter_terkecil as min', 'diameter_terbesar as max')
            ->distinct()
            ->orderBy('min')
            ->get();
    }

    public function getPriceMatrix($woodName, $length, $grade, $minD, $maxD)
    {
        $match = $this->prices
            ->where('jenisKayu.nama_kayu', $woodName)
            ->where('panjang', (int) $length)
            ->where('grade', (int) $grade)
            ->filter(fn ($item) => (float) $item->diameter_terkecil === (float) $minD &&
                (float) $item->diameter_terbesar === (float) $maxD
            )
            ->first();

        return $match ? number_format($match->harga_beli, 0, ',', '.') : '';
    }
}
