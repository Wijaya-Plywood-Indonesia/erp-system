<?php

namespace App\Filament\Resources\JurnalUmums\Schemas;

use App\Models\AnakAkun;
use App\Models\JurnalUmum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class JurnalUmumForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tgl')
                    ->label('Tanggal')
                    ->default(today())
                    ->required(),
                TextInput::make('jurnal')
                    ->label('Kode Jurnal')
                    ->numeric()
                    ->required()
                    ->default(function () {
                        $last = JurnalUmum::max('jurnal');
                        return $last ? $last + 1 : 1;
                    })
                    ->hint(function () {
                        $last = JurnalUmum::max('jurnal');
                        return $last
                            ? "Kode jurnal terakhir: {$last}"
                            : "Belum ada jurnal sebelumnya";
                    }),

                Select::make('no_akun')
                    ->label('No Akun')
                    ->options(function () {

                        $options = [];

                        $anakAkuns = AnakAkun::with([
                            'subAnakAkuns' => fn($q) => $q->orderBy('kode_sub_anak_akun')
                        ])
                            ->orderBy('kode_anak_akun')
                            ->get();

                        foreach ($anakAkuns as $anak) {

                            $indukKode = $anak->indukAkun?->kode_induk_akun ?? '-';

                            // Jika tidak ada Sub Anak → tambahkan ".00"
                            if ($anak->subAnakAkuns->isEmpty()) {

                                $kode = "{$anak->kode_anak_akun}.00";

                                $options[$kode] = "{$kode} — {$anak->nama_anak_akun}";
                            }

                            // Jika ada Sub Anak → tampilkan semuanya
                            foreach ($anak->subAnakAkuns as $sub) {

                                $kode = "{$sub->kode_sub_anak_akun}";

                                $options[$kode] = "{$kode} — {$sub->nama_sub_anak_akun}";
                            }
                        }

                        return $options;
                    })
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('no-dokumen')
                    ->label('No Dokumen'),

                TextInput::make('mm')
                    ->label('mm (tebal plywood')
                    ->suffix('mm')
                    ->numeric(),

                TextInput::make('nama')
                    ->label('Nama'),

                TextInput::make('keterangan')
                    ->label('Keterangan'),

                Select::make('map')
                    ->label('Map (D/K)')
                    ->default('D')
                    ->options([
                        'D' => 'Debet',
                        'K' => 'Kredit',
                    ])
                    ->required()
                    ->native(false),

                Select::make('hit_kbk')
                    ->label('Kubikasi / Banyak')
                    ->default('b')
                    ->options([
                        'k' => 'Kubikasi (m³/k)',
                        'b' => 'Banyak (b)',
                    ])
                    ->required()
                    ->native(false),

                TextInput::make('banyak')
                    ->label('Banyak')
                    ->numeric(),

                TextInput::make('m3')
                    ->label('M3')
                    ->numeric(),

                TextInput::make('harga')
                    ->label('Harga')
                    ->prefix('Rp')
                    ->dehydrateStateUsing(fn($state) => str_replace('.', '', $state))
                    ->numeric(),


                TextInput::make('created_by')
                    ->label('Dibuat Oleh')
                    ->default(fn() => Auth::user()->name)
                    ->readOnly(), // <-- boleh dikirim ke database

                TextInput::make('status')
                    ->label('Status')
                    ->dehydrated()
                    ->default('Belum Sinkron')   // masuk ke database
                    ->readOnly(),     // user tidak bisa edit

            ]);
    }
}
