<?php

namespace App\Filament\Resources\Bms\Actions;

use App\Filament\Resources\Bks\BkResource;
use App\Models\Bk;
use App\Models\Bm;
use App\Services\BkBm\BkBmService;
use App\Services\BkBm\NomorBkBmService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/** Tombol "Kembalikan" pada BMK (barang pinjaman diterima). Membuat BKR; barangnya diisi manual. */
class KembalikanBmkAction
{
    public static function make(): Action
    {
        return Action::make('kembalikan')
            ->label('Kembalikan')
            ->color('info')
            ->icon('heroicon-o-arrow-uturn-left')
            ->visible(fn (Bm $record) => $record->kategori === 'bmk'
                && $record->status === 'divalidasi'
                && ! $record->bkrPengembalian()->exists())
            ->schema([
                DatePicker::make('tanggal')->label('Tanggal dikembalikan')->default(today())->required(),
                TextInput::make('no_nota')->label('No. BKR')->required()->maxLength(255)->extraInputAttributes(['style' => 'text-transform:uppercase'])
                    ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                    ->default(fn () => NomorBkBmService::saran('bkr', today()))
                    ->helperText('Saran otomatis, boleh diubah.')
                    ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                        if (Bk::where('no_nota', strtoupper(trim((string) $value)))->exists()) {
                            $fail('Nomor nota ini sudah terdaftar.');
                        }
                    }]),
                Textarea::make('keterangan')->rows(2),
            ])
            ->action(function (array $data, Bm $record) {
                try {
                    $bkr = BkBmService::buatBkr($record, $data['tanggal'], $data['keterangan'] ?? null, $data['no_nota'] ?? null);
                    Notification::make()->title("BKR {$bkr->no_nota} dibuat, silakan isi barang yang dikembalikan")->success()->send();
                    return redirect(BkResource::getUrl('view', ['record' => $bkr]));
                } catch (\Throwable $e) {
                    Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                }
            });
    }
}
