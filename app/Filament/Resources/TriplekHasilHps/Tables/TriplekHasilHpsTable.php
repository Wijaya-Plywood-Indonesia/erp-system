<?php

namespace App\Filament\Resources\TriplekHasilHps\Tables;

use App\Models\SerahTerimaHp;
use App\Models\TriplekHasilHp;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class TriplekHasilHpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'mesin',
                'barangSetengahJadi.jenisBarang',
                'barangSetengahJadi.grade',
                'barangSetengahJadi.ukuran',
                'serahTerimaHp',
            ]))
            ->columns([

                TextColumn::make('mesin.nama_mesin')
                    ->label('Mesin')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    ->color(function ($record) {
                        $serahTerima = $record->serahTerimaHp;

                        if (! $serahTerima) {
                            return 'gray';
                        }

                        return $serahTerima->diterima_oleh === '-' ? 'warning' : 'success';
                    })
                    ->description(function ($record) {
                        $serahTerima = $record->serahTerimaHp;

                        if (! $serahTerima) {
                            return 'Belum Serah';
                        }

                        return $serahTerima->diterima_oleh === '-'
                            ? 'Menunggu Diterima Graji Triplek'
                            : 'Sudah Diterima Graji Triplek';
                    }),

                TextColumn::make('barangSetengahJadi.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('barangSetengahJadi.grade.nama_grade')
                    ->label('Grade')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('barangSetengahJadi.ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('barangSetengahJadi.ukuran', function ($q) use ($search) {
                            $q->whereRaw(
                                "CONCAT(panjang, 'mm x ', lebar, 'mm x ', tebal, 'mm') LIKE ?",
                                ["%{$search}%"]
                            );
                        });
                    })
                    ->placeholder('-'),

                TextColumn::make('isi')
                    ->label('Jumlah Lembar'),

                /*
                 * STATUS SERAH — toggleable, default hidden
                 */
                TextColumn::make('status_serah')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (TriplekHasilHp $record) {
                        $serahTerima = $record->serahTerimaHp;

                        if (! $serahTerima) {
                            return 'Belum Diserahkan';
                        }

                        $tujuan = match ($serahTerima->tujuan) {
                            'graji_triplek' => 'Graji Triplek',
                            default => '-',
                        };

                        return $serahTerima->diterima_oleh === '-'
                            ? "Menunggu Diterima {$tujuan}"
                            : "Sudah Diterima {$tujuan}";
                    })
                    ->color(fn ($state) => match (true) {
                        str_contains($state, 'Sudah Diterima') => 'success',
                        str_contains($state, 'Menunggu Diterima') => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * DISERAHKAN OLEH — toggleable, default hidden
                 */
                TextColumn::make('serahTerimaHp.diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * DITERIMA OLEH — toggleable, default hidden
                 */
                TextColumn::make('serahTerimaHp.diterima_oleh')
                    ->label('Diterima Oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * CREATED AT — toggleable, default hidden
                 */
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * UPDATED AT — toggleable, default hidden
                 */
                TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            ->recordActions([
                Action::make('serah')
                    ->label('Serah')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Triplek ini ke Graji Triplek?')
                    ->modalDescription('Pastikan data berikut sudah sesuai sebelum diserahkan.')
                    ->modalContent(function (TriplekHasilHp $record) {
                        $mesin = $record->mesin?->nama_mesin ?? '-';
                        $jenis = $record->barangSetengahJadi?->jenisBarang?->nama_jenis_barang ?? '-';
                        $grade = $record->barangSetengahJadi?->grade?->nama_grade ?? '-';
                        $ukuranModel = $record->barangSetengahJadi?->ukuran;
                        $ukuran = $ukuranModel
                            ? "{$ukuranModel->panjang}mm x {$ukuranModel->lebar}mm x {$ukuranModel->tebal}mm"
                            : '-';

                        return new HtmlString(<<<HTML
            <div class="space-y-2 text-sm">
                <div class="grid grid-cols-3 gap-1">
                    <span class="font-medium text-gray-500">Mesin</span>
                    <span class="col-span-2">: {$mesin}</span>

                    <span class="font-medium text-gray-500">No. Palet</span>
                    <span class="col-span-2">: {$record->no_palet}</span>

                    <span class="font-medium text-gray-500">Jenis Barang</span>
                    <span class="col-span-2">: {$jenis}</span>

                    <span class="font-medium text-gray-500">Grade</span>
                    <span class="col-span-2">: {$grade}</span>

                    <span class="font-medium text-gray-500">Ukuran</span>
                    <span class="col-span-2">: {$ukuran}</span>

                    <span class="font-medium text-gray-500">Jumlah Lembar</span>
                    <span class="col-span-2">: {$record->isi}</span>
                </div>
            </div>
        HTML);
                    })
                    ->visible(fn (TriplekHasilHp $record) => ! $record->serahTerimaHp)
                    ->action(function (TriplekHasilHp $record) {
                        try {
                            DB::transaction(function () use ($record) {
                                SerahTerimaHp::create([
                                    'id_triplek_hasil_hp' => $record->id,
                                    'id_produksi_graji_triplek' => null,
                                    'tujuan' => 'graji_triplek',
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diterima_oleh' => '-',
                                    'status' => 'Serah Triplek',
                                ]);
                            });

                            $record->unsetRelation('serahTerimaHp');
                            $record->refresh();

                            Notification::make()
                                ->title('Penyerahan Berhasil')
                                ->body('Palet telah masuk ke daftar Serah Terima ke Graji Triplek.')
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->hidden(function ($livewire, TriplekHasilHp $record) {
                        $serahTerima = $record->serahTerimaHp;
                        $sudahDiterima = $serahTerima && $serahTerima->diterima_oleh !== '-';

                        return $sudahDiterima
                            || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi';
                    }),

                DeleteAction::make()
                    ->hidden(
                        fn ($livewire, TriplekHasilHp $record) => $record->serahTerimaHp
                            || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
