<?php

namespace App\Filament\Resources\ProduksiTembelTripleks\RelationManagers;

use App\Models\Pegawai;
use App\Models\PegawaiTembeltriplek;
use Carbon\CarbonPeriod;

// Custom Schema & Table
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Notifications\Notification;

// Form Components
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

// Table Columns & Custom Actions
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class PegawaiTembeltriplekRelationManager extends RelationManager
{
    protected static string $relationship = 'pegawaiTembeltriplek';
    
    protected static ?string $title = 'Pegawai Tembel Triplek';

    public static function timeOptions(): array
    {
        return collect(CarbonPeriod::create('00:00', '1 hour', '23:00')->toArray())
            ->mapWithKeys(fn($time) => [
                $time->format('H:i') => $time->format('H.i'),
            ])
            ->toArray();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->options(self::timeOptions())
                    ->default('06:00')
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                Select::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->options(self::timeOptions())
                    ->default('16:00')
                    ->required()
                    ->searchable()
                    ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                    ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->options(
                        Pegawai::query()
                            ->get()
                            ->mapWithKeys(fn($p) => [
                                $p->id => "{$p->kode_pegawai} - {$p->nama_pegawai}"
                            ])
                    )
                    ->rule(function ($livewire) {
                        return function (string $attribute, $value, $fail) use ($livewire) {
                            $produksiId = $livewire->ownerRecord->id ?? null;
                            $currentRecord = method_exists($livewire, 'getMountedTableActionRecord')
                                ? $livewire->getMountedTableActionRecord()
                                : null;

                            if (! $produksiId) return;

                            $query = PegawaiTembeltriplek::query()
                                ->where('id_produksi_tembel_triplek', $produksiId)
                                ->where('id_pegawai', $value);

                            if ($currentRecord) {
                                $query->where('id', '!=', $currentRecord->id);
                            }

                            if ($query->exists()) {
                                $fail('Pegawai ini sudah ditugaskan pada produksi ini!');
                            }
                        };
                    })
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('pegawai.nama_pegawai')
                    ->label('Pekerja')
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
                TextColumn::make('ijin')
                    ->default('-')
                    ->searchable(),
                TextColumn::make('keterangan')
                    ->default('-')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('ijin_keterangan')
                    ->label('Ijin & Ket.')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->form([
                        Select::make('ijin')
                            ->label('Jenis Ijin')
                            ->options([
                                '' => 'Tidak Ada Ijin',
                                'sakit' => 'Sakit',
                                'izin' => 'Izin Pribadi',
                                'cuti' => 'Cuti',
                                'alpha' => 'Tanpa Keterangan',
                            ])
                            ->native(false)
                            ->default(fn($record) => $record->ijin)
                            ->reactive(),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3)
                            ->placeholder('Alasan ijin / keterangan tambahan...')
                            ->default(fn($record) => $record->keterangan),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'ijin' => $data['ijin'] === '' ? null : $data['ijin'],
                            'keterangan' => $data['keterangan'],
                        ]);

                        Notification::make()
                            ->title('Ijin & keterangan berhasil disimpan')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn($record) => "Ijin & Keterangan - {$record->pegawai->nama_pegawai}")
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg'),

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