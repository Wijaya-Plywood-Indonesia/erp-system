<?php

namespace App\Filament\Resources\PengajuanBarangs\Pages;

use App\Filament\Resources\PengajuanBarangs\PengajuanBarangResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPengajuanBarang extends EditRecord
{
    protected static string $resource = PengajuanBarangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
