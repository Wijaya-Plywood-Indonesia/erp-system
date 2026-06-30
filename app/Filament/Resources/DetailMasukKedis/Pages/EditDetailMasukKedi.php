<?php

namespace App\Filament\Resources\DetailMasukKedis\Pages;

use App\Filament\Resources\DetailMasukKedis\DetailMasukKediResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailMasukKedi extends EditRecord
{
    protected static string $resource = DetailMasukKediResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
