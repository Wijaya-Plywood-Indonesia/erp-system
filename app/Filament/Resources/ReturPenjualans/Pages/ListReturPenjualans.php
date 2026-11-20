<?php

namespace App\Filament\Resources\ReturPenjualans\Pages;

use App\Filament\Resources\ReturPenjualans\ReturPenjualanResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListReturPenjualans extends ListRecords
{
    protected static string $resource = ReturPenjualanResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('formRetur')
                ->label('Buat Retur Penjualan')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('primary')
                ->url(fn (): string => FormReturPenjualan::getUrl()),
        ];
    }
}
