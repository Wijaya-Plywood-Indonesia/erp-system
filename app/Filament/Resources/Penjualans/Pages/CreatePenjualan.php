<?php

namespace App\Filament\Resources\Penjualans\Pages;

use App\Filament\Resources\Penjualans\PenjualanResource;
use App\Services\Akuntansi\CustomerSyncService;
use Filament\Resources\Pages\CreateRecord;

class CreatePenjualan extends CreateRecord
{
    protected static string $resource = PenjualanResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        if (!empty($record->tujuan_nota)) {
            app(CustomerSyncService::class)->syncCustomer(
                $record->tujuan_nota,
                $record->alamat,
                $record->pembeli_bkp_npwp
            );
        }
    }
}
