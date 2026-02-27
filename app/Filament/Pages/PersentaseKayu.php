<?php

namespace App\Filament\Pages;

use App\Models\NotaKayu;
use App\Services\ProduksiInflowService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class PersentaseKayu extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Persentase Kayu';

    protected string $view = 'filament.pages.persentase-kayu';

    public array $full_data = [];

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return 'Laporan';
    }
    protected function getViewData(): array
    {
        $service = new ProduksiInflowService();
        
        // Data diambil berdasarkan paginasi yang diproses di Service
        $dataLaporan = $service->getLaporanBatch();

        return [
            'laporan' => $dataLaporan,
        ];
    }
}
