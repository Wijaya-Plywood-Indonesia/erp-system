<?php

namespace App\Filament\Resources\ValidasiStiks\Tables;

use App\Services\ProduksiLockService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;

class ValidasiStiksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('role')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Create Action — HILANG jika sudah divalidasi, KECUALI Super Admin
                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        ProduksiLockService::isLocked($livewire->ownerRecord, 'validasiStik')
                    ),
            ])
            ->recordActions([
                // Edit Action — HILANG jika sudah divalidasi, KECUALI Super Admin.
                // Ini penting: baris "divalidasi" tetap BISA diedit/dihapus oleh
                // Super Admin, sehingga Super Admin bisa membuka kembali produksi
                // dengan menghapus baris validasinya.
                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        ProduksiLockService::isLocked($livewire->ownerRecord, 'validasiStik')
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        ProduksiLockService::isLocked($livewire->ownerRecord, 'validasiStik')
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            ProduksiLockService::isLocked($livewire->ownerRecord, 'validasiStik')
                        ),
                ]),
            ]);
    }
}