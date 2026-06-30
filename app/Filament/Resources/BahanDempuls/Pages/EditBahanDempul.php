<?php

namespace App\Filament\Resources\BahanDempuls\Pages;

use App\Filament\Resources\BahanDempuls\BahanDempulResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBahanDempul extends EditRecord
{
    protected static string $resource = BahanDempulResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
