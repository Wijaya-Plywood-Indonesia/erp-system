<?php

namespace App\Filament\Resources\ProduksiRotaries\RelationManagers;

use App\Models\Pegawai;
use App\Models\PegawaiRotary;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class DetailPegawaiRotaryRelationManager extends RelationManager
{
    protected static ?string $title = 'Pegawai';
    protected static string $relationship = 'detailPegawaiRotary';
    //Format Angka
    // FUNGSI BARU UNTUK MEMUNCULKAN TOMBOL DI HALAMAN VIEW
    public function isReadOnly(): bool
    {
        return false;
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
                Select::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->options(self::timeOptions())
                    ->default('06:00') // Default: 06:00 (sore)
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null), // Tampilkan hanya HH:MM,
                Select::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->options(self::timeOptions())
                    ->default('16:00') // Default: 17:00 (sore)
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null), // Tampilkan hanya HH:MM,
                //sampai sini

                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->options(
                        Pegawai::query()
                            ->get()
                            ->mapWithKeys(function ($pegawai) {
                                return [
                                    $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                                ];
                            })
                    )
                    ->searchable()
                    ->required(),

                Select::make('role')
                    ->label('Peran Di Produksi')
                    ->options([
                        'operator_mesin' => 'Operator Mesin',
                        'petugas_pilih' => 'Petugas Pilih',
                        'siku' => 'Siku',
                        'sampah' => 'Sampah',
                        'operator_lain' => 'Tugas Lain',
                    ])
                    ->required()
                    ->native(false),
            ]);
    }
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $exists = PegawaiRotary::where('id_produksi', $this->ownerRecord->id)
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
            ->recordTitleAttribute('id_pegawai')
            ->columns([
                TextColumn::make('pegawai.nama_pegawai')
                    ->label('Nama Pegawai')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Role')
                    ->formatStateUsing(fn(string $state): string => [
                        'operator_mesin' => 'Operator Mesin',
                        'petugas_pilih' => 'Petugas Pilih',
                        'operator_lain' => 'Operator Produksi Lain',
                    ][$state] ?? $state),
                TextColumn::make('jam_masuk')
                    ->label('Jam Masuk'),
                TextColumn::make('jam_pulang')
                    ->label('Jam Pulang'),
                TextColumn::make('izin_keterangan')
                    ->label('Izin & Keterangan')
                    ->getStateUsing(function ($record) {
                        $html = '';

                        if (!empty($record->izin)) {
                            $html .= "<div><strong>Izin:</strong> {$record->izin}</div>";
                        }

                        if (!empty($record->keterangan)) {
                            $html .= "<div><strong>Keterangan:</strong> {$record->keterangan}</div>";
                        }

                        return $html;
                    })
                    ->html()
                    ->wrap(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([

                Action::make('aturIjin')
                    ->label(fn($record) => $record->ijin ? 'Edit Ijin' : 'Tambah Ijin')
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        TextInput::make('izin')->label('Izin'),
                        Textarea::make('ket')->label('Keterangan'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'izin' => $data['izin'],
                            'keterangan' => $data['ket'],
                        ]);
                    }),
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
