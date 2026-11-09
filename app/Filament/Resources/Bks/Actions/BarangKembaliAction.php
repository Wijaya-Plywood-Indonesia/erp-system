<?php

namespace App\Filament\Resources\Bks\Actions;

use App\Filament\Resources\Bms\BmResource;
use App\Models\Bk;
use App\Models\Bm;
use App\Services\BkBm\BkBmService;
use App\Services\BkBm\NomorBkBmService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/** Tombol "Barang Kembali" untuk BKL. Dipakai di halaman View dan di daftar BK. */
class BarangKembaliAction
{
    public static function make(): Action
    {
        return Action::make('barang_kembali')
            ->label('Barang Kembali')
            ->color('info')
            ->icon('heroicon-o-arrow-uturn-left')
            ->visible(fn (Bk $record) => $record->kategori === 'bkl'
                && $record->status === 'divalidasi'
                && ! $record->bmTerkait()->where('kategori', 'bml')->exists())
            ->schema([
                DatePicker::make('tanggal')->label('Tanggal kembali')->default(today())->required(),
                TextInput::make('no_nota')->label('No. BML')->required()->maxLength(255)->extraInputAttributes(['style' => 'text-transform:uppercase'])
                    ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                    ->default(fn () => NomorBkBmService::saran('bml', today()))
                    ->helperText('Saran otomatis, boleh diubah.')
                    ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                        if (Bm::where('no_nota', trim((string) $value))->exists()) {
                            $fail('Nomor nota ini sudah terdaftar.');
                        }
                    }]),
                Textarea::make('keterangan')->rows(2),
            ])
            ->action(function (array $data, Bk $record) {
                try {
                    $bml = BkBmService::buatBml($record, $data['tanggal'], $data['keterangan'] ?? null, $data['no_nota'] ?? null);
                    Notification::make()->title("BML {$bml->no_nota} dibuat, silakan isi barangnya")->success()->send();
                    return redirect(BmResource::getUrl('view', ['record' => $bml]));
                } catch (\Throwable $e) {
                    Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                }
            });
    }
}
