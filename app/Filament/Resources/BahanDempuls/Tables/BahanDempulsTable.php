<?php

namespace App\Filament\Resources\BahanDempuls\Tables;

use App\Filament\Resources\BahanDempuls\Schemas\BahanDempulForm;
use App\Models\BahanDempul;
use App\Models\BahanPenolongValidasi;
use App\Services\BahanPenolongPotongStokService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

class BahanDempulsTable
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
        return $table
            ->columns([
                TextColumn::make('nama_bahan')
                    ->label('Nama Bahan')
                    ->formatStateUsing(function ($state) {
                        $bahan = \App\Models\BahanPenolongProduksi::where('nama_bahan_penolong', $state)->first();
                        return $state . ($bahan ? " ({$bahan->satuan})" : "");
                    })
                    ->searchable(),

                TextColumn::make('jumlah')
                    ->label('Banyaknya'),
            ])
            ->filters([
                //
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
                    ->disabled(fn($livewire) => $livewire->ownerRecord?->bahanDempuls()->count() === 0)
                    ->action(function ($livewire) {
                        try {
                            app(BahanPenolongPotongStokService::class)->validasiDanPotongStok(
                                produksi: $livewire->ownerRecord,
                                relasiBahan: 'bahanDempuls',
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

                        $existing = $model::where('id_produksi_dempul', $ownerRecord->id)
                            ->where('nama_bahan', $data['nama_bahan'])
                            ->first();

                        if ($existing) {
                            $existing->increment('jumlah', $data['jumlah']);
                            return $existing;
                        }

                        return $model::create(array_merge($data, ['id_produksi_dempul' => $ownerRecord->id]));
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