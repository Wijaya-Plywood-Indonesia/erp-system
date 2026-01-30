<?php

namespace App\Filament\Widgets;

use App\Models\HargaKayu;
use Filament\Widgets\Widget;

class CardProduksiOverwiew extends Widget
{
    protected static bool $isDiscovered = true;

    protected string $view = 'filament.widgets.card-produksi-overwiew';
protected int | string | array $columnSpan = 'full';
protected function getViewData(): array
    {
        return [
            'total_kayu' => HargaKayu::count(),
            'total_aset' => HargaKayu::sum('harga_beli'),
            'grade_a'    => HargaKayu::where('grade', 1)->count(),
        ];
    }
}
