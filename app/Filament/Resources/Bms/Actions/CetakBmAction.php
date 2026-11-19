<?php

namespace App\Filament\Resources\Bms\Actions;

use App\Models\Bm;
use Filament\Actions\Action;

class CetakBmAction
{
    public static function make(): Action
    {
        return Action::make('cetak_nota')->label('Cetak Nota')->icon('heroicon-o-printer')->color('info')
            ->url(fn (Bm $record) => route('bkbm.nota-bm.print', $record))->openUrlInNewTab()
            ->visible(fn (Bm $record) => $record->divalidasi_oleh !== null);
    }
}
