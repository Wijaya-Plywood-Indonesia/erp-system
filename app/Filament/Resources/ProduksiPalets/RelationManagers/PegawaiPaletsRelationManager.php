<?php

namespace App\Filament\Resources\ProduksiPalets\RelationManagers;

use App\Models\Pegawai;
use App\Services\ValidasiProduksiPaletService;
use Carbon\CarbonPeriod;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PegawaiPaletsRelationManager extends RelationManager
{
    protected static string $relationship = 'pegawaiPalets';

    public function isReadOnly(): bool
    {
        $owner = $this->getOwnerRecord();
        return $owner ? ValidasiProduksiPaletService::isLocked($owner) : false;
    }

    public static function timeOptions(): array
    {
        return collect(CarbonPeriod::create('00:00', '1 hour', '23:00')->toArray())
            ->mapWithKeys(fn($time) => [
                $time->format('H:i') => $time->format('H.i'),
            ])
            ->toArray();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->options(
                        Pegawai::query()
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                            ])
                    )
                    ->searchable()
                    ->required(),

                // 2. Select Jam Masuk (Sama seperti PegawaiRotaryForm)
                Select::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->options(self::timeOptions())
                    ->default('06:00')
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                // 3. Select Jam Pulang (Sama seperti PegawaiRotaryForm)
                Select::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->options(self::timeOptions())
                    ->default('17:00')
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                // 4. Status Izin & Keterangan
                Select::make('izin')
                    ->label('Izin')
                    ->options([
                        'Hadir' => 'Hadir',
                        'Izin' => 'Izin',
                        'Sakit' => 'Sakit',
                        'Alpha' => 'Alpha',
                    ])
                    ->nullable()
                    ->native(false),

                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('Catatan tambahan jika ada')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();
        return $table
            ->recordTitleAttribute('PegawaiPalet')
            ->columns([
                TextColumn::make('pegawai.nama_pegawai')
                    ->label('Nama Pegawai')
                    ->formatStateUsing(
                        fn($record) => $record->pegawai
                            ? $record->pegawai->kode_pegawai . ' - ' . $record->pegawai->nama_pegawai
                            : '—'
                    )
                    ->badge()
                    ->searchable(
                        query: fn($query, $search) => $query->whereHas(
                            'pegawai',
                            fn($q) => $q
                                ->where('nama_pegawai', 'like', "%{$search}%")
                                ->orWhere('kode_pegawai', 'like', "%{$search}%")
                        )
                    ),

                TextColumn::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('izin')
                    ->label('Izin')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Hadir' => 'success',
                        'Izin', 'Sakit' => 'warning',
                        'Alpha' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('-'),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn() => $owner && ValidasiProduksiPaletService::isStatusDivalidasi($owner))
                    ->after(fn($record) => ValidasiProduksiPaletService::prosesValidasi($record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn() => $owner && ValidasiProduksiPaletService::isStatusDivalidasi($owner))
                    ->after(fn($record) => ValidasiProduksiPaletService::prosesValidasi($record)),
                DeleteAction::make()
                    ->hidden(fn() => $owner && ValidasiProduksiPaletService::isStatusDivalidasi($owner))
                    ->after(fn($record) => ValidasiProduksiPaletService::prosesValidasi($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn() => $owner && ValidasiProduksiPaletService::isStatusDivalidasi($owner))
                        ->after(fn($record) => ValidasiProduksiPaletService::prosesValidasi($record)),
                ]),
            ]);
    }
}
