<?php

namespace App\Filament\Resources\Bms\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use App\Models\Bm;
use App\Services\BkBm\NomorBkBmService;
use Illuminate\Support\Carbon;

class BmForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('tanggal')->default(today())->required()->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => $set('no_nota', NomorBkBmService::saran('bm', Carbon::parse($get('tanggal'))))),
            TextInput::make('no_nota')->label('No. Nota')->required()->maxLength(255)->extraInputAttributes(['style' => 'text-transform:uppercase'])->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                ->default(fn () => NomorBkBmService::saran('bm', today()))
                ->helperText('Terisi otomatis sebagai saran, boleh diubah.')
                ->unique(table: Bm::class, column: 'no_nota', ignoreRecord: true)
                ->validationMessages(['unique' => 'Nomor nota ini sudah terdaftar.']),
            TextInput::make('dari')->label('Dari')->maxLength(255)
                ->datalist(fn () => \App\Models\Customer::pluck('nama')->toArray()),
            Textarea::make('keterangan')->rows(2),
        ]);
    }
}
