<?php

namespace App\Filament\Resources\PegawaiSandings\Pages;

use App\Filament\Resources\PegawaiSandings\PegawaiSandingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiSanding extends EditRecord
{
    protected static string $resource = PegawaiSandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
