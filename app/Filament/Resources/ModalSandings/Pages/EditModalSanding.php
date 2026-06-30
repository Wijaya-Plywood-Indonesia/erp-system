<?php

namespace App\Filament\Resources\ModalSandings\Pages;

use App\Filament\Resources\ModalSandings\ModalSandingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModalSanding extends EditRecord
{
    protected static string $resource = ModalSandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
