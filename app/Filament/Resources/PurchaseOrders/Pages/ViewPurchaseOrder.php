<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    #[On('po-items-updated')]
    public function refreshRecordData(): void
    {
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Edit Data PO')->color('warning'),
        ];
    }
}