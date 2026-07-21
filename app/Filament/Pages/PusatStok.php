<?php

namespace App\Filament\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class PusatStok extends Page
{
    use HasPageShield;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Pusat Stok';
    protected string $view = 'filament.pages.pusat-stok';
}
