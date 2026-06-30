<?php

namespace App\Filament\Resources\Jurnal1sts\Pages;

use App\Filament\Resources\Jurnal1sts\Jurnal1stResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Services\Jurnal\Jurnal1ToJurnal2Service;

class ListJurnal1sts extends ListRecords
{
    protected static string $resource = Jurnal1stResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sinkronisasi ke Jurnal 2')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $total = app(Jurnal1ToJurnal2Service::class)->sync();

                    Notification::make()
                        ->title('Sinkronisasi Berhasil')
                        ->body("{$total} data berhasil dikirim ke Jurnal 2")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
