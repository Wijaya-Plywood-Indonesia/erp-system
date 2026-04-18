<?php

namespace App\Filament\Pages;

use App\Models\HargaKayu as ModelsHargaKayu;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class HargaKayu extends Page
{
    protected string $view = 'filament.pages.harga-kayu';
    protected static ?string $navigationLabel = 'Table Harga Kayu';
    protected static string|UnitEnum|null $navigationGroup = 'Kayu';
    protected static ?string $title = 'Tabel Harga Kayu';

    public Collection $prices;

    public function mount(): void
    {
        // Mengambil semua data harga master beserta relasi jenis kayunya
        // Ditambahkan filter dasar agar data yang tidak punya relasi tidak merusak memory
        $this->prices = ModelsHargaKayu::with('jenisKayu')
            ->whereHas('jenisKayu')
            ->get();
    }

    /**
     * COMPUTED PROPERTY: matrixHeader
     * Mengelompokkan header berdasarkan Jenis Kayu -> Panjang -> Grade
     */
    public function getMatrixHeaderProperty(): Collection
    {
        return $this->prices
            // Pastikan hanya memproses data yang memiliki relasi jenis kayu yang valid dan ada namanya
            ->filter(fn($item) => optional($item->jenisKayu)->nama_kayu !== null)
            ->groupBy('jenisKayu.nama_kayu')
            ->map(function ($itemsByWood) {
                return $itemsByWood->groupBy('panjang')
                    ->map(function ($itemsByLength) {
                        return $itemsByLength->pluck('grade')->unique()->sort();
                    })->sortKeysDesc(); // Mengurutkan 260 lalu 130
            });
    }

    /**
     * COMPUTED PROPERTY: diameterRanges
     * Mengambil rentang diameter unik yang terdaftar di database
     */
    public function getDiameterRangesProperty(): Collection
    {
        return ModelsHargaKayu::query()
            ->whereHas('jenisKayu') // Hanya ambil range dari data yang punya jenis kayu valid
            ->select('diameter_terkecil as min', 'diameter_terbesar as max')
            ->distinct()
            ->orderBy('min')
            ->get();
    }

    /**
     * HELPER: Mencari harga di matriks
     */
    public function getPriceMatrix($woodName, $length, $grade, $minD, $maxD)
    {
        $match = $this->prices->where('jenisKayu.nama_kayu', $woodName)
            ->where('panjang', (int) $length)
            ->where('grade', (int) $grade)
            ->where('diameter_terkecil', (int) $minD)
            ->where('diameter_terbesar', (int) $maxD)
            ->first();

        return $match ? number_format($match->harga_beli, 0, ',', '.') : '';
    }
}
