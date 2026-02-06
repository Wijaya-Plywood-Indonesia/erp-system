<?php

namespace App\Filament\Resources\Jurnal2s\Pages;

use App\Filament\Resources\Jurnal2s\Jurnal2Resource;
use App\Services\Jurnal\Jurnal2ToJurnal3Service;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Services\Jurnal\JurnalDuaToJurnalTigaService;

class ListJurnal2s extends ListRecords
{
    protected static string $resource = Jurnal2Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncJurnal')
            ->label('Sinkronisasi ke Jurnal 3')
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->requiresConfirmation()
            ->action(function () {

                $jumlah = app(Jurnal2ToJurnal3Service::class)->sync();

                Notification::make()
                    ->title('Sinkronisasi Berhasil')
                    ->body("{$jumlah} data dikirim ke Jurnal 3")
                    ->success()
                    ->send();
            }),
            CreateAction::make(),
            
        ];
    }
}
