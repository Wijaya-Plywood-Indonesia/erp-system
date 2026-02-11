<?php

namespace App\Filament\Resources\Absensis\RelationManagers;

use App\Filament\Pages\Absen;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class DetailAbsensiRelationManager extends RelationManager
{
    protected static string $relationship = 'detailAbsensis';

    /**
     * Kita kosongkan Form karena data ini bersifat Read-Only 
     * hasil dari parsing file mesin finger.
     */
    public function form(Schema $schema): Schema
    {
        return $schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('kode_pegawai')
            ->columns([

                TextColumn::make('tanggal')
                    ->label('Tanggal Absen')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('kode_pegawai')
                    ->label('Kode Pegawai')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->time('H:i:s')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->time('H:i:s')
                    ->badge()
                    ->color('danger')
                    ->placeholder('-- : -- : --') // Tampil jika data pulang tidak ada
                    ->sortable(),
            ])
            ->filters([
                // Anda bisa menambahkan filter di sini jika diperlukan
            ])
            ->headerActions([
                // Header action dikosongkan karena tidak boleh input manual
            ])
            ->actions([
                // Kita tidak berikan EditAction karena bersifat read-only
            ])
            ->headerActions([
                Action::make('sync_to_report')
                    ->label('Sync ke Laporan')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('success')
                    ->action(function ($livewire) {
                        // Kita arahkan user ke Custom Page Absen dengan parameter tanggal
                        // Misalnya record terakhir yang dipilih atau tanggal hari ini
                        return redirect()->to(Absen::getUrl([
                            'tanggal' => now()->format('Y-m-d')
                        ]));
                    })
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // Hanya berikan Delete jika ingin menghapus data yang salah import
                    DeleteBulkAction::make(),
                    // Opsional: Jika menggunakan filament-excel
                    // ExportBulkAction::make(), 
                ]),
            ]);
    }
}
