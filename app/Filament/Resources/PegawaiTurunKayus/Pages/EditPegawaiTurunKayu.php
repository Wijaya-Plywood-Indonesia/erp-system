<?php

namespace App\Filament\Resources\PegawaiTurunKayus\Pages;

use App\Filament\Resources\PegawaiTurunKayus\PegawaiTurunKayuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiTurunKayu extends EditRecord
{
    protected static string $resource = PegawaiTurunKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
