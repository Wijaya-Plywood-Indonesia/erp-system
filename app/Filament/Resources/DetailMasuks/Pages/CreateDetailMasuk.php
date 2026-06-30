<?php

namespace App\Filament\Resources\DetailMasuks\Pages;

use App\Filament\Resources\DetailMasuks\DetailMasukResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDetailMasuk extends CreateRecord
{
    protected static string $resource = DetailMasukResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ✅ Log SEBELUM diproses
        \Illuminate\Support\Facades\Log::info('BEFORE mutate', $data);

        unset($data['no_palet_select']);
        unset($data['af_preview']);
        $data['no_palet'] = (int) ($data['no_palet'] ?? 0);

        // ✅ Log SESUDAH diproses
        \Illuminate\Support\Facades\Log::info('AFTER mutate', $data);

        return $data;
    }
}
