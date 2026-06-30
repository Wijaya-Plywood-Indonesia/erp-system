<?php

namespace App\Filament\Resources\RencanaPegawaiDempuls\Pages;

use App\Filament\Resources\RencanaPegawaiDempuls\RencanaPegawaiDempulResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRencanaPegawaiDempul extends EditRecord
{
    protected static string $resource = RencanaPegawaiDempulResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
