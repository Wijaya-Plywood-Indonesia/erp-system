<?php

namespace App\Filament\Resources\Penjualans\Pages;

use App\Filament\Resources\Penjualans\PenjualanResource;
use App\Services\Akuntansi\CustomerSyncService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPenjualan extends EditRecord
{
    protected static string $resource = PenjualanResource::class;

    protected function afterSave(): void
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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
