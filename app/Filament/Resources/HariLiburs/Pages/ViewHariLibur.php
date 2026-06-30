<?php

namespace App\Filament\Resources\HariLiburs\Pages;

use App\Filament\Resources\HariLiburs\HariLiburResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHariLibur extends ViewRecord
{
    protected static string $resource = HariLiburResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
