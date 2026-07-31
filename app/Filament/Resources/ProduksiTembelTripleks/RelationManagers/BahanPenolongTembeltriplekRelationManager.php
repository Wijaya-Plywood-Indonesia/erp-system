<?php

namespace App\Filament\Resources\ProduksiTembelTripleks\RelationManagers;

use App\Models\BahanPenolongProduksi;
use App\Models\BahanPenolongValidasi;
use App\Services\BahanPenolongPotongStokService;

// Custom Schema & Table
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

// Form Components
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

// Table Columns & Custom Actions
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class BahanPenolongTembeltriplekRelationManager extends RelationManager
{
    protected static string $relationship = 'bahanPenolongTembeltriplek';

    protected static ?string $title = 'Bahan Penolong';

    protected static function sudahDivalidasi($livewire): bool
    {
        $owner = $livewire->ownerRecord;

        if (!$owner) {
            return false;
        }

        return BahanPenolongValidasi::sudahDivalidasi(get_class($owner), $owner->id);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('nama_bahan')
                    ->label('Nama Bahan')
                    ->options(
                        fn() =>
                        BahanPenolongProduksi::where('kategori_produksi', 'tembel_triplek')
                            ->get()
                            ->mapWithKeys(fn($item) => [
                                $item->nama_bahan_penolong => $item->nama_bahan_penolong . ' (' . $item->satuan . ')'
                            ])
                            ->toArray()
                    )
                    ->required()
                    ->native(false)
                    ->searchable(),

                TextInput::make('jumlah')
                    ->label('Banyak')
                    ->required()
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_bahan')
            ->columns([
                TextColumn::make('nama_bahan')
                    ->label('Nama Bahan')
                    ->formatStateUsing(function ($state) {
                        $bahan = BahanPenolongProduksi::where('nama_bahan_penolong', $state)->first();
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
                    ->disabled(fn($livewire) => $livewire->ownerRecord?->bahanPenolongTembeltriplek()->count() === 0)
                    ->action(function ($livewire) {
                        try {
                            app(BahanPenolongPotongStokService::class)->validasiDanPotongStok(
                                produksi: $livewire->ownerRecord,
                                relasiBahan: 'bahanPenolongTembeltriplek',
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
                    ->hidden(fn($livewire) => static::sudahDivalidasi($livewire))
                    ->using(function (array $data, string $model, $livewire): \Illuminate\Database\Eloquent\Model {
                        $ownerRecord = $livewire->ownerRecord;

                        $existing = $model::where('id_produksi_tembel_triplek', $ownerRecord->id)
                            ->where('nama_bahan', $data['nama_bahan'])
                            ->first();

                        if ($existing) {
                            $existing->increment('jumlah', $data['jumlah']);
                            return $existing;
                        }

                        return $model::create(array_merge($data, ['id_produksi_tembel_triplek' => $ownerRecord->id]));
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn($livewire) => static::sudahDivalidasi($livewire)),
                DeleteAction::make()
                    ->hidden(fn($livewire) => static::sudahDivalidasi($livewire)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn($livewire) => static::sudahDivalidasi($livewire)),
                ]),
            ]);
    }
}