<?php

namespace App\Filament\Resources\DetailMasukStiks\Pages;

use App\Filament\Resources\DetailMasukStiks\DetailMasukStikResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailMasukStik extends EditRecord
{
    protected static string $resource = DetailMasukStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
