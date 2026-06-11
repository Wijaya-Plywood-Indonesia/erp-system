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
        $data['no_nota'] = NomorNotaService::generateBarangMasuk(
            tipe: $data['tipe_nota'],
            tanggal: Carbon::parse($data['tanggal']),
        );

        return $data;
    }
}
