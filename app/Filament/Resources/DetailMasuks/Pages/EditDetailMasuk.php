<?php

namespace App\Filament\Resources\DetailMasuks\Pages;

use App\Filament\Resources\DetailMasuks\DetailMasukResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailMasuk extends EditRecord
{
    protected static string $resource = DetailMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['no_palet_select']);
        unset($data['af_preview']);
        $data['no_palet'] = (int) ($data['no_palet'] ?? 0);
        return $data;
    }
}
