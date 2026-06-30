<?php

namespace App\Filament\Resources\DetailLainLains\Pages;

use App\Filament\Resources\DetailLainLains\DetailLainLainResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailLainLain extends EditRecord
{
    protected static string $resource = DetailLainLainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
