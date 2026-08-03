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
use Filament\Schemas\Components\Section;
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
                                    ->live() // DITAMBAHKAN: Agar trigger state update ke hidden input berfungsi
                                    ->columnSpan(2)
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
                Section::make(fn ($record) => $record->barangSetengahJadi?->label ?? '- barang belum dipilih -')
                    ->description(fn ($record) => "{$record->jumlah} Lembar"
                        .($record->keterangan ? " · {$record->keterangan}" : ''))
                    ->schema([
                        TextEntry::make('status_badge')
                            ->label('Status')
                            ->state(fn ($record) => $record->status ? 'Selesai' : 'Belum Selesai')
                            ->badge()
                            ->color(fn ($record) => $record->status ? 'success' : 'danger'),

                        RepeatableEntry::make('layers')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('urutan')
                                    ->label('Urutan')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => "Lapis {$state}"),
                                // DIREVISI: Mengambil label langsung dari relasi barangSetengahJadi
                                TextEntry::make('barangSetengahJadi.label')
                                    ->label('Material Veneer')
                                    ->weight(FontWeight::Medium)
                                    ->placeholder('-')
                                    ->default(fn ($record) => $record->material), // fallback ke text lama jika relasi tidak ada
                                TextEntry::make('qty')
                                    ->label('Kuantitas')
                                    ->suffix(' Lbr'),
                            ])
                            ->columns(3)
                            ->placeholder('Belum ada komposisi lapisan untuk barang ini.'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('keterangan')
            // DIREVISI: Eager load berlapis sampai ke relasi barangSetengahJadi pada layer
            ->modifyQueryUsing(fn ($query) => $query->with(['barangSetengahJadi', 'layers.barangSetengahJadi']))
            ->columns([
                TextColumn::make('barangSetengahJadi.label')
                    ->label('Nama Barang')
                    ->weight(FontWeight::Medium)
                    ->searchable()
                    ->placeholder('- barang belum dipilih -')
                    // DIREVISI: Memindahkan HTML lapisan ke dalam deskripsi nama barang utama
                    ->description(function ($record) {
                        if ($record->layers->isEmpty()) {
                            return new \Illuminate\Support\HtmlString(
                                '<div class="text-sm text-gray-500 py-1">Belum ada komposisi lapisan.</div>'
                            );
                        }

                        $rows = $record->layers->map(function ($layer) {
                            // Mengambil dari relasi agar nama barang selalu update, fallback ke text lama
                            $material = e($layer->barangSetengahJadi?->label ?? $layer->material ?? '-');

                            return <<<HTML
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="py-1 pr-4">
                                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium bg-gray-100 dark:bg-gray-700">Lapis {$layer->urutan}</span>
                                    </td>
                                    <td class="py-1 pr-4 font-medium text-xs text-gray-700 dark:text-gray-300">{$material}</td>
                                    <td class="py-1 text-xs text-gray-500">{$layer->qty} Lbr</td>
                                </tr>
                            HTML;
                        })->implode('');

                        return new \Illuminate\Support\HtmlString(<<<HTML
                            <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                                <div class="text-[10px] font-semibold uppercase text-gray-400 mb-1">Komposisi Lapisan</div>
                                <table class="w-full text-left">
                                    <tbody>{$rows}</tbody>
                                </table>
                            </div>
                        HTML);
                    }),

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