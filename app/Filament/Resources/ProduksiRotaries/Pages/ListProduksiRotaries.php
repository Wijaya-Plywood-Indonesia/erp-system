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

                $url    = rtrim(config('services.akuntansi.url'), '/') . '/api/jurnal/rotary/create';
                $apiKey = config('services.akuntansi.key');

                /** @var Response $response */
                $response = Http::timeout(30)
                    ->withoutVerifying()
                    ->withHeaders([
                        'X-API-KEY' => $apiKey,
                        'Accept'    => 'application/json',
                    ])
                    ->post($url, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    Notification::make()
                        ->title('✅ Jurnal berhasil dikirim ke Akuntansi!')
                        ->body(
                            'No. Jurnal: ' . ($data['data']['no_jurnal'] ?? '-') . ' | ' .
                            'Jurnal #' . ($data['data']['jurnal'] ?? '-') . ' | ' .
                            ($data['data']['jumlah_header'] ?? 0) . ' header, ' .
                            ($data['data']['jumlah_items'] ?? 0) . ' items'
                        )
                        ->success()
                        ->send();

                } elseif ($response->status() === 409) {
                    Notification::make()
                        ->title('⚠️ Jurnal sudah pernah dibuat')
                        ->body('No. jurnal ' . ($payload['jurnal_header']['no_jurnal'] ?? '') . ' sudah ada di akuntansi.')
                        ->warning()
                        ->send();

                } else {
                    Notification::make()
                        ->title('❌ Gagal kirim ke Akuntansi')
                        ->body('Status: ' . $response->status() . ' — ' . $response->body())
                        ->danger()
                        ->send();
                }
            }),
        ];
    }
}
