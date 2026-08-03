<?php

namespace App\Filament\Resources\PengajuanBarangs\Schemas;

use App\Models\BarangUmum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PengajuanBarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('diajukan_oleh')
                    ->default(fn() => Auth::id()),

                Grid::make(2)->schema([
                    DatePicker::make('tanggal')
                        ->label('Tanggal')
                        ->default(now())
                        ->required(),

                    TextInput::make('lokasi_penggunaan')
                        ->label('Lokasi Penggunaan')
                        ->placeholder('mis. Gerbang, Rotary, Hotpress, dll')
                        ->required()
                        ->maxLength(255),
                ]),

                Repeater::make('items')
                    ->relationship()
                    ->label('Barang yang Diajukan')
                    // ── Layout tabel: tiap item jadi 1 baris ringkas, bukan kartu besar ──
                    ->table([
                        TableColumn::make('Barang'),
                        TableColumn::make('Jumlah')
                            ->width('160px'),
                    ])
                    ->schema([
                        Select::make('id_barang_umum')
                            ->label('Barang')
                            ->options(fn() => BarangUmum::orderBy('nama_barang')->get()
                                ->mapWithKeys(fn($b) => [$b->id => "{$b->nama_barang} ({$b->satuan})"]))
                            ->searchable()
                            ->required()
                            ->preload(),

                        TextInput::make('jumlah')
                            ->label('Jumlah')
                            ->numeric()
                            ->minValue(0.0001)
                            ->required(),
                    ])
                    ->minItems(1)
                    ->required()
                    ->addActionLabel('Tambah Barang')
                    // biar list panjang tetap nyaman discroll, tidak bikin halaman memanjang tak terkendali
                    ->defaultItems(1)
                    ->reorderable(false)
                    ->columnSpanFull(),

                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(3)
                    ->columnSpanFull(),

                FileUpload::make('foto')
                    ->label('Foto (opsional)')
                    ->image()
                    ->directory('pengajuan-barang')
                    ->columnSpanFull(),
            ]);
    }
}