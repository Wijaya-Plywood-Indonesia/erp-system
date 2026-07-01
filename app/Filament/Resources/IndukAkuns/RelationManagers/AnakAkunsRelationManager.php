<?php

namespace App\Filament\Resources\IndukAkuns\RelationManagers;

use App\Models\AnakAkun;
use Closure;
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
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AnakAkunsRelationManager extends RelationManager
{
    protected static string $relationship = 'anakAkuns';

    protected static ?string $title = 'Anak Akun';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── Kode Anak Akun ───────────────────────────────────────────
                TextInput::make('kode_anak_akun')
                    ->label('Kode Anak Akun')
                    ->required()
                    ->numeric()
                    ->rules([
                        function () {
                            return function (string $attribute, $value, Closure $fail) {
                                $induk = $this->ownerRecord;
                                if (!$induk) return;

                                $kodeAnak  = (int) $value;
                                $prefix    = substr($induk->kode_induk_akun, 0, 1);
                                $min       = ((int) $prefix) * 1000 + 1;
                                $max       = ((int) $prefix + 1) * 1000 - 1;

                                if (strlen((string) $value) !== 4) {
                                    $fail('Kode Anak Akun harus 4 digit.');
                                    return;
                                }
                                if ($kodeAnak < $min || $kodeAnak > $max) {
                                    $fail("Kode harus berada pada range {$min} – {$max}.");
                                }
                            };
                        },
                    ]),

                // ── Nama ─────────────────────────────────────────────────────
                TextInput::make('nama_anak_akun')
                    ->label('Nama Anak Akun')
                    ->required()
                    ->maxLength(255),

                // ── Parent (self-reference, hanya anak dari induk yg sama) ───
                Select::make('parent')
                    ->label('Parent')
                    ->relationship(
                        name: 'parentAkun',
                        titleAttribute: 'nama_anak_akun',
                        modifyQueryUsing: fn($query) => $query
                            ->where('id_induk_akun', $this->ownerRecord->id)
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn($record) => "[{$record->kode_anak_akun}] {$record->nama_anak_akun}"
                    )
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->placeholder('— Tanpa Parent —'),

                // ── Saldo Normal ──────────────────────────────────────────────
                Select::make('saldo_normal')
                    ->label('Saldo Normal')
                    ->options([
                        'debet'  => 'Debet',
                        'kredit' => 'Kredit',
                    ])
                    ->required()
                    ->native(false),

                // ── Status ───────────────────────────────────────────────────
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'aktif'     => 'Aktif',
                        'non-aktif' => 'Non-Aktif',
                    ])
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
            ->recordTitleAttribute('nama_anak_akun')
            ->columns([
                TextColumn::make('kode_anak_akun')
                    ->label('Kode')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nama_anak_akun')
                    ->label('Nama Anak Akun')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('parentAkun.nama_anak_akun')
                    ->label('Parent')
                    ->placeholder('-')
                    ->sortable(),

                BadgeColumn::make('saldo_normal')
                    ->label('Saldo Normal')
                    ->colors([
                        'success' => 'debet',
                        'danger'  => 'kredit',
                    ]),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn($state) => match ((string) $state) {
                        'aktif', '1'      => 'Aktif',
                        'non-aktif', '0'  => 'Non-Aktif',
                        default           => ucfirst($state),
                    })
                    ->colors([
                        'success' => fn($state) => in_array((string) $state, ['aktif', '1']),
                        'danger'  => fn($state) => in_array((string) $state, ['non-aktif', '0']),
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = Auth::id();
                        return $data;
                    }),
            ])
            ->actions([
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