<?php

namespace App\Filament\Resources\Bks\RelationManagers;

use App\Models\BarangUmum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BkDetailRelationManager extends RelationManager
{
    protected static string $relationship = 'detail';
    protected static ?string $title = 'Barang';

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->terkunci();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('barang_umum_id')->label('Barang')->options(fn () => BarangUmum::orderBy('nama_barang')->pluck('nama_barang', 'id'))
                ->searchable()->required()->live()
                ->afterStateUpdated(fn ($state, Set $set) => $set('satuan', BarangUmum::find($state)?->satuan)),
            TextInput::make('jumlah')->numeric()->minValue(0.001)->required(),
            TextInput::make('satuan')->maxLength(50),
            TextInput::make('keterangan')->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('nama_barang')->label('Barang'),
            TextColumn::make('jumlah')->numeric(),
            TextColumn::make('satuan'),
            TextColumn::make('keterangan'),
        ])->headerActions([
            CreateAction::make()->label('Tambah Barang')
                ->mutateDataUsing(function (array $data) {
                    $nama = BarangUmum::findOrFail($data['barang_umum_id'])->nama_barang;
                    unset($data['barang_umum_id']);
                    $data['nama_barang'] = config('bkbm.prefix_barang_umum') . $nama;
                    return $data;
                })
                ->visible(fn () => ! $this->getOwnerRecord()->terkunci()),
        ])->recordActions([
            DeleteAction::make()->visible(fn () => ! $this->getOwnerRecord()->terkunci()),
        ]);
    }
}
