<?php

namespace App\Filament\Resources\DetailTurunKayus\Pages;

use App\Filament\Resources\DetailTurunKayus\DetailTurunKayuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailTurunKayu extends EditRecord
{
    protected static string $resource = DetailTurunKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
