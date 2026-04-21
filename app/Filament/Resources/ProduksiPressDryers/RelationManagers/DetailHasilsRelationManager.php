<?php

namespace App\Filament\Resources\ProduksiPressDryers\RelationManagers;

use App\Models\DetailHasil;
use App\Services\SerahHasilDryerService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DetailHasilsRelationManager extends RelationManager
{
    protected static ?string $title = 'Hasil';
    protected static string $relationship = 'detailHasils';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),

                Select::make('id_ukuran')
                    ->label('Ukuran Kayu')
                    ->options(function () {
                        $produksi = $this->getOwnerRecord();
                        return \App\Models\DetailMasuk::where('id_produksi_dryer', $produksi->id)
                            ->with('ukuran')
                            ->get()
                            ->pluck('ukuran.nama_ukuran', 'id_ukuran')
                            ->unique();
                    })
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(['last_ukuran' => $state]);
                    })
                    ->default(fn() => session('last_ukuran'))
                    ->required(),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(function () {
                        $produksi = $this->getOwnerRecord();
                        return \App\Models\DetailMasuk::where('id_produksi_dryer', $produksi->id)
                            ->select('id_jenis_kayu')
                            ->distinct()
                            ->with('jenisKayu:id,nama_kayu')
                            ->get()
                            ->pluck('jenisKayu.nama_kayu', 'id_jenis_kayu');
                    })
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(['last_jenis_kayu' => $state]);
                    })
                    ->default(fn() => session('last_jenis_kayu'))
                    ->required(),

                TextInput::make('kw')
                    ->label('Kualitas (KW)')
                    ->required()
                    ->placeholder('Cth: 1, 2, 3 dll.'),

                TextInput::make('isi')
                    ->label('Isi')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 1.5 atau 100'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
        ->modifyQueryUsing(fn($query) => $query->with(['stokMasuk', 'ukuran', 'jenisKayu', 'produksiDryer']))
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    ->color(fn($record) => $record->stokMasuk ? 'success' : 'gray')
                    ->description(fn($record) => $record->stokMasuk ? 'Sudah Serah' : 'Belum Serah'),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable(),

                TextColumn::make('ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('ukuran', function (Builder $q) use ($search) {
                            $q->where('panjang', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('tebal', 'like', "%{$search}%")
                                ->orWhereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('kw')
                    ->label('Kualitas (KW)')
                    ->searchable(),

                TextColumn::make('isi')
                    ->label('Isi'),

                TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                Action::make('serahKeGudang')
                    ->label('Serahkan Hasil')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Palet ke Gudang Kering?')
                    ->modalDescription('Setelah diserahkan, data ini akan masuk ke stok gudang dan tombol serah akan hilang.')
                    ->modalSubmitActionLabel('Ya, Serahkan Sekarang')
                    ->visible(fn($record) => is_null($record->stokMasuk))
                    ->action(function (DetailHasil $record) {
                        try {
                            app(SerahHasilDryerService::class)->serahkan($record);

                            Notification::make()
                                ->title('Penyerahan Berhasil')
                                ->body("Palet {$record->no_palet} telah dipindahkan ke stok gudang.")
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Terjadi Kesalahan Sistem')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}