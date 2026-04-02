<?php

namespace App\Filament\Resources\ProduksiRotaries\RelationManagers;

use App\Models\DetailHasilPaletRotary;
use App\Models\ProduksiPressDryer;
use App\Models\ProduksiRotary;
use App\Models\ProduksiStik; // <-- tambah import
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Laravel\Reverb\Loggers\Log;

class SerahTerimaRelationManager extends RelationManager
{
    protected static string $relationship = 'serahTerima';

    protected static ?string $relatedResource = null;

    protected function getTipePenerima(): string
    {
        return match (get_class($this->getOwnerRecord())) {
            ProduksiRotary::class     => 'rotary',
            ProduksiPressDryer::class => 'dryer',
            ProduksiStik::class       => 'stik', // <-- ganti kedi ke stik
            default                   => 'unknown',
        };
    }

    protected function getStatusByTipe(string $tipe): string
    {
        return match ($tipe) {
            'rotary' => 'Serah Barang',
            default  => 'Terima Barang',
        };
    }

    protected function getLabelByTipe(string $tipe): string
    {
        return match ($tipe) {
            'rotary' => 'Diserahkan Oleh',
            default  => 'Diterima Oleh',
        };
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_detail_hasil_palet_rotary')
                    ->label('Nomor Palet')
                    ->options(function () {
                        $tipe       = $this->getTipePenerima();
                        $idProduksi = $this->getOwnerRecord()->id;

                        $sudahSerah = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('tipe', 'rotary')
                            ->pluck('id_detail_hasil_palet_rotary')
                            ->toArray();

                        if ($tipe === 'rotary') {
                            return DetailHasilPaletRotary::where('id_produksi', $idProduksi)
                                ->whereNotIn('id', $sudahSerah)
                                ->get()
                                ->mapWithKeys(fn($d) => [
                                    $d->id => "{$d->palet} - {$d->total_lembar} lembar"
                                ]);
                        }

                        return [];
                    })
                    ->searchable()
                    ->required()
                    ->noSearchResultsMessage('Belum ada palet yang tersedia.')
                    ->columnSpanFull(),

                TextInput::make('diserahkan_oleh')
                    ->label(fn() => $this->getLabelByTipe($this->getTipePenerima()))
                    ->readOnly()
                    ->content(fn() => Auth::user()->name)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        $tipe = $this->getTipePenerima();

        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(function ($query) use ($tipe) {
                if ($tipe === 'rotary') {
                    return $query->where('tipe', 'rotary');
                }

                // Reset semua kondisi dari relasi dummy
                $query->getQuery()->wheres   = [];
                $query->getQuery()->bindings = [
                    'select'     => [],
                    'from'       => [],
                    'join'       => [],
                    'where'      => [],
                    'groupBy'    => [],
                    'having'     => [],
                    'order'      => [],
                    'union'      => [],
                    'unionOrder' => [],
                ];

                $query
                    ->from('detail_hasil_palet_rotary_serah_terima_pivot')
                    ->select('detail_hasil_palet_rotary_serah_terima_pivot.*')
                    ->where(function ($q) {
                        // Palet masuk dari rotary, belum diterima siapapun
                        $q->where('tipe', 'rotary')
                            ->where('diterima_oleh', '-');
                    })
                    ->orWhere(function ($q) use ($tipe) {
                        // Palet yang sudah diterima tipe ini (histori)
                        $q->where('tipe', $tipe);
                    });

                return $query;
            })
            ->columns([

                TextColumn::make('detailHasilPalet.palet')
                    ->label('Nomor Palet')
                    ->getStateUsing(fn($record) => $record->detailHasilPalet?->kode_palet ?? '-')
                    ->searchable(),

                TextColumn::make('detailHasilPalet.total_lembar')
                    ->label('Jumlah (Lembar)')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('diserahkan_oleh')
                    ->badge()
                    ->label('Diserahkan Oleh'),

                TextColumn::make('diterima_oleh')
                    ->label('Diterima Oleh')
                    ->badge()
                    ->color(fn($state) => $state === '-' ? 'gray' : 'success')
                    ->formatStateUsing(fn($state) => $state === '-' ? 'Belum Diterima' : $state),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Serah Barang'  => 'warning',
                        'Terima Barang' => 'success',
                        default         => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn($record) => $record?->tipe === 'rotary' ? 'Waktu Serah' : 'Waktu Terima')
                    ->sortable(),

            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Serahkan Palet')
                    ->visible(fn() => $this->getTipePenerima() === 'rotary'),
            ])
            ->actions([
                Action::make('terima')
                    ->label('Terima')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->tooltip('Terima palet ini')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Penerimaan Palet')
                    ->modalDescription(
                        fn($record) =>
                        "Palet {$record->detailHasilPalet?->palet} ({$record->detailHasilPalet?->total_lembar} lembar) " .
                            "akan diterima atas nama " . Auth::user()->name . "."
                    )
                    ->modalSubmitActionLabel('Ya, Terima')
                    ->visible(fn($record) => $tipe !== 'rotary' && $record?->diterima_oleh === '-')
                    ->action(function ($record) use ($tipe) {
                        DB::transaction(function () use ($record, $tipe) {
                            $owner = $this->getOwnerRecord();

                            $labelProduksi = match ($tipe) {
                                'dryer' => 'Produksi Dryer',
                                'stik'  => 'Produksi Stik', // <-- ganti kedi ke stik
                                default => 'Produksi',
                            };

                            $diterimaOleh = Auth::user()->name . ' - ' . $labelProduksi;

                            // Update record rotary — tandai sudah diterima
                            DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                ->where('id', $record->id)
                                ->update([
                                    'diterima_oleh' => $diterimaOleh,
                                    'status'        => 'Terima Barang',
                                    'updated_at'    => now(),
                                ]);

                            // Insert record baru tipe dryer/stik sebagai histori penerimaan
                            DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                ->insert([
                                    'id_detail_hasil_palet_rotary' => $record->id_detail_hasil_palet_rotary,
                                    'diserahkan_oleh'              => $record->diserahkan_oleh,
                                    'diterima_oleh'                => $diterimaOleh,
                                    'tipe'                         => $tipe,
                                    'status'                       => 'Terima Barang',
                                    'created_at'                   => now(),
                                    'updated_at'                   => now(),
                                ]);

                            \Illuminate\Support\Facades\Log::info('Palet Diterima: ' . json_encode([
                                'tipe'          => $tipe,
                                'owner_id'      => $owner->id,
                                'pivot_id'      => $record->id,
                                'diterima_oleh' => $diterimaOleh,
                            ]));
                        });

                        Notification::make()
                            ->title('Palet berhasil diterima')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }
}
