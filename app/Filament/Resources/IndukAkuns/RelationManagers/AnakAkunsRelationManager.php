<?php

namespace App\Filament\Resources\IndukAkuns\RelationManagers;

use App\Models\AnakAkun;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnakAkunsRelationManager extends RelationManager
{
    // diperbaiki → harus camelCase sesuai relasi di model
    protected static string $relationship = 'anakAkuns';
    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('kode_anak_akun')
                    ->label('Kode Anak Akun')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true) // ini otomatis munculkan error inline merah
                    ->validationMessages([
                        'unique' => 'Kode anak akun ini sudah digunakan oleh akun lain.',
                    ]),

                TextInput::make('nama_anak_akun')
                    ->label('Nama Anak Akun')
                    ->required()
                    ->maxLength(255),

                // Parent (opsional)
                Select::make('parent')
                    ->label('Parent')
                    ->options(AnakAkun::pluck('nama_anak_akun', 'id'))
                    ->relationship('parentAkun', 'nama_anak_akun')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Textarea::make('keterangan')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        1 => 'Aktif',
                        0 => 'Non-Aktif',
                    ])
                    ->native(false) // Disarankan agar UI lebih konsisten dengan Filament
                    ->required()
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_anak_akun')
            ->columns([

                TextColumn::make('kode_anak_akun')
                    ->label('Kode Akun')
                    ->getStateUsing(function ($record) {
                        return "{$record->indukAkun->kode_induk_akun}{$record->kode_anak_akun}";
                    })
                    ->sortable()
                    ->searchable(),

                // Tampilkan parent
                TextColumn::make('parentAkun.nama_anak_akun')
                    ->label('Parent')
                    ->placeholder('-'),

                TextColumn::make('nama_anak_akun')
                    ->label('Nama Anak Akun')
                    ->sortable()
                    ->searchable(),


                TextColumn::make('keterangan')
                    ->limit(30)
                    ->suffix('...')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('status')
                    ->sortable()
                    ->searchable()
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto isi created_by
                        $data['created_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
