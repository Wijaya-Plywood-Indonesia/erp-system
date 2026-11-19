<?php

namespace App\Filament\Resources\Bks\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use App\Models\Bk;
use App\Services\BkBm\NomorBkBmService;
use Illuminate\Support\Carbon;

class BkForm
{
    public static function configure(Schema $schema): Schema
    {
        $kategori = collect(config('bkbm.kategori_bk'))->filter(fn ($k) => $k['aktif'])->map(fn ($k) => $k['label'])->all();

        return $schema->components([
            Select::make('kategori')->label('Kategori')
                ->options(fn ($record) => $record ? collect(config('bkbm.kategori_bk'))->map(fn ($k) => $k['label'])->all() : $kategori)->required()->native(false)->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::saran($get, $set))
                ->disabled(fn ($record) => $record !== null)->dehydrated(fn ($record) => $record === null),
            DatePicker::make('tanggal')->default(today())->required()->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::saran($get, $set)),
            TextInput::make('no_nota')->label('No. Nota')->required()->maxLength(255)->extraInputAttributes(['style' => 'text-transform:uppercase'])
                ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                ->helperText('Terisi otomatis sebagai saran, boleh diubah.')
                ->unique(table: Bk::class, column: 'no_nota', ignoreRecord: true)
                ->validationMessages(['unique' => 'Nomor nota ini sudah terdaftar.']),
            TextInput::make('tujuan_nota')->label('Kepada')->required()->maxLength(255)
                ->datalist(fn () => \App\Models\Customer::pluck('nama')->toArray()),
            TextInput::make('alamat')->maxLength(255),
            Textarea::make('keterangan')->rows(2),
        ]);
    }

    private static function saran(Get $get, Set $set): void
    {
        if ($get('kategori') && $get('tanggal')) {
            $set('no_nota', NomorBkBmService::saran($get('kategori'), Carbon::parse($get('tanggal'))));
        }
    }
}
