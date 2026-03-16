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
                    $record = $this->getRecord(); // Mengambil data yang sedang dibuka
                    $user = Auth::user();

                    // 1. Jika Admin, tombol edit SELALU muncul
                    if ($user->hasRole('admin')) {
                        return true;
                    }

                    // 2. Cek status Nota melalui relasi
                    $nota = $record->notaKayu;

                    // Jika nota sudah ada dan statusnya bukan 'Belum Diperiksa', sembunyikan tombol
                    if ($nota && $nota->status !== 'Belum Diperiksa') {
                        return false;
                    }

                    return true;
                }),
        ];
    }
}
