<?php

namespace App\Filament\Resources\IndukAkuns\RelationManagers;

use App\Models\AnakAkun;
use App\Models\IndukAkun;
use Closure;
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
                TextInput::make('kode_anak_akun')
                    ->label('Kode Anak Akun')
                    ->required()
                    ->length(4)
                    ->numeric()
                    ->unique(ignoreRecord: true)
                    ->hint(function () {
                        $induk = $this->ownerRecord;

                        if (!$induk)
                            return 'Induk Akun tidak ditemukan.';

                        $kodeInduk = $induk->kode_induk_akun;

                        $prefix = substr($kodeInduk, 0, 1);
                        $min = ((int) $prefix) * 1000 + 1;
                        $max = ((int) $prefix + 1) * 1000 - 1;

                        return "Kode induk: {$kodeInduk}. Range valid anak: {$min} – {$max}.";
                    })
                    ->rule(function () {
                        return function (string $attribute, $value, Closure $fail) {
                            $induk = $this->ownerRecord;

                            if (!$induk)
                                return;

                            $kodeInduk = (int) $induk->kode_induk_akun;
                            $kodeAnak = (int) $value;

                            // Validasi 4 digit
                            if (strlen($value) !== 4) {
                                $fail('Kode Anak Akun harus 4 digit.');
                                return;
                            }

                            // Harus lebih besar dari induk
                            if ($kodeAnak <= $kodeInduk) {
                                $fail('Kode anak akun harus lebih besar dari kode induk.');
                                return;
                            }

                            // Hitung range
                            $prefix = substr($induk->kode_induk_akun, 0, 1);
                            $min = ((int) $prefix) * 1000 + 1;
                            $max = ((int) $prefix + 1) * 1000 - 1;

                            if ($kodeAnak < $min || $kodeAnak > $max) {
                                $fail("Kode anak akun harus berada pada range {$min} – {$max}.");
                                return;
                            }
                        };
                    }),

                TextInput::make('nama_anak_akun')
                    ->label('Nama Anak Akun')
                    ->required()
                    ->maxLength(255),

                Select::make('parent')
                    ->label('Parent')
                    ->relationship(
                        name: 'parentAkun',
                        titleAttribute: 'nama_anak_akun',
                        modifyQueryUsing: function ($query) {
                            $indukId = $this->ownerRecord->id;

                            return $query->where('id_induk_akun', $indukId);
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'aktif' => 'Aktif',
                        'non-aktif' => 'Non-Aktif',
                    ])
                    ->default('aktif')
                    ->required()
                    ->native(false),

                Textarea::make('keterangan')
                    ->label('Deskripsi')
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
                    ->label('Kode Akun')
                    ->sortable()
                    ->searchable(),


                TextColumn::make('parentAkun.nama_anak_akun')
                    ->label('Parent')
                    ->placeholder('-')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nama_anak_akun')
                    ->label('Nama Anak Akun')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        // Antisipasi jika DB masih berisi 0/1 atau string aktif
                        if ($state === '1' || $state === 1)
                            return 'Aktif';
                        if ($state === '0' || $state === 0)
                            return 'Non-Aktif';
                        return ucfirst($state);
                    })
                    ->color(fn($state): string => match ((string) $state) {
                        'aktif', '1' => 'success',
                        'non-aktif', '0' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Menggunakan Auth::id() agar IDE tidak bingung
                        $data['created_by'] = Auth::id();
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
