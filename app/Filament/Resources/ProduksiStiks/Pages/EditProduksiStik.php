<?php

namespace App\Filament\Resources\ProduksiStiks\Pages;

use App\Filament\Resources\ProduksiStiks\ProduksiStikResource;
use App\Filament\Resources\ProduksiStiks\Widgets\ProduksiStikSummaryWidget;
use App\Models\ProduksiStik;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditProduksiStik extends EditRecord
{
    protected static string $resource = ProduksiStikResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiStikSummaryWidget::class,
        ];
    }

    protected function beforeSave(): void
    {
        $tanggal = $this->data['tanggal_produksi'] ?? null;
        $recordId = $this->getRecord()->id;

        if ($tanggal) {
            $exists = ProduksiStik::whereDate('tanggal_produksi', $tanggal)
                ->where('id', '!=', $recordId)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'data.tanggal_produksi' => 'Gagal mengubah! Tanggal ini sudah digunakan di laporan lain.',
                ]);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}