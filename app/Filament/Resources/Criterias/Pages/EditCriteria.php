<?php

namespace App\Filament\Resources\Criterias\Pages;

use App\Filament\Resources\Criterias\CriteriaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCriteria extends EditRecord
{
    protected static string $resource = CriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
