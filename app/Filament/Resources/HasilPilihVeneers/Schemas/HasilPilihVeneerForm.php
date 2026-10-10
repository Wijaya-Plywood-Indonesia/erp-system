<?php

namespace App\Filament\Resources\HasilPilihVeneers\Schemas;

use App\Models\Grade;
use App\Models\ModalPilihVeneer;
use App\Models\PegawaiPilihVeneer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class HasilPilihVeneerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('id_modal_pilih_veneer')
                    ->label('Pilih Barang Modal')
                    ->required()
                    ->searchable()
                    ->live()
                    ->options(function ($livewire, $record) {
                        $produksi = $livewire->getOwnerRecord();
                        if (! $produksi) {
                            return [];
                        }

                        return ModalPilihVeneer::query()
                            ->where('id_produksi_pilih_veneer', $produksi->id)
                            ->with(['stokVeneerJadi.jenisKayu', 'hasilPilihVeneers', 'ukuran', 'jenisKayu'])
                            ->get()
                            ->filter(function ($item) use ($record) {
                                // Poin 3: modal yang sisanya 0 disembunyikan dari pilihan,
                                // kecuali modal itu memang yang sedang dipakai record ini
                                $sisa = $item->sisaBelumDipakai($record?->id);

                                return $sisa > 0 || ($record && $record->id_modal_pilih_veneer == $item->id);
                            })
                            ->mapWithKeys(function ($item) use ($record) {
                                $sisa = $item->sisaBelumDipakai($record?->id);

                                if ($item->id_stok_veneer_jadi) {
                                    $stok = $item->stokVeneerJadi;
                                    if (!$stok) {
                                        return [$item->id => "Palet {$item->no_palet} [Veneer Jadi] · Data Stok N/A [Modal: {$item->jumlah} · Sisa: {$sisa}]"];
                                    }
                                    
                                    $panjang = floatval($stok->panjang);
                                    $lebar = floatval($stok->lebar);
                                    $tebal = floatval($stok->tebal);
                                    $dimensi = "{$panjang} x {$lebar} x {$tebal}";
                                    $kayu = $stok->jenisKayu?->nama_kayu ?? '-';
                                    $kwAsal = $stok->kw_grade;
                                    $jenisVeneer = 'Veneer Jadi';
                                } else {
                                    $panjang = floatval($item->ukuran?->panjang ?? 0);
                                    $lebar = floatval($item->ukuran?->lebar ?? 0);
                                    $tebal = floatval($item->ukuran?->tebal ?? 0);
                                    $dimensi = "{$panjang} x {$lebar} x {$tebal}";
                                    $kayu = $item->jenisKayu?->nama_kayu ?? '-';
                                    $kwAsal = $item->kw;
                                    $jenisVeneer = 'Veneer Kering';
                                }

                                $label = "[{$jenisVeneer}] Palet {$item->no_palet} · {$kayu} · {$dimensi} [KW Asal: {$kwAsal}] · Modal: {$item->jumlah} · Sisa: {$sisa}";

                                return [$item->id => $label];
                            });
                    })
                    ->afterStateUpdated(function (Set $set) {
                        $set('jumlah', null);
                    })
                    ->columnSpanFull(),

                Select::make('jenis_veneer')->visibleOn('edit')
                    ->label('Jenis Veneer Output')
                    ->options([
                        'jadi' => 'Veneer Jadi',
                        'kering' => 'Veneer Kering',
                    ])
                    ->default('jadi')
                    ->required(),
                Select::make('kw')->visibleOn('edit')
                    ->label('KW Hasil')
                    ->options(\App\Models\Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade'))
                    ->required(),
                \Filament\Forms\Components\TextInput::make('no_palet')->visibleOn('edit')
                    ->label('Nomor Palet Hasil')
                    ->numeric()
                    ->required(),
                \Filament\Forms\Components\TextInput::make('jumlah')->visibleOn('edit')
                    ->label('Jumlah Hasil')
                    ->numeric()
                    ->required(),

                \Filament\Forms\Components\Repeater::make('hasil_list')->visibleOn('create')
                    ->label('Daftar Hasil')
                    ->schema([
                        Select::make('jenis_veneer')
                            ->label('Jenis Veneer Output')
                            ->options([
                                'jadi' => 'Veneer Jadi',
                                'kering' => 'Veneer Kering',
                            ])
                            ->default('jadi')
                            ->required(),
                        Select::make('kw')
                            ->label('KW Hasil')
                            ->options(\App\Models\Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade'))
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('no_palet')
                            ->label('Nomor Palet Hasil')
                            ->numeric()
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('jumlah')
                            ->label('Jumlah Hasil')
                            ->numeric()
                            ->required(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                \Filament\Forms\Components\Repeater::make('tambahan_hasil')->visibleOn('edit')
                    ->label('Tambah Palet Hasil Lainnya (Opsional)')
                    ->schema([
                        Select::make('jenis_veneer')
                            ->label('Jenis Veneer Output')
                            ->options([
                                'jadi' => 'Veneer Jadi',
                                'kering' => 'Veneer Kering',
                            ])
                            ->default('jadi')
                            ->required(),
                        Select::make('kw')
                            ->label('KW Hasil')
                            ->options(\App\Models\Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade'))
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('no_palet')
                            ->label('Nomor Palet Hasil')
                            ->numeric()
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('jumlah')
                            ->label('Jumlah Hasil')
                            ->numeric()
                            ->required(),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
            ]);
    }
}
