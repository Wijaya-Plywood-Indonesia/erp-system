<?php

namespace App\Filament\Resources\JenisKayus\Pages;

use App\Filament\Resources\JenisKayus\JenisKayuResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditJenisKayu extends EditRecord
{
    protected static string $resource = JenisKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
