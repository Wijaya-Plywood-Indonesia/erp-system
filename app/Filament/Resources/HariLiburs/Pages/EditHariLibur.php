<?php

namespace App\Filament\Resources\HariLiburs\Pages;

use App\Filament\Resources\HariLiburs\HariLiburResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHariLibur extends EditRecord
{
    protected static string $resource = HariLiburResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
