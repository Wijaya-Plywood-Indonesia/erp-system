<?php

namespace App\Filament\Resources\Bms\Actions;

use App\Models\Bk;
use App\Models\Bm;
use App\Services\BkBm\BkBmService;
use App\Services\BkBm\NomorBkBmService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/** Dari menu BM: pilih BKL yang masih di luar, lalu buat BML-nya. */
class BuatBmlDariBklAction
{
    public static function make(): Action
    {
        return Action::make('bml_dari_bkl')
            ->label('Barang Kembali dari BKL')
            ->color('info')
            ->icon('heroicon-o-arrow-uturn-left')
            ->modalHeading('Barang kembali dari luar')
            ->schema([
                Select::make('id_bk')->label('BKL yang masih di luar')->required()->searchable()
                    ->options(fn () => Bk::where('kategori', 'bkl')->where('status', 'divalidasi')
                        ->whereDoesntHave('bmTerkait', fn ($q) => $q->where('kategori', 'bml'))
                        ->orderBy('divalidasi_at')->get()
                        ->mapWithKeys(fn (Bk $b) => [$b->id => "{$b->no_nota} · {$b->tujuan_nota} · " . $b->divalidasi_at?->format('d M Y')])),
                DatePicker::make('tanggal')->label('Tanggal kembali')->default(today())->required(),
                TextInput::make('no_nota')->label('No. BML')->required()->maxLength(255)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                    ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                    ->default(fn () => NomorBkBmService::saran('bml', today()))
                    ->helperText('Saran otomatis, boleh diubah.')
                    ->rules([fn () => function (string $a, $v, \Closure $fail) {
                        if (Bm::where('no_nota', trim((string) $v))->exists()) {
                            $fail('Nomor nota ini sudah terdaftar.');
                        }
                    }]),
                Textarea::make('keterangan')->rows(2),
            ])
            ->action(function (array $data) {
                try {
                    $bml = BkBmService::buatBml(Bk::findOrFail($data['id_bk']), $data['tanggal'], $data['keterangan'] ?? null, $data['no_nota']);
                    Notification::make()->title("BML {$bml->no_nota} dibuat, silakan isi barangnya")->success()->send();
                    return redirect(\App\Filament\Resources\Bms\BmResource::getUrl('view', ['record' => $bml]));
                } catch (\Throwable $e) {
                    Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                }
            });
    }
}
