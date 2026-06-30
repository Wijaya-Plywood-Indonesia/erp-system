<?php

namespace App\Filament\Resources\ProduksiStiks\RelationManagers;

use App\Models\Pegawai;
use App\Models\DetailPegawaiStik;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class DetailPegawaiStikRelationManager extends RelationManager
{
    protected static ?string $title = 'Pegawai';
    protected static string $relationship = 'detailPegawaiStik';

    public static function timeOptions(): array
    {
        return collect(CarbonPeriod::create('00:00', '1 hour', '23:00')->toArray())
            ->mapWithKeys(fn($time) => [
                $time->format('H:i') => $time->format('H.i'),
            ])
            ->toArray();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('masuk')
                    ->label('Jam Masuk')
                    ->options(self::timeOptions())
                    ->default('06:00')
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                Select::make('pulang')
                    ->label('Jam Pulang')
                    ->options(self::timeOptions())
                    ->default('17:00')
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                TextInput::make('tugas')
                    ->label('Tugas')
                    ->default('Pegawai Stik')
                    ->readOnly()
                    ->maxLength(255),

                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->placeholder('Pilih pegawai yang tersedia...')
                    ->searchable()
                    ->required()
                    ->options(function ($record) {
                        // 1. Ambil ID pegawai yang sudah ada di Produksi Stik ini
                        $usedPegawaiIds = DetailPegawaiStik::query()
                            ->where('id_produksi_stik', $this->getOwnerRecord()->id)
                            // Jika sedang Edit, jangan sembunyikan pegawai yang sedang diedit itu sendiri
                            ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                            ->pluck('id_pegawai')
                            ->toArray();

                        // 2. Tampilkan hanya pegawai yang BELUM dipilih
                        return Pegawai::query()
                            ->whereNotIn('id', $usedPegawaiIds)
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                            ]);
                    })
                    ->live(), // Penting agar state dropdown selalu update
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Tetap pertahankan validasi double check di sisi server
        $exists = DetailPegawaiStik::where('id_produksi_stik', $this->getOwnerRecord()->id)
            ->where('id_pegawai', $data['id_pegawai'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'id_pegawai' => 'Pegawai ini sudah tercatat pada produksi yang sama.',
            ]);
        }

        return $data;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pegawai.nama_pegawai')
                    ->label('Pegawai')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('tugas')
                    ->label('Tugas')
                    ->searchable(),

                TextColumn::make('masuk')
                    ->label('Masuk')
                    ->dateTime('H:i'),

                TextColumn::make('pulang')
                    ->label('Pulang')
                    ->dateTime('H:i'),

                TextColumn::make('ijin')
                    ->label('Izin')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn() => $this->getOwnerRecord()->validasiTerakhir?->status === 'divalidasi'),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn() => $this->getOwnerRecord()->validasiTerakhir?->status === 'divalidasi'),

                DeleteAction::make()
                    ->hidden(fn() => $this->getOwnerRecord()->validasiTerakhir?->status === 'divalidasi'),

                Action::make('aturIjin')
                    ->label(fn($record) => $record->ijin ? 'Edit Ijin' : 'Tambah Ijin')
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        TextInput::make('ijin')->label('Izin'),
                        Textarea::make('ket')->label('Keterangan'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'ijin' => $data['ijin'],
                            'ket'  => $data['ket'],
                        ]);
                    })
                    ->hidden(fn() => $this->getOwnerRecord()->validasiTerakhir?->status === 'divalidasi'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn() => $this->getOwnerRecord()->validasiTerakhir?->status === 'divalidasi'),
                ]),
            ]);
    }
}
