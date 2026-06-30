<?php

namespace App\Filament\Resources\DetailBongkarKedis\Pages;

use App\Filament\Resources\DetailBongkarKedis\DetailBongkarKediResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailBongkarKedi extends EditRecord
{
    protected static string $resource = DetailBongkarKediResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
