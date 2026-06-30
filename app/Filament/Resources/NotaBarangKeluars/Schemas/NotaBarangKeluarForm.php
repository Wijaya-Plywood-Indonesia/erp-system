<?php

namespace App\Filament\Resources\NotaBarangKeluars\Schemas;

use App\Services\NomorNotaService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class NotaBarangKeluarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->default(today())
                    ->required()
                    ->live(),

                Select::make('tipe_nota')
                    ->label('Tipe Nota')
                    ->options([
                        'BK'  => 'BK – Barang Keluar (Pabrik)',
                        'BKL' => 'BKL – Barang Keluar (Lain-lain)',
                    ])
                    ->required()
                    ->native(false),

                TextInput::make('no_nota')
                    ->label('No. Nota')
                    ->required()
                    ->maxLength(255),

                TextInput::make('tujuan_nota')
                    ->label('Kepada')
                    ->required(),

                Hidden::make('dibuat_oleh')
                    ->default(fn() => auth()->id()),

                TextInput::make('dibuat_oleh_display')
                    ->label('Dibuat Oleh')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if ($record) {
                            $component->state($record->pembuat?->name ?? '-');
                        } else {
                            $component->state(auth()->user()->name);
                        }
                    }),
            ]);
    }

    /**
     * Generate nomor nota dan set ke field display + hidden.
     * Dipanggil setiap kali tipe_nota atau tanggal berubah.
     */
    // private static function refreshNomorNota(Get $get, Set $set): void
    // {
    //     $tipe    = $get('tipe_nota');
    //     $tanggal = $get('tanggal');

    //     if (! $tipe || ! $tanggal) {
    //         $set('no_nota_display', null);
    //         $set('no_nota', null);
    //         return;
    //     }

    //     // 1. Generate nomor nota default bawaan Service (Misal: BKL-1231-0608)
    //     $nomorOriginal = NomorNotaService::generate(
    //         $tipe,
    //         Carbon::parse($tanggal),
    //         \App\Models\NotaBarangKeluar::class
    //     );

    //     // 2. Manipulasi format: Ganti tanda hubung pertama setelah kode dengan spasi
    //     // Di sini kita batasi hanya me-replace 1 kali saja menggunakan Str::replaceFirst
    //     $nomorCustom = Str::replaceFirst('-', ' ', $nomorOriginal);

    //     // 3. Paksa outputnya kecil semua (bkl 1231-0608)
    //     $nomorFinal = Str::lower($nomorCustom);

    //     $set('no_nota_display', $nomorFinal);
    //     $set('no_nota', $nomorFinal);
    // }
}
