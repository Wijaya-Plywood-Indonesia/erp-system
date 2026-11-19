<?php

namespace App\Filament\Resources\Bms\Schemas;

use App\Models\Bk;
use App\Models\Bm;
use App\Services\BkBm\NomorBkBmService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class BmForm
{
    public static function configure(Schema $schema): Schema
    {
        $saran = function (Get $get, Set $set) {
            $set('no_nota', NomorBkBmService::saran($get('kategori') ?: 'bm', Carbon::parse($get('tanggal'))));
        };

        return $schema->components([
            // Tipe hanya dipilih saat membuat. BMK/BMR dibuat otomatis oleh sistem, jadi tidak ada di pilihan.
            Select::make('kategori')->label('Tipe Nota')
                ->options(['bm' => 'BM – Barang Masuk (umum / dari pabrik sendiri)', 'bml' => 'BML – Barang Masuk dari Luar (beli / kembali dari BKL)'])
                ->default('bm')->required()->live()->native(false)
                ->visible(fn (string $operation) => $operation === 'create')
                ->afterStateUpdated(function (Get $get, Set $set) use ($saran) {
                    $set('id_bk_referensi', null);
                    $saran($get, $set);
                }),

            DatePicker::make('tanggal')->default(today())->required()->live()
                ->afterStateUpdated($saran),

            TextInput::make('no_nota')->label('No. Nota')->required()->maxLength(255)->extraInputAttributes(['style' => 'text-transform:uppercase'])->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                ->default(fn () => NomorBkBmService::saran('bm', today()))
                ->helperText('Terisi otomatis sebagai saran, boleh diubah.')
                ->unique(table: Bm::class, column: 'no_nota', ignoreRecord: true)
                ->validationMessages(['unique' => 'Nomor nota ini sudah terdaftar.']),

            Select::make('id_bk_referensi')->label('BKL asal (boleh dikosongkan)')
                ->helperText('Kosongkan bila barang dibeli dari luar. Pilih BKL bila ini barang kita yang kembali: BKL otomatis hijau saat BML divalidasi.')
                ->options(function (?Bm $record) {
                    return Bk::where('kategori', 'bkl')
                        ->where(function ($q) use ($record) {
                            $q->where(fn ($x) => $x->where('status', 'divalidasi')
                                ->whereDoesntHave('bmTerkait', fn ($b) => $b->where('kategori', 'bml')->when($record, fn ($y) => $y->where('id', '!=', $record->id))));
                            if ($record?->id_bk_referensi) {
                                $q->orWhere('id', $record->id_bk_referensi);
                            }
                        })
                        ->orderByDesc('id')->get()
                        ->mapWithKeys(fn ($b) => [$b->id => $b->no_nota . ' – ' . ($b->tujuan_nota ?: '-')]);
                })
                ->searchable()->native(false)
                ->visible(fn (Get $get, ?Bm $record) => ($get('kategori') ?? $record?->kategori) === 'bml'),

            TextInput::make('dari')->label('Dari')->maxLength(255)
                ->datalist(fn () => \App\Models\Customer::pluck('nama')->toArray()),
            Textarea::make('keterangan')->rows(2),
        ]);
    }
}