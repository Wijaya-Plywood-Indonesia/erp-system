<?php

namespace App\Filament\Resources\ProduksiRepairs\RelationManagers;

use App\Models\BahanPenolongProduksi;
use App\Models\BahanPenolongValidasi;
use App\Services\BahanPenolongPotongStokService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BahanPenolongRepairRelationManager extends RelationManager
{
    protected static string $relationship = 'bahanPenolongRepair';
    public function isReadOnly(): bool
    {
        return false;
    }

    protected static function sudahDivalidasi($livewire): bool
    {
        $owner = $livewire->ownerRecord;

        if (!$owner) {
            return false;
        }

        return BahanPenolongValidasi::sudahDivalidasi(get_class($owner), $owner->id);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('bahan_penolong_id')
                    ->label('Nama Bahan')
                    ->options(
                        fn() =>
                        BahanPenolongProduksi::where('kategori_produksi', 'repair')
                            ->get()
                            ->mapWithKeys(fn($item) => [
                                $item->id =>
                                $item->nama_bahan_penolong . ' (' . $item->satuan . ')'
                            ])
                            ->toArray()
                    )
                    ->searchable()
                    ->required(),

                TextInput::make('jumlah')
                    ->label('Banyak')
                    ->required()
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
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
                    ->disabled(fn($livewire) => $livewire->ownerRecord?->bahanPenolongRepair()->count() === 0)
                    ->action(function ($livewire) {
                        try {
                            app(BahanPenolongPotongStokService::class)->validasiDanPotongStok(
                                produksi: $livewire->ownerRecord,
                                relasiBahan: 'bahanPenolongRepair',
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
                    // Hidden jika bahan penolong sudah divalidasi (dipotong stoknya)
                    ->hidden(fn($livewire) => static::sudahDivalidasi($livewire))
                    ->using(function (array $data, string $model, $livewire): \Illuminate\Database\Eloquent\Model {
                        $ownerRecord = $livewire->ownerRecord;

                        $existing = $model::where('id_produksi_repair', $ownerRecord->id)
                            ->where('bahan_penolong_id', $data['bahan_penolong_id'])
                            ->first();

                        if ($existing) {
                            $existing->increment('jumlah', $data['jumlah']);
                            return $existing;
                        }

                        return $model::create(array_merge($data, ['id_produksi_repair' => $ownerRecord->id]));
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