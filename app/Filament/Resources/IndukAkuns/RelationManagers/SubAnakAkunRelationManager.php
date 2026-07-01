<?php

namespace App\Filament\Resources\IndukAkuns\RelationManagers;

use App\Models\AnakAkun;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SubAnakAkunRelationManager extends RelationManager
{
    protected static string $relationship = 'subAnakAkuns';
    protected static ?string $title = 'Sub-Anak Akun';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── Pilih Anak Akun ──────────────────────────────────────────
                Select::make('id_anak_akun')
                    ->label('Anak Akun')
                    ->options(function () {
                        return AnakAkun::where('id_induk_akun', $this->ownerRecord->id)
                            ->orderBy('kode_anak_akun')
                            ->get()
                            ->mapWithKeys(fn($a) => [
                                $a->id => "[{$a->kode_anak_akun}] {$a->nama_anak_akun}",
                            ]);
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                // ── Kode — user ketik HANYA suffix (01, 02, dst) ────────────
                // Field ini pakai nama 'kode_sub_anak_akun' supaya masuk $data,
                // tapi isinya masih berupa suffix saja.
                // Kode lengkap dirakit di mutateFormDataUsing.
                TextInput::make('kode_sub_anak_akun')
                    ->label('Kode Sub Anak Akun')
                    ->required()
                    ->maxLength(10)
                    ->prefix(function (Get $get) {
                        $anak = AnakAkun::find($get('id_anak_akun'));
                        return $anak ? $anak->kode_anak_akun . '.' : '—-';
                    })
                    ->live(debounce: 500) // debounce 500ms agar tidak query tiap ketik
                    ->afterStateUpdated(function ($state, Set $set, Get $get, $record) {
                        $anak = AnakAkun::find($get('id_anak_akun'));
                        if (!$anak || blank($state)) {
                            $set('kode_sudah_ada', false);
                            return;
                        }

                        $parts = strpos($state, '.') !== false ? explode('.', $state) : explode('-', $state);
                        $suffix = count($parts) > 1 ? end($parts) : $state;
                        $cleanState = ltrim(ltrim($suffix, '-'), '.');
                        if (is_numeric($cleanState) && strlen($cleanState) === 1) {
                            $cleanState = '0' . $cleanState;
                        }
                        $kode = $anak->kode_anak_akun . '.' . $cleanState;

                        // Cek unique, exclude record saat ini jika edit
                        $exists = \App\Models\SubAnakAkun::where('kode_sub_anak_akun', $kode)
                            ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                            ->exists();

                        $set('kode_sudah_ada', $exists);
                    })
                    ->hintColor(fn(Get $get) => $get('kode_sudah_ada') ? 'danger' : 'gray')
                    ->hint(function (Get $get) {
                        if ($get('kode_sudah_ada')) {
                            return '⚠ Kode ini sudah digunakan, pilih kode lain.';
                        }
                        return null;
                    })
                    ->afterStateHydrated(function ($component, $record) {
                        if (!$record) return;
                        $kode   = $record->kode_sub_anak_akun ?? '';
                        $parts  = strpos($kode, '.') !== false ? explode('.', $kode) : explode('-', $kode);
                        $suffix = count($parts) > 1 ? end($parts) : $kode;
                        $component->state($suffix);
                    }),

                // Field hidden untuk menyimpan state boolean
                \Filament\Forms\Components\Hidden::make('kode_sudah_ada')
                    ->default(false),

                // ── Nama ─────────────────────────────────────────────────────
                TextInput::make('nama_sub_anak_akun')
                    ->label('Nama Sub Anak Akun')
                    ->required()
                    ->maxLength(255),

                // ── Saldo Normal ──────────────────────────────────────────────
                Select::make('saldo_normal')
                    ->label('Saldo Normal')
                    ->options(['debet' => 'Debet', 'kredit' => 'Kredit'])
                    ->required()
                    ->native(false),

                // ── Status ───────────────────────────────────────────────────
                Select::make('status')
                    ->label('Status')
                    ->options(['aktif' => 'Aktif', 'non-aktif' => 'Non-Aktif'])
                    ->default('aktif')
                    ->required()
                    ->native(false),

                // ── Keterangan ───────────────────────────────────────────────
                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_sub_anak_akun')
            ->columns([
                TextColumn::make('kode_sub_anak_akun')
                    ->label('Kode')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nama_sub_anak_akun')
                    ->label('Nama Sub Anak Akun')
                    ->searchable(),

                TextColumn::make('anakAkun.nama_anak_akun')
                    ->label('Anak Akun')
                    ->sortable(),

                BadgeColumn::make('saldo_normal')
                    ->label('Saldo Normal')
                    ->colors(['success' => 'debet', 'danger' => 'kredit']),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn($state) => match ((string) $state) {
                        'aktif', '1'     => 'Aktif',
                        'non-aktif', '0' => 'Non-Aktif',
                        default          => ucfirst($state),
                    })
                    ->colors([
                        'success' => fn($state) => in_array((string) $state, ['aktif', '1']),
                        'danger'  => fn($state) => in_array((string) $state, ['non-aktif', '0']),
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (\Filament\Actions\CreateAction $action, array $data): array {
                        $anak   = AnakAkun::find($data['id_anak_akun']);
                        $raw    = $data['kode_sub_anak_akun'] ?? '';
                        $parts  = strpos($raw, '.') !== false ? explode('.', $raw) : explode('-', $raw);
                        $suffix = count($parts) > 1 ? end($parts) : $raw;
                        $suffix = ltrim(ltrim($suffix, '-'), '.');
                        if (is_numeric($suffix) && strlen($suffix) === 1) {
                            $suffix = '0' . $suffix;
                        }
                        $kode   = $anak ? $anak->kode_anak_akun . '.' . $suffix : $suffix;

                        if (\App\Models\SubAnakAkun::where('kode_sub_anak_akun', $kode)->exists()) {
                            Notification::make()
                                ->title('Kode sudah digunakan')
                                ->danger()
                                ->send();
                            $action->halt(); // 👈 Sekarang aman, menggunakan $action->halt(), bukan $this->halt()
                        }

                        $data['kode_sub_anak_akun'] = $kode;
                        $data['created_by']         = Auth::id();
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $anak   = AnakAkun::find($data['id_anak_akun']);
                        $raw    = $data['kode_sub_anak_akun'] ?? '';
                        $parts  = strpos($raw, '.') !== false ? explode('.', $raw) : explode('-', $raw);
                        $suffix = count($parts) > 1 ? end($parts) : $raw;
                        $suffix = ltrim(ltrim($suffix, '-'), '.');
                        if (is_numeric($suffix) && strlen($suffix) === 1) {
                            $suffix = '0' . $suffix;
                        }

                        // Rakit kode lengkap: 2210.02
                        $data['kode_sub_anak_akun'] = $anak
                            ? $anak->kode_anak_akun . '.' . $suffix
                            : $suffix;

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
