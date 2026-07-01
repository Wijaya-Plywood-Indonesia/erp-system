<?php

namespace App\Filament\Resources\ProduksiPressDryers\RelationManagers;

use App\Models\DetailHasil;
use App\Models\DetailMasuk;
use App\Models\SerahTerimaVeneerKering;
use App\Services\SerahHasilDryerService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

class DetailHasilsRelationManager extends RelationManager
{
    protected static ?string $title = 'Hasil';

    protected static string $relationship = 'detailHasils';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),

                Select::make('id_ukuran')
                    ->label('Ukuran Kayu')
                    ->options(function () {
                        $produksi = $this->getOwnerRecord();

                        return DetailMasuk::where('id_produksi_dryer', $produksi->id)
                            ->with('ukuran')
                            ->get()
                            ->pluck('ukuran.nama_ukuran', 'id_ukuran')
                            ->unique();
                    })
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(['last_ukuran' => $state]);
                    })
                    ->default(fn () => session('last_ukuran'))
                    ->required(),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(function () {
                        $produksi = $this->getOwnerRecord();

                        return DetailMasuk::where('id_produksi_dryer', $produksi->id)
                            ->select('id_jenis_kayu')
                            ->distinct()
                            ->with('jenisKayu:id,nama_kayu')
                            ->get()
                            ->pluck('jenisKayu.nama_kayu', 'id_jenis_kayu');
                    })
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(['last_jenis_kayu' => $state]);
                    })
                    ->default(fn () => session('last_jenis_kayu'))
                    ->required(),

                TextInput::make('kw')
                    ->label('Kualitas (KW)')
                    ->required()
                    ->placeholder('Cth: 1, 2, 3 dll.'),

                TextInput::make('isi')
                    ->label('Isi')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 1.5 atau 100'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'stokMasuk',
                'ukuran',
                'jenisKayu',
                'produksiDryer',
                'serahTerimaVeneerKering', // eager load untuk cek status repair
            ]))
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    ->color(fn ($record) => $record->stokMasuk ? 'success' : 'gray')
                    ->description(fn ($record) => $record->stokMasuk ? 'Sudah Serah' : 'Belum Serah'),

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

                TextColumn::make('isi')
                    ->label('Isi'),

                // Kolom status repair — informatif
                TextColumn::make('status_repair')
                    ->label('Status Repair')
                    ->badge()
                    ->getStateUsing(function (DetailHasil $record) {
                        $serahTerima = $record->serahTerimaVeneerKering;
                        if (! $serahTerima) {
                            return 'Belum Diserahkan';
                        }

                        return $serahTerima->diterima_oleh === '-' ? 'Menunggu Repair' : 'Sudah Diterima Repair';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Sudah Diterima Repair' => 'success',
                        'Menunggu Repair' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                Action::make('serahKeGudang')
                    ->label('Serahkan Hasil')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(function (DetailHasil $record) {
                        return ! DB::table('stok_veneer_kerings')
                            ->where('id_detail_hasil_dryer', $record->id)
                            ->exists();
                    })
                    ->action(function (DetailHasil $record) {
                        try {
                            DB::transaction(function () use ($record) {
                                // Step 1: serah ke gudang (logic lama)
                                app(SerahHasilDryerService::class)->serahkan($record);

                                // Step 2: otomatis serah ke repair sekaligus
                                $sudahKeRepair = DB::table('serah_terima_veneer_kering')
                                    ->where('id_detail_hasil', $record->id)
                                    ->exists();

                                if (! $sudahKeRepair) {
                                    SerahTerimaVeneerKering::create([
                                        'id_detail_hasil' => $record->id,
                                        'id_detail_bongkar_kedi' => null,
                                        'tipe_sumber' => 'dryer',
                                        'id_produksi_repair' => null,
                                        'diserahkan_oleh' => Auth::user()->name,
                                        'diterima_oleh' => '-',
                                        'status' => 'Serah Veneer',
                                    ]);
                                }
                            });

                            $record->unsetRelation('stokMasuk');
                            $record->unsetRelation('serahTerimaVeneerKering');
                            $record->refresh();

                            Notification::make()
                                ->title('Penyerahan Berhasil')
                                ->body('Veneer kering telah masuk gudang dan siap diterima Repair.')
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

                // Action serahKeRepair dihapus — sudah otomatis di atas

                EditAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
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
