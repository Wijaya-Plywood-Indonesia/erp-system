<?php

namespace App\Filament\Resources\PegawaiNyusups\Pages;

use App\Filament\Resources\PegawaiNyusups\PegawaiNyusupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiNyusup extends EditRecord
{
    protected static string $resource = PegawaiNyusupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
