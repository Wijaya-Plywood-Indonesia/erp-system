<?php

namespace App\Filament\Resources\Neracas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Illuminate\Database\Query\Builder;

class NeracasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('akun_seribu', 'asc')
            ->columns([
                // Menampilkan kode dari akun seribu (Induk)
                TextColumn::make('akun_seribu')
                    ->label('Kode Akun')
                    ->sortable()
                    ->searchable(),

                // Menampilkan detail/nama akun yang terisi otomatis
                TextColumn::make('detail')
                    ->label('Nama Akun')
                    ->searchable(),

                TextColumn::make('banyak')
                    ->label('Banyak')
                    ->numeric()
                    ->sortable(),

                // Format m3 dengan 4 angka di belakang koma untuk presisi
                TextColumn::make('kubikasi')
                    ->label('m3')
                    ->numeric(decimalPlaces: 4),

                // Menampilkan harga dengan format Rupiah
                TextColumn::make('harga')
                    ->label('Harga')
                    ->sortable(),

                // Total saldo dengan ringkasan Grand Total di bawah tabel
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn($state) =>
                    number_format($state, 2, ',', '.'))
                    ->summarize([
                        // Summarizer 1: Total Kelompok 1, 2, 3
                        Summarizer::make()
                            ->label('Total Kelompok 1-3')
                            ->query(fn(Builder $query) => $query->whereIn('akun_seribu', [1000, 2000, 3000]))
                            ->using(fn(Builder $query) => $query->sum('total')),

                        // Summarizer 2: Total Kelompok 4, 5, 6
                        Summarizer::make()
                            ->label('Total Kelompok 4-6')
                            ->query(fn(Builder $query) => $query->whereIn('akun_seribu', [4000, 5000, 6000]))
                            ->using(fn(Builder $query) => $query->sum('total')),

                        // Summarizer 3: Cek Balance (Neraca)
                        Summarizer::make()
                            ->label('Neraca')
                            ->using(function (Builder $query) {
                                // Ambil total grup A
                                $groupA = (clone $query)->whereIn('akun_seribu', [1000, 2000, 3000])->sum('total');
                                // Ambil total grup B
                                $groupB = (clone $query)->whereIn('akun_seribu', [4000, 5000, 6000])->sum('total');

                                // Rumus Neraca: Grup A + Grup B (Jika B bernilai negatif di akuntansi)
                                // Atau disesuaikan dengan logika input Anda apakah hasilnya 0
                                return $groupA + $groupB;
                            })
                            ->formatStateUsing(fn($state) => $state == 0 ? 'BALANCE (0)' : 'UNBALANCE (' . number_format($state, 0, ',', '.') . ')')
                    ]),

                TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Tanggal Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Tambahkan filter di sini jika diperlukan
            ])
            ->recordActions([
                // EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
