<?php

namespace App\Filament\Pages;

use App\Models\GradingSession;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class GradingPage extends Page
{
    use HasPageShield;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Grade';

    protected static ?string $navigationLabel = 'Konfirmasi Grade';

    protected static ?string $title = '';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.grading-page';
}
