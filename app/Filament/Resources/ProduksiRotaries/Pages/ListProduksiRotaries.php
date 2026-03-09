<?php

namespace App\Filament\Resources\ProduksiRotaries\Pages;

use App\Filament\Resources\ProduksiRotaries\ProduksiRotaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Filament\Forms\Components\DatePicker;
use App\Services\Akuntansi\RotaryJurnalService;
use Filament\Actions\Action;
use Illuminate\Http\Client\Response;

class ListProduksiRotaries extends ListRecords
{
    protected static string $resource = ProduksiRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('test_kirim_jurnal')
                ->label('🧪 Test Kirim Jurnal')
                ->color('warning')
                ->form([
                    DatePicker::make('tanggal')
                        ->label('Tanggal Produksi')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                ])
                ->action(function (array $data) {
                    $tanggal = $data['tanggal'];
                    $service = app(RotaryJurnalService::class);
                    $payload = $service->buildJurnalPayload($tanggal);

                    if (!$payload) {
                        Notification::make()
                            ->title('Jurnal belum bisa dibuat')
                            ->body('Masih ada mesin yang belum divalidasi pada tanggal tersebut.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $webhookUrl = config('services.webhook_test.url');

                    /** @var Response $response */
                    $response = Http::timeout(10)
                    ->withoutVerifying()
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->post($webhookUrl, $payload);

                    if ($response->successful()) {
                        Notification::make()
                            ->title('Berhasil dikirim ke webhook!')
                            ->body('Cek payload di webhook.site Anda.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Gagal kirim ke webhook')
                            ->body('Status: ' . $response->status())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
