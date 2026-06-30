<?php

namespace App\Filament\Resources\AkunGroups\RelationManagers;

use App\Models\AnakAkun;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Tables;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class AnakAkunsRelationManager extends RelationManager
{
    public function isReadOnly(): bool
    {
        return false;
    }

    protected static string $relationship = 'anakAkuns';

    protected static ?string $title = 'Daftar Akun';

    /*
    |--------------------------------------------------------------------------
    | Leaf Only Enforcement
    |--------------------------------------------------------------------------
    */

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->isLeaf();
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->recordTitleAttribute('nama_anak_akun')
            ->columns([
                TextColumn::make('kode_anak_akun')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama_anak_akun')
                    ->label('Nama Akun')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('indukAkun.nama_induk_akun')
                    ->label('Induk')
                    ->sortable(),
            ])
            ->headerActions([
                // AttachAction::make()
                //     ->label('Daftarkan Akun')
                //     ->preloadRecordSelect()
                //     ->multiple()
                //     ->recordTitle(
                //         fn(AnakAkun $record) =>
                //         "{$record->kode_anak_akun} - {$record->nama_anak_akun}"
                //     )
                //     ->recordSelectSearchColumns([
                //         'kode_anak_akun',
                //         'nama_anak_akun',
                //     ])
                //     ->recordSelectOptionsQuery(
                //         function ($query, $livewire) {
                //             return $query
                //                 ->where('status', 'aktif')
                //                 ->whereDoesntHave(
                //                     'akunGroups',
                //                     fn($q) =>
                //                     $q->where(
                //                         'akun_group_id',
                //                         $livewire->ownerRecord->id
                //                     )

                //                 );
                //         }
                //     ),
                AttachAction::make()
                    ->label('Daftarkan Akun')
                    ->preloadRecordSelect()
                    ->multiple()
                    ->recordTitle(
                        fn(AnakAkun $record) =>
                        "{$record->kode_anak_akun} - {$record->nama_anak_akun}"
                    )
                    ->recordSelectSearchColumns([
                        'kode_anak_akun',
                        'nama_anak_akun',
                    ])
                    ->recordSelectOptionsQuery(
                        fn($query) =>
                        $query
                            ->where('status', 'aktif')
                            ->whereDoesntHave('akunGroups') // 🔥 global lock
                    ),
            ])
            ->actions([
                DetachAction::make(),
            ])
            ->bulkActions([
                DetachBulkAction::make(),
            ]);
    }
}