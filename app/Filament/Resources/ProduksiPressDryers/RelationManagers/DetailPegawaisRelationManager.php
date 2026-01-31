<?php

namespace App\Filament\Resources\ProduksiPressDryers\RelationManagers;

use App\Models\Pegawai;
use App\Models\DetailPegawai;
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
use Illuminate\Database\Eloquent\Model;

class DetailPegawaisRelationManager extends RelationManager
{
    protected static ?string $title = 'Pegawai';
    protected static string $relationship = 'detailPegawais';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public static function showTableHeaderActionsInView(): bool
    {
        return true;
    }

    public static function canCreateForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

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

                Select::make('id_pegawai')
                    ->label('Pegawai')
                    ->options(function ($livewire, $record) {
                        // Ambil daftar pegawai yang SUDAH ada di produksi ini
                        $usedPegawaiIds = DetailPegawai::where('id_produksi_dryer', $livewire->ownerRecord->id)
                            ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                            ->pluck('id_pegawai')
                            ->toArray();

                        // Saring: Hanya tampilkan pegawai yang BELUM dipilih
                        return Pegawai::query()
                            ->whereNotIn('id', $usedPegawaiIds)
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}",
                            ]);
                    })
                    ->searchable()
                    ->preload() // Membantu agar filtering terasa instan
                    ->required(),

                Select::make('tugas')
                    ->label('Tugas')
                    ->options([
                        'operator' => 'Operator',
                        'asistenoperator' => 'Asisten Operator',
                        'dll' => 'Dll',
                    ])
                    ->required()
                    ->native(false),
            ]);
    }

    /**
     * Validasi Lapis Kedua: Mencegah duplikasi saat tombol simpan ditekan
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $exists = DetailPegawai::where('id_produksi_dryer', $this->ownerRecord->id)
            ->where('id_pegawai', $data['id_pegawai'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'id_pegawai' => 'Pegawai ini sudah terdaftar dalam laporan ini.',
            ]);
        }

        return $data;
    }

    /**
     * Validasi saat Update: Pastikan tidak bentrok dengan data lain
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $exists = DetailPegawai::where('id_produksi_dryer', $this->ownerRecord->id)
            ->where('id_pegawai', $data['id_pegawai'])
            ->where('id', '!=', $this->activeRecord->id) // Abaikan diri sendiri
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'id_pegawai' => 'Pegawai ini sudah digunakan di baris lain.',
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

                TextColumn::make('tugas')
                    ->searchable(),

                TextColumn::make('masuk')
                    ->time('H:i'),

                TextColumn::make('pulang')
                    ->time('H:i'),

                TextColumn::make('ijin')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->limit(50)
                    ->tooltip(fn($record) => $record->ket)
                    ->searchable()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),

                DeleteAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),

                Action::make('aturIjin')
                    ->label(fn($record) => $record->ijin ? 'Edit Ijin' : 'Tambah Ijin')
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        TextInput::make('ijin')->label('Ijin'),
                        Textarea::make('ket')->label('Keterangan'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'ijin' => $data['ijin'],
                            'ket' => $data['ket'],
                        ]);
                    })
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
                ]),
            ]);
    }
}
