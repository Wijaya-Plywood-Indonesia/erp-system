<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class ProduksiPilihPlywoodSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-pilih-plywood.widgets.hasil-pilih-plywood-summary';

    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';
}