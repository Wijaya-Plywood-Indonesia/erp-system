<?php

namespace App\Filament\Resources\Bms\Pages;

use App\Filament\Resources\Bms\BmResource;
use Filament\Resources\Pages\EditRecord;

class EditBm extends EditRecord
{
    protected static string $resource = BmResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        abort_if($this->record->terkunci(), 403, 'Nota yang sudah divalidasi tidak bisa diubah.');
    }
}
