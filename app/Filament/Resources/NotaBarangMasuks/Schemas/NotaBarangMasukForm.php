<?php

namespace App\Filament\Resources\NotaBarangMasuks\Schemas;

use App\Models\NotaBarangMasuk;
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

class NotaBarangMasukForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->default(today())
                    ->required(),

                Select::make('tipe_nota')
                    ->label('Tipe Nota')
                    ->options([
                        'BM'  => 'BM – Barang Masuk (Pabrik)',
                        'BML' => 'BML – Barang Masuk (Lain-lain)',
                    ])
                    ->required()
                    ->native(false),

                TextInput::make('no_nota')
                    ->label('No. Nota')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        table: NotaBarangMasuk::class,
                        column: 'no_nota',
                        ignoreRecord: true
                    )
                    ->validationMessages([
                        'unique' => 'Nomor nota ini sudah terdaftar di sistem. Silakan gunakan nomor lain.',
                    ]),

                TextInput::make('tujuan_nota')
                    ->label('Kepada')
                    ->required(),
                Hidden::make('dibuat_oleh')
                    ->default(fn() => auth()->id())
                    ->dehydrated(fn($context) => $context === 'create'),
                TextInput::make('dibuat_oleh_display')
                    ->label('Dibuat Oleh')
                    ->formatStateUsing(
                        fn($record) =>
                        $record?->dibuatOleh?->name ?? auth()->user()->name
                    )
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    // private static function refreshNomorNota(Get $get, Set $set): void
    // {
    //     $tipe    = $get('tipe_nota');
    //     $tanggal = $get('tanggal');

    //     if (! $tipe || ! $tanggal) {
    //         $set('no_nota_display', null);
    //         $set('no_nota', null);
    //         return;
    //     }

    //     // 1. Ambil format asli dari service (Misal: BML-1231-0608)
    //     $nomorOriginal = NomorNotaService::generateBarangMasuk($tipe, Carbon::parse($tanggal));

    //     // 2. Ganti tanda hubung pertama setelah kode tipe dengan spasi
    //     $nomorCustom = Str::replaceFirst('-', ' ', $nomorOriginal);

    //     // 3. Ubah menjadi huruf kecil semua (bml 1231-0608)
    //     $nomorFinal = Str::lower($nomorCustom);

    //     $set('no_nota_display', $nomorFinal);
    //     $set('no_nota', $nomorFinal);
    // }
}
