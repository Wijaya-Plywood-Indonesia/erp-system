<?php

namespace App\Filament\Resources\Bms\Pages;

use App\Filament\Resources\Bms\BmResource;
use App\Services\BkBm\BkBmService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBm extends CreateRecord
{
    protected static string $resource = BmResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BkBmService::buatBm($data, 'bm');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
