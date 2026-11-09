<?php

namespace App\Filament\Resources\Bks\Pages;

use App\Filament\Resources\Bks\BkResource;
use Filament\Resources\Pages\EditRecord;

class EditBk extends EditRecord
{
    protected static string $resource = BkResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        abort_if($this->record->terkunci(), 403, 'Nota yang sudah divalidasi tidak bisa diubah.');
    }
}
