<?php

namespace App\Filament\Resources\NotaBarangKeluars\Pages;

use App\Filament\Resources\NotaBarangKeluars\NotaBarangKeluarResource;
use App\Services\NomorNotaService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateNotaBarangKeluar extends CreateRecord
{
    protected static string $resource = NotaBarangKeluarResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['tipe_nota']); // buang karena tidak ada di tabel

        return $data;
    }
}
