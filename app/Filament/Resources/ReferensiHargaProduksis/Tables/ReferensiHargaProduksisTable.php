<?php

namespace App\Filament\Resources\ReferensiHargaProduksis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReferensiHargaProduksisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->default('-'),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(function ($record) {
                        return $record->ukuran ? (
                            $record->ukuran->panjang . 'mm x ' .
                            $record->ukuran->lebar . 'mm x ' .
                            $record->ukuran->tebal . 'mm'
                        ) : '-';
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('ukuran', function ($q) use ($search) {
                            $q->where('panjang', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('tebal', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('kw')
                    ->label('KW')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jenis_barang')
                    ->label('Jenis Barang')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Veneer Jadi', 'Veneer' => 'success',
                        'Veneer Kering' => 'info',
                        'Veneer Basah' => 'info',
                        'Platform' => 'primary',
                        'Afalan' => 'danger',
                        'Plywood' => 'warning',
                        'Barang' => 'info',
                        'Lain-Lain' => 'gray',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('harga')
                    ->label('Harga')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 2, ',', '.'))
                    ->sortable(),

                TextColumn::make('subAnakAkun')
                    ->label('Sub Anak Akun')
                    ->state(fn($record) => $record->subAnakAkun ? $record->subAnakAkun->kode_sub_anak_akun . ' - ' . $record->subAnakAkun->nama_sub_anak_akun : '-')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('subAnakAkun', function ($q) use ($search) {
                            $q->where('kode_sub_anak_akun', 'like', "%{$search}%")
                                ->orWhere('nama_sub_anak_akun', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function ($query, string $direction) {
                        $query->join('sub_anak_akuns', 'referensi_harga_produksi.id_sub_anak_akun', '=', 'sub_anak_akuns.id')
                            ->orderBy('sub_anak_akuns.nama_sub_anak_akun', $direction);
                    }),

                TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
