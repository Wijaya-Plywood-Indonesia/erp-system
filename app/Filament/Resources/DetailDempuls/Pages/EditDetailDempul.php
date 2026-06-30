<?php

namespace App\Filament\Resources\DetailDempuls\Pages;

use App\Filament\Resources\DetailDempuls\DetailDempulResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailDempul extends EditRecord
{
    protected static string $resource = DetailDempulResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
