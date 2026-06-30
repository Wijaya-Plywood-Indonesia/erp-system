<?php

namespace App\Filament\Resources\Perusahaans\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JabatanRelationManager extends RelationManager
{
    public function isReadOnly(): bool
    {
        return false;
    }
    protected static string $relationship = 'jabatans';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama_jabatan')
                ->label('Nama Jabatan')
                ->required()
                ->maxLength(255),

            Textarea::make('deskripsi')
                ->label('Deskripsi')
                ->rows(3),
            Select::make('jam_masuk')
                ->label('Jam Masuk')
                ->options(self::hoursOptions())
                ->default('06:00')
                ->searchable(),

            Select::make('jam_pulang')
                ->label('Jam Pulang')
                ->options(self::hoursOptions())
                ->default('16:00')
                ->searchable(),

            Select::make('istirahat_mulai')
                ->label('Istirahat Mulai')
                ->options(self::hoursOptions())
                ->default('12:00')
                ->searchable(),

            Select::make('istirahat_selesai')
                ->label('Istirahat Selesai')
                ->options(self::hoursOptions())
                ->default('13:00')
                ->searchable(),
        ]);
    }
    public static function hoursOptions(): array
    {
        $options = [];

        for ($i = 0; $i < 24; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
            $options[$hour] = $hour;
        }

        return $options;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_jabatan')
            ->columns([
                TextColumn::make('nama_jabatan')->label('Jabatan')->searchable(),

                TextColumn::make('jam_masuk')
                    ->label('Masuk')
                    ->formatStateUsing(fn($state) => substr($state, 0, 5)),

                TextColumn::make('jam_pulang')
                    ->label('Pulang')
                    ->formatStateUsing(fn($state) => substr($state, 0, 5)),

                TextColumn::make('istirahat_mulai')
                    ->label('Istirahat Mulai')
                    ->formatStateUsing(fn($state) => substr($state, 0, 5)),

                TextColumn::make('istirahat_selesai')
                    ->label('Istirahat Selesai')
                    ->formatStateUsing(fn($state) => substr($state, 0, 5)),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
