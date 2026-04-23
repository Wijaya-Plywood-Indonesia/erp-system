<?php

namespace App\Filament\Resources\HargaKayus\Schemas;

use App\Models\HargaKayu;
use App\Models\JenisKayu;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;

class HargaKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('panjang')
                    ->label('Panjang')
                    ->options([
                        130 => '130',
                        260 => '260',
                    ])
                    ->required()
                    //->default(260)
                    ->native(false)
                    ->searchable()
                    ->placeholder('Pilih Panjang Kayu')
                    ->preload(),
                TextInput::make('diameter_terkecil')
                    ->label('Diameter Terkecil (cm)')
                    ->numeric(),
                TextInput::make('diameter_terbesar')
                    ->label('Diameter Terbesar (cm)')
                    ->numeric()
                    ->required()
                    ->rule(function (Get $get) {
                        return function (string $attribute, $value, \Closure $fail) use ($get) {

                            $jenis = $get('id_jenis_kayu');
                            $grade = $get('grade');
                            $panjang = $get('panjang');
                            $min = $get('diameter_terkecil');
                            $max = $value;

                            if (!$jenis || !$grade || !$panjang || !$min || !$max) {
                                return;
                            }

                            $overlap = HargaKayu::query()
                                ->where('id_jenis_kayu', $jenis)
                                ->where('grade', $grade)
                                ->where('panjang', $panjang)
                                ->where(function ($q) use ($min, $max) {
                                    $q->whereBetween('diameter_terkecil', [$min, $max])
                                        ->orWhereBetween('diameter_terbesar', [$min, $max])
                                        ->orWhere(function ($q2) use ($min, $max) {
                                            $q2->where('diameter_terkecil', '<=', $min)
                                                ->where('diameter_terbesar', '>=', $max);
                                        });
                                })
                                ->when($get('id'), fn($q) => $q->where('id', '!=', $get('id')))
                                ->first();

                            if ($overlap) {

                                // 🔥 NOTIFIKASI DETAIL HARGA YANG BENTROK
                                Notification::make()
                                    ->title('Harga sudah terdaftar!')
                                    ->body("
                        Terbentur dengan data:
                        <br>Diameter: {$overlap->diameter_terkecil} - {$overlap->diameter_terbesar} cm
                        <br>Harga: Rp " . number_format($overlap->harga_beli, 0, ',', '.') . "
                    ")
                                    ->danger()
                                    ->persistent() // agar tidak hilang sendiri
                                    ->send();

                                // ❌ GAGALKAN VALIDSASI
                                $fail('Range diameter ini bertumpukan dengan data harga yang sudah ada.');
                            }
                        };
                    }),
                TextInput::make('harga_beli')
                    ->label('Harga Beli Per m³')
                    ->numeric(),

                TextInput::make('harga_baru')
                    ->label('Harga Baru')
                    ->numeric(),

                Select::make('grade')
                    ->label('Grade')
                    ->options([
                        1 => 'Grade A',
                        2 => 'Grade B',
                    ])
                    ->required()
                    //  ->default(2)
                    ->native(false)
                    ->placeholder('Pilih Grade')
                    ->searchable(),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::query()
                            ->get()
                            ->mapWithKeys(function ($JenisKayu) {
                                return [
                                    $JenisKayu->id => "{$JenisKayu->kode_kayu} - {$JenisKayu->nama_kayu}",
                                ];
                            })
                    )
                    ->searchable()
                    // ->default(4)
                    ->placeholder('Pilih Jenis Kayu')
                    ->required(),

                TextInput::make('updated_by')
                    ->label('Diperbarui Oleh')
                    // Simpan ID User yang sedang login ke database
                    ->default(fn() => Filament::auth()->id())
                    // Tampilkan Nama Role + Nama User sebagai label bantuan (Visual Saja)
                    ->formatStateUsing(function () {
                        $user = Filament::auth()->user();
                        if (!$user) return 'Tidak diketahui';

                        // Langsung mengambil nama user agar lebih mudah dicek
                        return $user->name;
                    })
                    ->disabled()
                    ->dehydrated(),
            ]);
    }
}
