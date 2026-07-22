<?php

namespace App\Filament\Resources\ModalSandings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use GuzzleHttp\Promise\Create;

class ModalSandingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'barangSetengahJadi.grade.kategoriBarang',
                'barangSetengahJadi.ukuran',
                'barangSetengahJadi.jenisBarang',
                'serahTerimaHp.triplekMutasiKeluar.jenisKayu',
            ]))
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No Palet')
                    ->alignCenter()
                    ->numeric()
                    ->sortable(),

                TextColumn::make('barangSetengahJadiInfo')
                    ->label('Barang Setengah Jadi')
                    ->getStateUsing(function ($record) {
                        // Barang dari Gudang Triplek Jadi tidak punya barangSetengahJadi —
                        // rakit label dari mutasi keluar (jenis kayu / ukuran / grade).
                        $serah = $record->serahTerimaHp;
                        if ($serah?->id_triplek_mutasi_keluar !== null) {
                            $m = $serah->triplekMutasiKeluar;
                            if (! $m) {
                                return 'Triplek Jadi — -';
                            }
                            $ukuran = ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0);

                            // Barang di Gudang Triplek Jadi selalu berkategori Plywood
                            // (tidak menyimpan field kategori sendiri), jadi di-hardcode
                            // agar formatnya identik dengan baris hotpress/graji.
                            return "Plywood — {$ukuran} - "
                                .($m->jenisKayu?->nama_kayu ?? '-').' - '
                                .($m->kw_grade ?? '-');
                        }

                        // Sumber lama (hotpress / graji): format asli, tidak diubah.
                        $kategori = $record->barangSetengahJadi?->grade?->kategoriBarang?->nama_kategori ?? '-';
                        $ukuran = $record->barangSetengahJadi?->ukuran?->dimensi ?? '-';
                        $grade = $record->barangSetengahJadi?->grade?->nama_grade ?? '-';
                        $jenis = $record->barangSetengahJadi?->jenisBarang?->nama_jenis_barang ?? '-';

                        return "{$kategori} — {$ukuran} - {$jenis} - {$grade}";
                    })
                ,
                //--- INI BUAT FILTER AJA
                TextColumn::make('barangSetengahJadi.grade.kategoriBarang.nama_kategori')
                    ->label('Kategori')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('barangSetengahJadi.ukuran.dimensi')
                    ->label('Ukuran')
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('barangSetengahJadi.ukuran', function ($q) use ($search) {
                            $q->whereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                ,

                TextColumn::make('barangSetengahJadi.grade.nama_grade')
                    ->label('Grade')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('barangSetengahJadi.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('kuantitas')
                    ->label('Kuantitas')
                    ->suffix(' Lbr')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('jumlah_sanding_face')
                    ->label('Sanding Face (Pass)')
                    ->suffix(' x')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('jumlah_sanding_back')
                    ->label('Sanding Back (Pass)')
                    ->suffix(' x')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('+ Tambah Modal')
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                EditAction::make()
                ->label('')
                ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
                DeleteAction::make()
                ->label('')
                ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}