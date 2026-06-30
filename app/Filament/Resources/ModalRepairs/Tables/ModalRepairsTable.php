<?php

namespace App\Filament\Resources\ModalRepairs\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ModalRepairsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ukuran.dimensi')
                    ->label('Ukuran')
                    ->numeric()
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('ukuran', function (Builder $q) use ($search) {
                            $q->where('panjang', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('tebal', 'like', "%{$search}%")
                                // Opsional: Mendukung format pencarian "12 x 12"
                                ->orWhereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                        });
                    }),
                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('jenisKayu', function (Builder $q) use ($search) {
                            $q->where('nama_kayu', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('jumlah')
                    ->label('Jumlah bahan')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('kw')
                    ->label('KW')
                    ->searchable(),
                TextColumn::make('nomor_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('keterangan')
                    ->label('Kehilangan/Kelebihan'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('aturIjin')
                    ->label('Keterangan')
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        Textarea::make('keterangan')->label('Kehilangan/Kelebihan'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'keterangan'  => $data['keterangan'],
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
