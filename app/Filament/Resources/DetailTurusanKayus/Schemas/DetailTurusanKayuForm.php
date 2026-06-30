<?php

namespace App\Filament\Resources\DetailTurusanKayus\Schemas;

use App\Models\DetailTurusanKayu;
use App\Models\JenisKayu;
use App\Models\Lahan;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class DetailTurusanKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                |--------------------------------------------------------------------------
                | NOMOR URUT (SUDAH BENAR)
                |--------------------------------------------------------------------------
                */
                TextInput::make('nomer_urut')
                    ->label('Nomor')
                    ->numeric()
                    ->required()
                    ->default(function (callable $get, $livewire) {
                        $parent = $livewire->ownerRecord;

                        if (!$parent)
                            return 1;

                        $last = DetailTurusanKayu::where('id_kayu_masuk', $parent->id)
                            ->max('nomer_urut');

                        return $last ? $last + 1 : 1;
                    })
                    ->rules(function ($get, $livewire, $record) {
                        $parent = $livewire->ownerRecord;

                        if (!$parent)
                            return [];

                        return [
                            Rule::unique('detail_turusan_kayus', 'nomer_urut')
                                ->where('id_kayu_masuk', $parent->id)
                                ->where('lahan_id', $get('lahan_id'))
                                ->ignore($record?->id),
                        ];
                    })
                    ->validationMessages([
                        'unique' => 'Nomor ini sudah digunakan pada kayu masuk dan lahan yang sama.',
                    ]),

                /*
                |--------------------------------------------------------------------------
                | LAHAN
                |--------------------------------------------------------------------------
                */
                Select::make('lahan_id')
                    ->label('Lahan')
                    ->options(
                        Lahan::get()
                            ->mapWithKeys(fn($lahan) => [
                                $lahan->id => "{$lahan->kode_lahan} - {$lahan->nama_lahan}",
                            ])
                    )
                    ->default(function ($livewire) {
                        $parent = $livewire->ownerRecord;

                        if (!$parent)
                            return 1;

                        return DetailTurusanKayu::where('id_kayu_masuk', $parent->id)
                            ->latest('id')
                            ->value('lahan_id') ?? 1;
                    })
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (!$state)
                            return $set('panjang', 0);

                        $lahan = Lahan::find($state);

                        if (!$lahan)
                            return $set('panjang', 0);

                        $nama = strtolower($lahan->nama_lahan ?? '');

                        if (str_contains($nama, '130'))
                            return $set('panjang', 130);
                        if (str_contains($nama, '260'))
                            return $set('panjang', 260);

                        $last = DetailTurusanKayu::where('lahan_id', $state)
                            ->latest('id')
                            ->value('panjang');

                        return $set('panjang', $last ?? 0);
                    }),

                /*
                |--------------------------------------------------------------------------
                | PANJANG
                |--------------------------------------------------------------------------
                */
                Select::make('panjang')
                    ->label('Panjang')
                    ->options([
                        130 => '130 cm',
                        260 => '260 cm',
                        0 => 'Tidak Diketahui',
                    ])
                    ->required()
                    ->default(function ($livewire) {
                        $parent = $livewire->ownerRecord;

                        if (!$parent)
                            return 0;

                        return DetailTurusanKayu::where('id_kayu_masuk', $parent->id)
                            ->latest('id')
                            ->value('panjang') ?? 0;
                    })
                    ->searchable()
                    ->native(false),

                /*
                |--------------------------------------------------------------------------
                | JENIS KAYU
                |--------------------------------------------------------------------------
                */
                Select::make('jenis_kayu_id')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::get()
                            ->mapWithKeys(fn($x) => [
                                $x->id => "{$x->kode_kayu} - {$x->nama_kayu}",
                            ])
                    )
                    ->default(function ($livewire) {
                        $parent = $livewire->ownerRecord;

                        if (!$parent)
                            return 1;

                        return DetailTurusanKayu::where('id_kayu_masuk', $parent->id)
                            ->latest('id')
                            ->value('jenis_kayu_id') ?? 1;
                    })
                    ->searchable()
                    ->required(),

                /*
                |--------------------------------------------------------------------------
                | GRADE
                |--------------------------------------------------------------------------
                */
                Select::make('grade')
                    ->label('Grade')
                    ->options([
                        1 => 'Grade A',
                        2 => 'Grade B',
                    ])
                    ->required()
                    ->default(function ($livewire) {
                        $parent = $livewire->ownerRecord;

                        if (!$parent)
                            return 1;

                        return DetailTurusanKayu::where('id_kayu_masuk', $parent->id)
                            ->latest('id')
                            ->value('grade') ?? 1;
                    })
                    ->native(false)
                    ->searchable()
                    ->reactive()
                    ->afterStateHydrated(function ($state, $set) {
                        $saved =
                            request()->cookie('filament_local_storage_detail_kayu_masuk.grade')
                            ?? optional(json_decode(request()->header('X-Filament-Local-Storage'), true))['detail_kayu_masuk.grade']
                            ?? null;

                        if ($saved && in_array($saved, [1, 2])) {
                            $set('grade', (int) $saved);
                        }
                    })
                    ->afterStateUpdated(
                        fn($state) =>
                        cookie()->queue('filament_local_storage_detail_kayu_masuk.grade', $state, 60 * 24 * 30)
                    ),

                /*
                |--------------------------------------------------------------------------
                | DIAMETER
                |--------------------------------------------------------------------------
                */
                TextInput::make('diameter')
                    ->label('Diameter (cm)')
                    ->placeholder('Masukkan diameter kayu')
                    ->required()
                    ->numeric()
            ]);
    }
}
