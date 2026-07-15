<?php

namespace App\Filament\Resources\ProduksiTembelTripleks\RelationManagers;

use Filament\Facades\Filament;

// Custom Schema & Table
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

// Form Components
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

// Table Columns & Custom Actions
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class ValidasiTembeltriplekRelationManager extends RelationManager
{
    protected static string $relationship = 'validasiTembeltriplek';

    protected static ?string $title = 'Validasi Produksi';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('role')
                    ->label('Role Login')
                    ->default(function () {
                        $user = Filament::auth()->user();

                        if (!$user) {
                            return 'Tidak diketahui';
                        }

                        return $user->getRoleNames()->first() ?? 'Tidak diketahui';
                    })
                    ->disabled()
                    ->dehydrated(true),

                Select::make('status')
                    ->label('Status Validasi')
                    ->options([
                        'divalidasi' => 'Divalidasi',
                        'disetujui' => 'Disetujui',
                        'ditangguhkan' => 'Ditangguhkan',
                        'ditolak' => 'Ditolak',
                    ])
                    ->required()
                    ->native(false)
                    ->searchable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('role')
            ->columns([
                TextColumn::make('role')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'divalidasi' => 'success',
                        'disetujui' => 'info',
                        'ditangguhkan' => 'warning',
                        'ditolak' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Tanggal Validasi')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTembeltriplek()->latest()->first()?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTembeltriplek()->latest()->first()?->status === 'divalidasi'
                    ),
                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTembeltriplek()->latest()->first()?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTembeltriplek()->latest()->first()?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}