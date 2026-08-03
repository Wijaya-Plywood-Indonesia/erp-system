<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Models\BarangSetengahJadiHp;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Setiap baris di sini = 1 pesanan Plywood.
 * "Pilih Barang" hanya menampilkan master barang berkategori Plywood,
 * dan komposisi lapisan hanya menampilkan master barang berkategori Veneer.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Detail Barang Order (Plywood)';

    protected static ?string $modelLabel = 'Plywood';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // FIELD INI WAJIB ADA DI LEVEL ITEM (bukan di dalam Repeater layers!)
                Select::make('id_barang_setengah_jadi_hp')
                    ->label('Pilih Barang (Plywood)')
                    ->placeholder('Pilih...')
                    ->options(
                        fn () => BarangSetengahJadiHp::kategori('Plywood')
                            ->get()
                            ->pluck('label', 'id')
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull(),

                Grid::make(2)
                    ->schema([
                        TextInput::make('jumlah')
                            ->label('Jumlah')
                            ->suffix('Lembar')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->placeholder('Contoh: Pastikan kering')
                            ->rows(1)
                            ->columnSpan(1),
                    ]),

                Repeater::make('layers')
                    ->label('Komposisi Lapisan Veneer')
                    ->helperText('Susun material veneer dari atas (Lapis 1) ke bawah.')
                    ->relationship()
                    ->addActionLabel('+ Tambah Lapisan')
                    ->reorderableWithButtons()
                    ->orderColumn('urutan')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('id_barang_setengah_jadi_hp')
                                    ->label('Material Veneer')
                                    ->placeholder('Pilih Barang Veneer...')
                                    ->options(
                                        fn () => BarangSetengahJadiHp::kategori('Veneer')
                                            ->get()
                                            ->pluck('label', 'id')
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(2)
                                    // WAJIB: supaya waktu form edit dibuka, dropdown ini
                                    // otomatis nunjukin barang veneer yang sudah tersimpan
                                    ->afterStateHydrated(function ($state, callable $set, $record) {
                                        if ($record) {
                                            $set('id_barang_setengah_jadi_hp', $record->id_barang_setengah_jadi_hp);
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $barang = BarangSetengahJadiHp::find($state);
                                        $set('material', $barang?->label);
                                    }),

                                TextInput::make('qty')
                                    ->label('Kuantitas / Pcs')
                                    ->suffix('Lbr')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1),

                                // hidden, cuma penampung nama barang terpilih
                                // supaya kolom lama 'material' tetap terisi tanpa perlu join
                                TextInput::make('material')
                                    ->hidden()
                                    ->dehydrated(true),
                            ]),
                    ])
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('barangSetengahJadi.label')->label('Barang'),
                TextEntry::make('jumlah')->label('Jumlah')->suffix(' Lembar'),
                TextEntry::make('keterangan')->label('Keterangan')->placeholder('-'),

                RepeatableEntry::make('layers')
                    ->label('Komposisi Lapisan')
                    ->schema([
                        TextEntry::make('urutan')->label('Urutan')->formatStateUsing(fn ($state) => "Lapis {$state}"),
                        TextEntry::make('material')->label('Material Veneer')->placeholder('-'),
                        TextEntry::make('qty')->label('Kuantitas')->suffix(' Lbr'),
                    ])
                    ->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('keterangan')
            ->modifyQueryUsing(fn ($query) => $query->with(['barangSetengahJadi', 'layers']))
            ->columns([
                // INI YANG SEBELUMNYA HILANG / KOSONG
                TextColumn::make('barangSetengahJadi.label')
                    ->label('Nama Barang')
                    ->weight(FontWeight::Medium)
                    ->searchable()
                    ->placeholder('- barang belum dipilih -'),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->suffix(' Lembar'),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->limit(30),

                TextColumn::make('layers_count')
                    ->label('Komposisi')
                    ->counts('layers')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state} Lapisan" : 'Belum diisi')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'gray' : 'danger'),

                CheckboxColumn::make('status')
                    ->label('Status Selesai')
                    ->alignCenter()
                    ->afterStateUpdated(fn () => $this->dispatch('po-items-updated')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('+ Tambah Barang')
                    ->modalHeading('Form Detail Plywood'),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()
                    ->label('Edit')
                    ->modalHeading('Form Detail Plywood'),
                DeleteAction::make(),
            ]);
    }
}