<?php

namespace App\Filament\Resources\ModalPilihVeneers\Schemas;

use Filament\Schemas\Schema;
use App\Models\JenisKayu;
use App\Models\StokVeneerJadi;
use App\Models\Ukuran;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ModalPilihVeneerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Relasi ke Kayu Masuk (Optional)
                Select::make('id_stok_veneer_jadi')
                    ->label('Pilih Stok Veneer')
                    ->options(function ($record) {
                        return StokVeneerJadi::query()
                            ->with('jenisKayu')
                            ->where('stok_lembar', '>', 0)
                            ->when($record?->id_stok_veneer_jadi, fn($q) => $q->orWhere('id', $record->id_stok_veneer_jadi))
                            ->get()
                            ->mapWithKeys(function ($stok) use ($record) {
                                $dimensi = "{$stok->panjang} x {$stok->lebar} x {$stok->tebal}";
                                $kayu = $stok->jenisKayu?->nama_kayu ?? '-';

                                $stokTersedia = (float) $stok->stok_lembar;
                                if ($record && $record->id_stok_veneer_jadi == $stok->id) {
                                    $stokTersedia += (float) $record->jumlah;
                                }

                                $label = "{$kayu} · {$dimensi} · KW: {$stok->kw_grade} [Sisa: {$stokTersedia} lbr]";

                                return [$stok->id => $label];
                            });
                    })
                    ->searchable()
                    ->live() // Mandatory agar merespons secara real-time
                    ->required()
                    ->afterStateUpdated(function ($state, Set $set, $record) {
                        if (!$state) {
                            $set('kw', null);
                            $set('sisa_stok_label', null);
                            return;
                        }

                        $stok = StokVeneerJadi::find($state);
                        if ($stok) {
                            $set('kw', $stok->kw_grade);
                            $stokTersedia = (float) $stok->stok_lembar;
                            if ($record && $record->id_stok_veneer_jadi == $stok->id) {
                                $stokTersedia += (float) $record->jumlah;
                            }
                            $set('sisa_stok_label', $stokTersedia);
                        }
                    }),

                // 2. FIELDS HIDDEN / DATA UTAMANYA YANG AKAN DISIMPAN KE DB
                Hidden::make('id_jenis_kayu')->dehydrated(),
                Hidden::make('panjang')->dehydrated(),
                Hidden::make('lebar')->dehydrated(),
                Hidden::make('tebal')->dehydrated(),
                Hidden::make('kw')->dehydrated(),

                // 3. INFORMASI PENDUKUNG FORM
                TextInput::make('no_palet') // Mengganti no_palet agar konsisten
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),

                TextInput::make('sisa_stok_label')
                    ->label('Stok Tersedia (Lembar)')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('jumlah')
                    ->label('Jumlah Digunakan')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 100')
                    ->rules([
                        fn(Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $idStok = $get('id_stok_veneer_jadi');
                            if (!$idStok) return;

                            $stok = StokVeneerJadi::find($idStok);
                            if ($stok && (float)$value > (float)$stok->stok_lembar) {
                                $fail("Jumlah input melebihi stok yang tersedia ({$stok->stok_lembar} lembar).");
                            }
                        },
                    ]),
            ]);
    }
}
