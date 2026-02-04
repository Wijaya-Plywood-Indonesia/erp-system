<?php

namespace App\Filament\Resources\JurnalUmums\Pages;

use App\Filament\Resources\JurnalUmums\JurnalUmumResource;
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
                ->label('Sinkronisasi')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $count = app(JurnalUmumToJurnal1Service::class)->sync();

                    Notification::make()
                        ->title('Sinkronisasi Selesai')
                        ->body("{$count} data berhasil disinkronkan ke Jurnal 1")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
