<?php

namespace App\Filament\Resources\JurnalUmums\Pages;

use App\Filament\Resources\JurnalUmums\JurnalUmumResource;
use App\Filament\Resources\JurnalUmums\Widgets\CreateJurnalWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Services\Jurnal\JurnalUmumToJurnal1Service;

class ListJurnalUmums extends ListRecords
{
    protected static string $resource = JurnalUmumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sinkronisasi')
                ->label('Sinkronisasi ke Jurnal 1')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn() => now()->isThursday())
                ->disabled(fn() => !now()->isThursday())
                ->action(function () {
                    $count = app(JurnalUmumToJurnal1Service::class)->sync();

                    Notification::make()
                        ->title('Sinkronisasi Selesai')
                        ->body("{$count} data berhasil disinkronkan ke Jurnal 1")
                        ->success()
                        ->send();
                }),
            CreateAction::make()
                ->label('Tambah Jurnal')
                ->modalHeading('Buat Jurnal Umum Baru')
                ->modalWidth('4xl') // Mengatur lebar popup agar form string akun terlihat jelas
                ->createAnother(false),

        ];
    }
}
