<?php

namespace App\Filament\Resources\TurusanKayus\Pages;

use App\Filament\Resources\TurusanKayus\TurusanKayuResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewTurusanKayu extends ViewRecord
{
    protected static string $resource = TurusanKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(function () {
                    $record = $this->getRecord();
                    $user = Auth::user();
                    if ($user->hasRole('admin')) {
                        return true;
                    }
                    $nota = $record->notaKayu;
                    if ($nota && $nota->status !== 'Belum Diperiksa') {
                        return false;
                    }

                    return true;
                }),
        ];
    }
    public static function getRelations(): array
    {
        return [];
    }
}
