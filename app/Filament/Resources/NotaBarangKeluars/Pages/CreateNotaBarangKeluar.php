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
        $tanggal = Carbon::parse($data['tanggal']);

        $data['no_nota'] = NomorNotaService::generateBarangKeluar(
            tipe: $data['tipe_nota'],
            tanggal: $tanggal,
        );

        return $data;
    }
}
