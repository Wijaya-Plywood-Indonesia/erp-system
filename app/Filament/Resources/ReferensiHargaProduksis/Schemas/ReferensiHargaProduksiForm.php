<?php

namespace App\Filament\Resources\ReferensiHargaProduksis\Schemas;

use App\Models\Grade;
use App\Models\JenisKayu;
use App\Models\KategoriBarang;
use App\Models\SubAnakAkun;
use App\Models\Ukuran;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class ReferensiHargaProduksiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama')
                    ->maxLength(255)
                    ->placeholder('Masukkan nama referensi (opsional)'),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::query()
                            ->get()
                            ->mapWithKeys(fn($j) => [$j->id => "{$j->kode_kayu} - {$j->nama_kayu}"])
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder('Pilih Jenis Kayu'),

                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->options(
                        Ukuran::query()
                            ->get()
                            ->mapWithKeys(fn($u) => [$u->id => "{$u->panjang}mm x {$u->lebar}mm x {$u->tebal}mm"])
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder('Pilih Ukuran (opsional)'),

                Select::make('id_kategori_barang')
                    ->label('Kategori Barang')
                    ->options(
                        KategoriBarang::query()
                            ->get()
                            ->mapWithKeys(fn($k) => [$k->id => $k->nama_kategori])
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder('Pilih Kategori Barang')
                    ->live()
                    ->afterStateUpdated(fn($set) => $set('id_grade', null)),

                Select::make('id_grade')
                    ->label('Grade')
                    ->options(function ($get) {
                        $idKategori = $get('id_kategori_barang');

                        return Grade::query()
                            ->when($idKategori, fn($q) => $q->where('id_kategori_barang', $idKategori))
                            ->get()
                            ->mapWithKeys(fn($g) => [$g->id => $g->nama_grade]);
                    })
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder('Pilih Grade'),

                TextInput::make('kw_min')
                    ->label('KW Min')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5)
                    ->placeholder('1'),

                TextInput::make('kw_max')
                    ->label('KW Max')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5)
                    ->placeholder('5'),

                TextInput::make('t_min')
                    ->label('Tebal Min (mm)')
                    ->numeric()
                    ->step(0.01)
                    ->placeholder('0.00'),

                TextInput::make('t_max')
                    ->label('Tebal Max (mm)')
                    ->numeric()
                    ->step(0.01)
                    ->placeholder('0.00'),

                TextInput::make('harga')
                    ->label('Harga Produksi')
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->formatStateUsing(fn($state) => $state ? number_format($state, 0, ',', '.') : null)
                    ->dehydrateStateUsing(fn($state) => blank($state) ? null : str_replace('.', '', $state))
                    ->placeholder('0'),

                Select::make('id_sub_anak_akun')
                    ->label('Sub Anak Akun')
                    ->options(
                        SubAnakAkun::query()
                            ->get()
                            ->mapWithKeys(fn($s) => [$s->id => "{$s->kode_sub_anak_akun} - {$s->nama_sub_anak_akun}"])
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder('Pilih Sub Anak Akun'),
                Hidden::make('created_by') // Sesuaikan dengan nama kolom di database Anda (bisa 'dibuat_oleh' atau 'created_by')
                    ->default(fn() => auth()->id())
                    ->dehydrated(fn($context) => $context === 'create'),
            ]);
    }
}
