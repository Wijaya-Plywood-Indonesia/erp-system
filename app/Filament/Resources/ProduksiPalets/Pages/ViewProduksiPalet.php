<?php

namespace App\Filament\Resources\ProduksiPalets\Pages;

use App\Filament\Resources\ProduksiPalets\ProduksiPaletResource;
use App\Services\ValidasiProduksiPaletService;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiPalet extends ViewRecord
{
    protected static string $resource = ProduksiPaletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->hidden(function () {
                    $record = $this->getRecord();

                    // Sembunyikan tombol Edit jika dokumen dalam kondisi terkunci (isLocked)
                    // Super Admin akan tetap mengembalikan false (tidak disembunyikan)
                    return $record ? ValidasiProduksiPaletService::isLocked($record) : false;
                }),
        ];
    }
}
