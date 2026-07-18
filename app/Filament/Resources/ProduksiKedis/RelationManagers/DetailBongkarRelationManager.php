<?php

namespace App\Filament\Resources\ProduksiKedis\RelationManagers;

use App\Models\SerahTerimaVeneerKering;
use App\Models\Ukuran;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class DetailBongkarRelationManager extends RelationManager
{
    protected static ?string $title = 'Bongkar Kedi';

    protected static string $relationship = 'detailBongkarKedi';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Pilihan Kayu Gabungan (Jenis Kayu + Ukuran) dari data masuk
                Select::make('kayu_masuk_composite')
                    ->label('Pilih Kayu (Dari Data Masuk)')
                    ->options(function ($livewire) {
                        return $livewire->ownerRecord->detailMasukKedi()
                            ->with(['jenisKayu', 'ukuran'])
                            ->get()
                            ->mapWithKeys(function ($d) {
                                $key = "{$d->id_jenis_kayu}-{$d->id_ukuran}";
                                $label = "{$d->jenisKayu->nama_kayu} | {$d->ukuran->dimensi}";

                                return [$key => $label];
                            })
                            ->unique();
                    })
                    ->searchable()
                    ->live()
                    ->formatStateUsing(fn($record) => $record ? "{$record->id_jenis_kayu}-{$record->id_ukuran}" : null)
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            [$jenisId, $ukuranId] = explode('-', $state);
                            $set('id_jenis_kayu', $jenisId);
                            $set('id_ukuran', $ukuranId);
                        } else {
                            $set('id_jenis_kayu', null);
                            $set('id_ukuran', null);
                        }
                    })
                    ->required()
                    ->dehydrated(false),

                Hidden::make('id_jenis_kayu')
                    ->required(),

                Hidden::make('id_ukuran')
                    ->required(),

                TextInput::make('kw')
                    ->label('Kualitas (KW)')
                    ->required()
                    ->placeholder('Cth: 1, 2, 3 dll.'),

                TextInput::make('jumlah')
                    ->label('Jumlah')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 1.5 atau 100'),

                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with([
                'jenisKayu',
                'ukuran',
                'serahTerimaVeneerKering', // eager load untuk cek status serah
            ]))
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    ->formatStateUsing(fn($state) => 'KD-' . $state)
                    ->color(function ($record) {
                        $serahTerima = $record->serahTerimaVeneerKering;

                        if (! $serahTerima) {
                            return 'gray';
                        }

                        return $serahTerima->diterima_oleh === '-' ? 'warning' : 'success';
                    })
                    ->description(function ($record) {
                        $serahTerima = $record->serahTerimaVeneerKering;

                        if (! $serahTerima) {
                            return 'Belum Serah';
                        }

                        return $serahTerima->diterima_oleh === '-' ? 'Sudah Diserahkan' : 'Sudah Diterima Repair';
                    }),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable(),

                TextColumn::make('ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('ukuran', function (Builder $q) use ($search) {
                            $q->where('panjang', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('tebal', 'like', "%{$search}%")
                                ->orWhereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('kw')
                    ->label('Kualitas (KW)')
                    ->searchable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah'),

                // Kolom status serah — informatif
                TextColumn::make('status_repair')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $serahTerima = $record->serahTerimaVeneerKering;
                        if (! $serahTerima) {
                            return 'Belum Diserahkan';
                        }

                        return $serahTerima->diterima_oleh === '-' ? 'Sudah Diserahkan' : 'Sudah Diterima Repair';
                    })
                    ->color(fn($state) => match ($state) {
                        'Sudah Diterima Repair' => 'success',
                        'Sudah Diserahkan' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn($livewire) => $livewire->ownerRecord?->isBongkarDivalidasi()
                    ),
            ])
            ->recordActions([
                // Tombol Serah — muncul kalau belum pernah diserahkan
                Action::make('serah')
                    ->label('Serah')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Veneer Kering ini ke Repair?')
                    ->modalDescription('Pilih tujuan serah, lalu pastikan data berikut sudah sesuai.')
                    ->schema([
                        Radio::make('jenis_terima')
                            ->label('Diserahkan Sebagai')
                            ->options([
                                'kering' => 'Veneer Kering',
                                'jadi' => 'Veneer Jadi',
                            ])
                            ->inline()
                            ->inlineLabel(false)
                            ->default('kering')
                            ->required(),
                    ])
                    ->modalContent(function ($record) {
                        $jenisKayu = $record->jenisKayu?->nama_kayu ?? '-';
                        $ukuranModel = $record->ukuran;
                        $ukuran = $ukuranModel
                            ? "{$ukuranModel->panjang} x {$ukuranModel->lebar} x {$ukuranModel->tebal}"
                            : '-';

                        return new HtmlString(<<<HTML
    <div class="space-y-2 text-sm">
        <div class="grid grid-cols-3 gap-1">
            <span class="font-medium text-gray-500">No. Palet</span>
            <span class="col-span-2">: {$record->no_palet}</span>

            <span class="font-medium text-gray-500">Jenis Kayu</span>
            <span class="col-span-2">: {$jenisKayu}</span>

            <span class="font-medium text-gray-500">Ukuran</span>
            <span class="col-span-2">: {$ukuran}</span>

            <span class="font-medium text-gray-500">Kualitas (KW)</span>
            <span class="col-span-2">: {$record->kw}</span>

            <span class="font-medium text-gray-500">Jumlah</span>
            <span class="col-span-2">: {$record->jumlah}</span>
        </div>
    </div>
HTML);
                    })
                    ->visible(fn($record) => ! $record->serahTerimaVeneerKering)
                    ->action(function ($record, array $data) {
                        try {
                            DB::transaction(function () use ($record, $data) {
                                SerahTerimaVeneerKering::create([
                                    'id_detail_hasil' => null,
                                    'id_detail_bongkar_kedi' => $record->id,
                                    'tipe_sumber' => 'kedi',
                                    'id_produksi_repair' => null,
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diterima_oleh' => '-',
                                    'jenis_terima' => $data['jenis_terima'],
                                    'status' => 'Serah Veneer',
                                ]);
                            });

                            $record->unsetRelation('serahTerimaVeneerKering');
                            $record->refresh();

                            Notification::make()
                                ->title('Veneer Kering Berhasil Diserahkan')
                                ->body('Siap diterima Repair.')
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
                    ->hidden(function ($livewire, $record) {
                        $serahTerima = $record->serahTerimaVeneerKering;
                        $sudahDiterima = $serahTerima && $serahTerima->diterima_oleh !== '-';

                        return $sudahDiterima
                            || $livewire->ownerRecord?->isBongkarDivalidasi();
                    }),

                DeleteAction::make()
                    ->hidden(
                        fn($livewire, $record) => $record->serahTerimaVeneerKering
                            || $livewire->ownerRecord?->isBongkarDivalidasi()
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) => $livewire->ownerRecord?->isBongkarDivalidasi()
                        ),
                ]),
            ]);
    }

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        return in_array($ownerRecord->status, ['bongkar', 'selesai']);
    }
}
