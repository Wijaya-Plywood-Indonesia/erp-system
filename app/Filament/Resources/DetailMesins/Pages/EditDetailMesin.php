<?php

namespace App\Filament\Resources\DetailMesins\Pages;

use App\Filament\Resources\DetailMesins\DetailMesinResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailMesin extends EditRecord
{
    protected static string $resource = DetailMesinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
