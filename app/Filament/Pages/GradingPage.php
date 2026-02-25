<?php

namespace App\Filament\Pages;

use App\Models\GradingSession;
use BackedEnum;
use Filament\Pages\Page;

class GradingPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Konfirmasi Grade';

    protected static ?string $title = '';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.grading-page';

    public static function getNavigationBadge(): ?string
    {
        $count = GradingSession::inProgress()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
