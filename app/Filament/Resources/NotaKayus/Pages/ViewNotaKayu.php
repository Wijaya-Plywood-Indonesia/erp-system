<?php

namespace App\Filament\Resources\NotaKayus\Pages;

use App\Filament\Resources\NotaKayus\NotaKayuResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewNotaKayu extends ViewRecord
{
    protected static string $resource = NotaKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(function () {
                    $record = $this->getRecord();
                    $user = Auth::user();

                    // 1. AKSES SPESIAL (Admin/Super Admin): Selalu bisa edit untuk revisi darurat
                    if ($user->hasRole(['admin', 'super_admin'])) {
                        return true;
                    }

                    // 2. AKSES PETUGAS (Non-Admin):
                    // Jika status sudah "Sudah Diperiksa", kunci akses edit (tombol hilang)
                    if (str_contains($record->status ?? '', 'Sudah Diperiksa')) {
                        return false;
                    }
                }),
        ];
    }
}
