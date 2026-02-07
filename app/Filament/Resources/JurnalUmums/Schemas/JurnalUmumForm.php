<?php

namespace App\Filament\Resources\JurnalUmums\Schemas;

use App\Helpers\AkunHelper;
use App\Models\JurnalUmum;
use Filament\Forms\Components\{
    DatePicker,
    Radio,
    Repeater,
    Select,
    TextInput,
    Placeholder
};
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class JurnalUmumForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /* ================= HEADER ================= */
            Grid::make(3)->schema([
                DatePicker::make('tgl')
                    ->label('Tanggal')
                    ->default(today())
                    ->required(),

                TextInput::make('jurnal')
                    ->label('Kode Jurnal')
                    ->default(fn () => (JurnalUmum::max('jurnal') ?? 0) + 1)
                    ->readOnly(),

                Radio::make('mode')
                    ->label('Mode Input')
                    ->options([
                        'DK'  => 'D + K',
                        'DKK' => 'D + K + K',
                        'KDD' => 'K + D + D',
                    ])
                    ->default('DK')
                    ->live(),
            ]),

            /* ================= DEBIT & KREDIT ================= */
            Grid::make(2)->schema([

                /* ---------- DEBIT ---------- */
                Repeater::make('debits')
                    ->label('DEBIT')
                    ->schema([
                        Select::make('no_akun')
                            ->label('No Akun')
                            ->options(AkunHelper::debitAccounts())
                            ->searchable()
                            ->required(),

                        TextInput::make('keterangan'),

                        TextInput::make('jumlah')
                            ->numeric()
                            ->required(),
                    ])
                    ->minItems(fn ($get) =>
                        in_array($get('mode'), ['DK','DKK']) ? 1 : 2
                    )
                    ->maxItems(fn ($get) =>
                        $get('mode') === 'KDD' ? 99 : 1
                    )
                    ->live(),

                /* ---------- KREDIT ---------- */
                Repeater::make('kredits')
                    ->label('KREDIT')
                    ->schema([
                        Select::make('no_akun')
                            ->label('No Akun')
                            ->options(AkunHelper::kreditAccounts())
                            ->searchable()
                            ->required(),

                        TextInput::make('keterangan'),

                        TextInput::make('jumlah')
                            ->numeric()
                            ->required(),
                    ])
                    ->minItems(fn ($get) =>
                        in_array($get('mode'), ['DK','KDD']) ? 1 : 2
                    )
                    ->maxItems(fn ($get) =>
                        $get('mode') === 'DKK' ? 99 : 1
                    )
                    ->live(),

            ]),

            /* ================= SUMMARY ================= */
            Placeholder::make('summary')
                ->content(function ($get) {

                    $debit  = collect($get('debits') ?? [])->sum('jumlah');
                    $kredit = collect($get('kredits') ?? [])->sum('jumlah');

                    if ($debit === $kredit) {
                        return "✅ <b>BALANCE</b><br>
                                Debit & Kredit: Rp ".number_format($debit);
                    }

                    return "❌ <b>TIDAK BALANCE</b><br>
                            Debit: Rp ".number_format($debit)."<br>
                            Kredit: Rp ".number_format($kredit)."<br>
                            Selisih: Rp ".number_format(abs($debit - $kredit));
                }),

        ]);
    }
}
