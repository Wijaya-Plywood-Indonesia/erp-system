<?php

namespace App\Filament\Resources\IndukAkuns\RelationManagers;

use App\Models\AnakAkun;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubAnakAkunsRelationManager extends RelationManager
{
    protected static string $relationship = 'SubAnakAkuns';
    protected static ?string $title = 'Sub-Anak Akun';
    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_anak_akun')
                    ->label('Anak Akun')
                    ->searchable()
                    ->preload()
                    ->reactive() // <-- WAJIB supaya prefix berubah!
                    ->options(function (Get $get, $livewire) {
                        return AnakAkun::with('indukAkun')
                            ->where('id_induk_akun', $livewire->ownerRecord->id)
                            ->get()
                            ->mapWithKeys(fn($record) => [
                                $record->id => "[{$record->kode_anak_akun}] • {$record->indukAkun->nama_induk_akun} → {$record->nama_anak_akun}"
                            ]);
                    })
                    ->required(),


                TextInput::make('kode_sub_anak_akun')
                    ->label('Kode Sub Anak')
                    ->prefix(function (Get $get) {
                        $anakAkunId = $get('id_anak_akun');
                        if (!$anakAkunId) {
                            return null;
                        }

                        $anakAkun = AnakAkun::find($anakAkunId);
                        return $anakAkun ? $anakAkun->kode_anak_akun . '.' : null;
                    })
                    ->reactive()
                    ->required(),

                TextInput::make('nama_sub_anak_akun')
                    ->label('Nama Sub Anak Akun')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_sub_anak_akun')
            ->columns([
                TextColumn::make('kode_anak_akun')
                    ->label('Kode Akun')
                    ->getStateUsing(function ($record) {
                        return "{$record->kode_sub_anak_akun}";
                        // {$record->anakAkun->kode_anak_akun}.
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nama_sub_anak_akun')
                    ->label('Nama')
                    ->searchable(),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
