<?php

namespace App\Filament\Resources\ProduksiKedis\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use App\Models\JenisKayu;
use App\Models\Ukuran;
use App\Models\Mesin;
use Illuminate\Database\Eloquent\Builder;

class DetailBongkarRelationManager extends RelationManager
{
    protected static ?string $title = 'Bongkar Kedi';
    protected static string $relationship = 'detailBongkarKedi';

    public function isReadOnly(): bool
    {
        return false;
    }




    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([



                // Pilihan Kayu Gabungan (Jenis Kayu + Ukuran) dari data masuk
                Select::make('kayu_masuk_composite')
                    ->label('Pilih Kayu (Dari Data Masuk)')
                    ->options(function ($livewire) {
                        return $livewire->ownerRecord->detailMasukKedi()
                            ->with(['jenisKayu', 'ukuran'])
                            ->get()
                            ->mapWithKeys(function ($d) {
                                $key = "{$d->id_jenis_kayu}-{$d->id_ukuran}";
                                $label = "{$d->jenisKayu->nama_kayu} | {$d->ukuran->dimensi}";
                                return [$key => $label];
                            })
                            ->unique();
                    })
                    ->searchable()
                    ->live()
                    ->formatStateUsing(fn ($record) => $record ? "{$record->id_jenis_kayu}-{$record->id_ukuran}" : null)
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            [$jenisId, $ukuranId] = explode('-', $state);
                            $set('id_jenis_kayu', $jenisId);
                            $set('id_ukuran', $ukuranId);
                        } else {
                            $set('id_jenis_kayu', null);
                            $set('id_ukuran', null);
                        }
                    })
                    ->required()
                    ->dehydrated(false), // Jangan simpan kolom virtual ini ke DB

                \Filament\Forms\Components\Hidden::make('id_jenis_kayu')
                    ->required(),

                \Filament\Forms\Components\Hidden::make('id_ukuran')
                    ->required(),


                TextInput::make('kw')
                    ->label('Kualitas (KW)')

                    ->required()
                    ->placeholder('Cth: 1, 2, 3 dll.'),

                TextInput::make('jumlah')
                    ->label('Jumlah')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 1.5 atau 100'),
                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),
            ]);
    }


    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable(),



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
                                // Mendukung format pencarian "12 x 12"
                                ->orWhereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('kw')
                    ->label('Kualitas (KW)')
                    ->searchable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah'),

                TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Create Action — HILANG jika status sudah divalidasi
                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->isBongkarDivalidasi()
                    ),
            ])
            ->recordActions([
                // Edit Action — HILANG jika status sudah divalidasi
                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->isBongkarDivalidasi()
                    ),

                // Delete Action — HILANG jika status sudah divalidasi
                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->isBongkarDivalidasi()
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->isBongkarDivalidasi()
                        ),
                ]),
            ]);
    }
    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        return in_array($ownerRecord->status, ['bongkar', 'selesai']);
    }
}
