<?php

namespace App\Filament\Resources\JurnalTigas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class JurnalTigasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Menampilkan Kode Akun yang dipilih (1100/1110 dll)
                TextColumn::make('modif1000')
                    ->label('modif1000')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('akun_seratus')
                    ->label('Akun seratus')
                    ->sortable()
                    ->searchable(),

                // Menampilkan Nama Akun yang tersimpan di kolom detail
                TextColumn::make('detail')
                    ->label('Detail')
                    ->searchable(),

                // Kolom Produksi: Banyak & m3
                TextColumn::make('banyak')
                    ->label('Banyak')
                    ->numeric()
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Banyak Total'))
                    ->sortable(),

                TextColumn::make('kubikasi')
                    ->label('m3')
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Total Kubikasi'))
                    ->numeric(decimalPlaces: 4),

                // Kolom Keuangan dengan format Rupiah/Ribuan
                TextColumn::make('harga')
                    ->label('Harga')
                    ->sortable()
                    ->numeric(),

                TextColumn::make('total')
                    ->label('Total')
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Gran Total'))
                    ->sortable()
                    ->numeric(),

                // Menampilkan siapa yang menginput data
                TextColumn::make('createdBy')
                    ->label('Dibuat Oleh')
                    ->badge() // Menggunakan badge agar lebih rapi
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge() // Mengaktifkan fitur badge
                    ->color(fn(string $state): string => match ($state) {
                        'sinkron' => 'success',      // Warna Hijau
                        'belum sinkron' => 'danger', // Warna Merah
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)) // Merapikan teks (Contoh: Sinkron)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Waktu Input')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Waktu Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Kolom untuk melihat siapa yang melakukan sinkronisasi
                TextColumn::make('synchronized_by')
                    ->label('Disinkron Oleh')
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true), // Muncul secara default tapi bisa disembunyikan

                // Kolom untuk melihat waktu presisi sinkronisasi
                TextColumn::make('synchronized_at')
                    ->label('Waktu Sinkron')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),


            ])
            ->filters([
                // Anda bisa menambahkan filter berdasarkan CreatedBy atau Akun di sini
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
            ])
            ->bulkActions([ // Filament v4 menggunakan bulkActions untuk BulkActionGroup
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
