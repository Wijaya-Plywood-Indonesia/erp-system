<?php

namespace App\Filament\Resources\BahanPenolongRotaries\Tables;

use App\Filament\Resources\BahanPenolongRotaries\Schemas\BahanPenolongRotaryForm;
use App\Models\BahanPenolongProduksi;
use App\Models\BahanPenolongValidasi;
use App\Services\BahanPenolongPotongStokService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BahanPenolongRotariesTable
{
    protected static function sudahDivalidasi($livewire): bool
    {
        $owner = $livewire->ownerRecord;

        if (!$owner) {
            return false;
        }

        return BahanPenolongValidasi::sudahDivalidasi(get_class($owner), $owner->id);
    }

    public static function configure(Table $table): Table
    {
        $bahanOptions = BahanPenolongRotaryForm::getBahanOptions();
        return $table
            ->columns([
                TextColumn::make('bahanPenolong.nama_bahan_penolong')
                    ->label('Nama Bahan')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->bahanPenolong ? 
                        $record->bahanPenolong->nama_bahan_penolong . ' (' . $record->bahanPenolong->satuan . ')' : 
                        $state
                    ),

                TextColumn::make('jumlah')
                    ->label('Banyaknya'),
            ])
            ->filters([
                // SelectFilter::make('nama_bahan')
                //     ->options($bahanOptions)
                //     ->multiple(),
            ])
            ->headerActions([
                Action::make('validasiBahanPenolong')
                    ->label('Validasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Validasi Bahan Penolong?')
                    ->modalDescription('Setelah divalidasi, semua bahan yang tercatat di bawah akan memotong Stok Barang Umum secara otomatis dan tidak bisa diubah/dihapus lagi.')
                    ->modalSubmitActionLabel('Ya, Validasi & Potong Stok')
                    ->hidden(fn($livewire) => static::sudahDivalidasi($livewire))
                    // TODO: pastikan nama relasi 'bahanPenolongRotaries' di bawah ini sesuai
                    // dengan method relasi hasMany yang sebenarnya ada di model produksi
                    // (mis. ProduksiRotary). Ganti jika nama relasinya berbeda.
                    ->disabled(fn($livewire) => $livewire->ownerRecord?->bahanPenolongRotaries()->count() === 0)
                    ->action(function ($livewire) {
                        try {
                            app(BahanPenolongPotongStokService::class)->validasiDanPotongStok(
                                produksi: $livewire->ownerRecord,
                                relasiBahan: 'bahanPenolongRotaries', // TODO: sesuaikan nama relasi jika berbeda
                                userId: Auth::id(),
                                namaValidator: Auth::user()?->name,
                            );

                            Notification::make()->success()
                                ->title('Bahan penolong berhasil divalidasi')
                                ->body('Stok Barang Umum telah dipotong sesuai pemakaian.')
                                ->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->danger()
                                ->title('Gagal memvalidasi')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                CreateAction::make()
                    // Hidden jika sudah divalidasi
                    ->hidden(fn($livewire) => static::sudahDivalidasi($livewire))
                    ->using(function (array $data, string $model, $livewire): \Illuminate\Database\Eloquent\Model {
                        $ownerRecord = $livewire->ownerRecord;

                        $existing = $model::where('id_produksi', $ownerRecord->id)
                            ->where('bahan_penolong_id', $data['bahan_penolong_id'])
                            ->first();

                        if ($existing) {
                            $existing->increment('jumlah', $data['jumlah']);
                            return $existing;
                        }

                        return $model::create(array_merge($data, ['id_produksi' => $ownerRecord->id]));
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn($livewire) => static::sudahDivalidasi($livewire)),

                DeleteAction::make()
                    ->hidden(fn($livewire) => static::sudahDivalidasi($livewire)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn($livewire) => static::sudahDivalidasi($livewire)),
                ]),
            ]);
    }
}