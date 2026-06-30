<?php

namespace App\Filament\Resources\NotaBarangMasuks\Pages;

use App\Filament\Resources\NotaBarangMasuks\NotaBarangMasukResource;
use App\Services\NomorNotaService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateNotaBarangMasuk extends CreateRecord
{
    protected static string $resource = NotaBarangMasukResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['tipe_nota']); // buang karena tidak ada di tabel

        return $data;
    }
}
