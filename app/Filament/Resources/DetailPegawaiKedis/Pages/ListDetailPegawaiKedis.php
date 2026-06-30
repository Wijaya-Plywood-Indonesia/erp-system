<?php

namespace App\Filament\Resources\DetailPegawaiKedis\Pages;

use App\Filament\Resources\DetailPegawaiKedis\DetailPegawaiKediResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailPegawaiKedis extends ListRecords
{
    protected static string $resource = DetailPegawaiKediResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
