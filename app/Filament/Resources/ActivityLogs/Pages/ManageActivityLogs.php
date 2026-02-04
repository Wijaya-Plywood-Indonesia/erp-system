<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageActivityLogs extends ManageRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
