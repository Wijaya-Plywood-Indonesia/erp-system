<?php

namespace App\Filament\Resources\ProduksiRotaries\RelationManagers;

use App\Filament\Resources\ProduksiRotaries\ProduksiRotaryResource;
use App\Models\DetailHasilPaletRotary;
use App\Models\ProduksiKedi;
use App\Models\ProduksiPressDryer;
use App\Models\ProduksiRotary;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SerahTerimaRelationManager extends RelationManager
{
    protected static string $relationship = 'serahTerima';

    protected static ?string $relatedResource = ProduksiRotaryResource::class;

    protected function getTipePenerima(): string
    {
        return match (get_class($this->getOwnerRecord())) {
            ProduksiRotary::class => 'rotary',
            ProduksiPressDryer::class  => 'dryer',
            ProduksiKedi::class   => 'kedi',
            default               => 'unknown',
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

    // Schema (Forms)
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_detail_hasil_palet_rotary')
                    ->label('Nomor Palet')
                    ->options(function () {
                        $tipe       = $this->getTipePenerima();
                        $idProduksi = $this->getOwnerRecord()->id;

                        // Palet yang sudah diserahkan rotary
                        $sudahSerah = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('tipe', 'rotary')
                            ->pluck('id_detail_hasil_palet_rotary')
                            ->toArray();

                        if ($tipe === 'rotary') {
                            // Rotary: palet milik produksi ini yang belum diserahkan
                            return DetailHasilPaletRotary::where('id_produksi', $idProduksi)
                                ->whereNotIn('id', $sudahSerah)
                                ->get()
                                ->mapWithKeys(fn($d) => [
                                    $d->id => "{$d->palet} - {$d->total_lembar} lembar"
                                ]);
                        }

                        // Dryer / Kedi: palet sudah serah rotary, belum diterima tipe ini
                        $sudahTerima = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('tipe', $tipe)
                            ->pluck('id_detail_hasil_palet_rotary')
                            ->toArray();

                        $tersedia = array_diff($sudahSerah, $sudahTerima);

                        if (empty($tersedia)) {
                            return [];
                        }

                        return DetailHasilPaletRotary::whereIn('id', $tersedia)
                            ->get()
                            ->mapWithKeys(fn($d) => [
                                $d->id => "{$d->palet} - {$d->total_lembar} lembar"
                            ]);
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

    // Table
    public function table(Table $table): Table
    {
        $tipe = $this->getTipePenerima();

        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn($query) => $query->where('tipe', $tipe))
            ->columns([

                TextColumn::make('detailHasilPalet.palet')
                    ->label('Nomor Palet')
                    ->searchable(),

                TextColumn::make('detailHasilPalet.total_lembar')
                    ->label('Jumlah (Lembar)')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('diserahkan_oleh')
                    ->label($this->getLabelByTipe($tipe)),

                TextColumn::make('diterima_oleh')
                    ->label($this->getLabelByTipe($tipe)),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Serah Barang'  => 'warning',
                        'Terima Barang' => 'success',
                        default         => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label($tipe === 'rotary' ? 'Waktu Serah' : 'Waktu Terima')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

            ])
            ->headerActions([
                CreateAction::make()
                    ->label(
                        fn() => $this->getTipePenerima() === 'rotary'
                            ? 'Serahkan Palet'
                            : 'Terima Palet'
                    )
                    ->disabled(function () {
                        $tipe = $this->getTipePenerima();

                        if ($tipe === 'rotary') return false;

                        // Dryer/Kedi disabled jika belum ada serah dari rotary
                        return DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('tipe', 'rotary')
                            ->doesntExist();
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        $tipe = $this->getTipePenerima();

                        $data['diserahkan_oleh'] = Auth::user()->name;
                        $data['tipe']            = $tipe;
                        $data['status']          = $this->getStatusByTipe($tipe);
                        return $data;
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
