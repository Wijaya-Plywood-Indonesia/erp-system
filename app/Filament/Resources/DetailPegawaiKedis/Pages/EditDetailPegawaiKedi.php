<?php

namespace App\Filament\Resources\DetailPegawaiKedis\Pages;

use App\Filament\Resources\DetailPegawaiKedis\DetailPegawaiKediResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailPegawaiKedi extends EditRecord
{
    protected static string $resource = DetailPegawaiKediResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
