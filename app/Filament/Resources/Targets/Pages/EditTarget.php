<?php

namespace App\Filament\Resources\Targets\Pages;

use App\Filament\Resources\Targets\TargetResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTarget extends EditRecord
{
    protected static string $resource = TargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
