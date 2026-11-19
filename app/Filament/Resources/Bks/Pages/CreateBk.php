<?php

namespace App\Filament\Resources\Bks\Pages;

use App\Filament\Resources\Bks\BkResource;
use App\Services\BkBm\BkBmService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBk extends CreateRecord
{
    protected static string $resource = BkResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $kategori = $data['kategori'];
        unset($data['kategori']);
        return BkBmService::buatBk($data, $kategori);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
