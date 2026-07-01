<?php

namespace App\Filament\Resources\SubAnakAkuns\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SubAnakAkunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_anak_akun')
                    ->relationship('anakAkun', 'nama_anak_akun')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->kode_anak_akun} - {$record->nama_anak_akun}")
                    ->searchable(['kode_anak_akun', 'nama_anak_akun'])
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $parent = \App\Models\AnakAkun::find($state);
                            if ($parent) {
                                $set('kode_sub_anak_akun', $parent->kode_anak_akun . '.');
                            }
                        }
                    }),

                TextInput::make('kode_sub_anak_akun')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->mutateStateForValidationUsing(function ($state) {
                        if (blank($state)) return $state;
                        // Support both dot and dash separators for validation mutation
                        $separator = strpos($state, '.') !== false ? '.' : '-';
                        $parts = explode($separator, $state);
                        if (count($parts) === 2) {
                            $suffix = $parts[1];
                            if (is_numeric($suffix) && strlen($suffix) === 1) {
                                return $parts[0] . '.' . '0' . $suffix;
                            }
                            return $parts[0] . '.' . $suffix;
                        }
                        return $state;
                    })
                    ->dehydrateStateUsing(function ($state) {
                        if (blank($state)) return $state;
                        $separator = strpos($state, '.') !== false ? '.' : '-';
                        $parts = explode($separator, $state);
                        if (count($parts) === 2) {
                            $suffix = $parts[1];
                            if (is_numeric($suffix) && strlen($suffix) === 1) {
                                return $parts[0] . '.' . '0' . $suffix;
                            }
                            return $parts[0] . '.' . $suffix;
                        }
                        return $state;
                    })
                    ->rules(function ($get) {
                        return [
                            function (string $attribute, $value, $fail) use ($get) {
                                $parentId = $get('id_anak_akun');
                                if ($parentId) {
                                    $parent = \App\Models\AnakAkun::find($parentId);
                                    if ($parent) {
                                        $expectedPrefix = $parent->kode_anak_akun . '.';
                                        if (!str_starts_with($value, $expectedPrefix)) {
                                            $fail("Kode sub anak akun harus diawali dengan '{$expectedPrefix}'");
                                        }
                                        if ($value === $expectedPrefix) {
                                            $fail("Kode sub anak akun tidak boleh hanya berupa prefix.");
                                        }
                                    }
                                }
                            }
                        ];
                    }),

                TextInput::make('nama_sub_anak_akun')
                    ->required(),

                Select::make('saldo_normal')
                    ->options([
                        'debet' => 'Debet',
                        'kredit' => 'Kredit',
                    ])
                    ->required(),

                Select::make('status')
                    ->options([
                        'aktif' => 'Aktif',
                        'nonaktif' => 'Nonaktif',
                    ])
                    ->default('aktif')
                    ->required(),

                Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }
}
