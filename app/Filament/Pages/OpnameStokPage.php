<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;
use Filament\Pages\Page;

class OpnameStokPage extends Page
{
    use HasPageShield;
    protected static ?string $navigationLabel = 'Opname Stok';
    protected static string|UnitEnum|null $navigationGroup = 'Opname';
    protected string $view = 'filament.pages.opname-stok-page';

    public function getTitle(): string
    {
        return 'Stock Opname';
    }

    public function getMaxContentWidth(): string
    {
        return 'full';
    }
}
