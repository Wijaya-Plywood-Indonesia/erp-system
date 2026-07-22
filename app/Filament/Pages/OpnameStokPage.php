<?php

namespace App\Filament\Pages;
use UnitEnum;
use Filament\Pages\Page;

class OpnameStokPage extends Page
{
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