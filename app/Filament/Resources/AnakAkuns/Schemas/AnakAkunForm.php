<?php

namespace App\Filament\Resources\AnakAkuns\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AnakAkunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_induk_akun')
                    ->relationship('indukAkun', 'nama_induk_akun')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->kode_induk_akun} - {$record->nama_induk_akun}")
                    ->searchable(['kode_induk_akun', 'nama_induk_akun'])
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $parent = \App\Models\IndukAkun::find($state);
                            if ($parent) {
                                $set('kode_anak_akun', $parent->kode_induk_akun);
                            }
                        }
                    }),

                TextInput::make('kode_anak_akun')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->rules(function ($get) {
                        return [
                            function (string $attribute, $value, $fail) use ($get) {
                                $parentId = $get('parent');
                                if ($parentId) {
                                    $parent = \App\Models\AnakAkun::find($parentId);
                                    if ($parent) {
                                        $prefix = rtrim($parent->kode_anak_akun, '0');
                                        if (!str_starts_with($value, $prefix)) {
                                            $fail("Kode anak akun harus diawali dengan '" . $prefix . "' (sesuai prefix parent '" . $parent->kode_anak_akun . "')");
                                        }
                                        if ($value === $parent->kode_anak_akun) {
                                            $fail("Kode anak akun tidak boleh sama dengan kode parent '" . $parent->kode_anak_akun . "'");
                                        }
                                    }
                                } else {
                                    $indukId = $get('id_induk_akun');
                                    if ($indukId) {
                                        $induk = \App\Models\IndukAkun::find($indukId);
                                        if ($induk && !str_starts_with($value, $induk->kode_induk_akun)) {
                                            $fail("Kode anak akun harus diawali dengan kode induk '" . $induk->kode_induk_akun . "'");
                                        }
                                    }
                                }
                            }
                        ];
                    }),

                TextInput::make('nama_anak_akun')
                    ->required(),

                Select::make('parent')
                    ->relationship('parentAkun', 'nama_anak_akun')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->kode_anak_akun} - {$record->nama_anak_akun}")
                    ->searchable(['kode_anak_akun', 'nama_anak_akun'])
                    ->preload()
                    ->placeholder('Tanpa Parent')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $parent = \App\Models\AnakAkun::find($state);
                            if ($parent) {
                                $set('kode_anak_akun', $parent->kode_anak_akun);
                            }
                        }
                    }),

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
                    ->required(),

                Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }
}
