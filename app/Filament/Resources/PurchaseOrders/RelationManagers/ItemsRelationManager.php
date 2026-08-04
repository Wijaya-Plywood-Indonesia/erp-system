<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Models\BarangSetengahJadiHp;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\HtmlString;

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
                    ->helperText('Susun komposisi veneer dari atas (Face) ke bawah (Back). Posisi otomatis terdeteksi.')
                    ->relationship()
                    ->addActionLabel('+ Tambah Lapisan')
                    ->reorderableWithButtons() 
                    ->cloneable() 
                    ->orderColumn('urutan')
                    ->collapsible()
                    ->collapsed(fn ($record) => $record !== null)
                    ->itemLabel(function ($state, $component): ?string {
                        try {
                            $container = $component->getContainer();
                            if (!$container) return 'Lapisan Veneer';
                            
                            $parent = $container->getParentComponent();
                            if (!$parent) return 'Lapisan Veneer';

                            $layers = $parent->getState() ?? [];
                            $keys = array_keys($layers);
                            $index = array_search($component->getStatePath(), array_map(fn($k) => "layers.{$k}", $keys));
                            $total = count($keys);
                            
                            $posisi = 'Core';
                            if ($index === 0) $posisi = 'Face (Atas)';
                            elseif ($index === $total - 1 && $total > 1) $posisi = 'Back (Bawah)';

                            return "Lapis " . (($index !== false ? $index : 0) + 1) . " - " . $posisi;
                        } catch (\Throwable $e) {
                            return 'Lapisan Veneer';
                        }
                    })
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Placeholder::make('posisi')
                                    ->label('Posisi')
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 3,
                                    ])
                                    ->content(function ($get, $component) {
                                        $path = $component->getStatePath();
                                        preg_match('/layers\.([^\.]+)\./', $path, $matches);
                                        $currentUuid = $matches[1] ?? null;

                                        $layers = $get('../../layers') ?? [];
                                        if (!is_array($layers) || empty($layers)) {
                                            return new HtmlString('<span class="inline-flex items-center rounded-md bg-primary-50 px-2.5 py-1 text-xs font-bold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-900/30 dark:text-primary-400">Face (Atas)</span>');
                                        }

                                        $keys = array_keys($layers);
                                        $index = array_search($currentUuid, $keys);
                                        $total = count($keys);

                                        if ($index === 0 || $index === false) {
                                            return new HtmlString('<span class="inline-flex items-center rounded-md bg-primary-50 px-2.5 py-1 text-xs font-bold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-900/30 dark:text-primary-400">Face (Atas)</span>');
                                        }
                                        if ($index === $total - 1 && $total > 1) {
                                            return new HtmlString('<span class="inline-flex items-center rounded-md bg-warning-50 px-2.5 py-1 text-xs font-bold text-warning-800 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-900/30 dark:text-warning-500">Back (Bawah)</span>');
                                        }
                                        
                                        return new HtmlString('<span class="inline-flex items-center rounded-md bg-gray-50 px-2.5 py-1 text-xs font-bold text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-800 dark:text-gray-400">Core (Tengah)</span>');
                                    }),

                                Select::make('id_barang_setengah_jadi_hp')
                                    ->label('Komposisi') // Diubah menjadi Komposisi
                                    ->placeholder('Pilih Komposisi...')
                                    ->options(
                                        fn () => BarangSetengahJadiHp::kategori('Veneer')
                                            ->get()
                                            ->pluck('label', 'id')
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->live() 
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 6,
                                    ])
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
                                    ->label('Kuantitas')
                                    ->placeholder('Qty')
                                    ->suffix('Lbr')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 3,
                                    ]),

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
                                TextEntry::make('info_lapisan')
                                    ->label('Lapisan & Posisi')
                                    ->state(function ($record) {
                                        $count = $record->purchaseOrderItem->layers->count();
                                        $pos = 'Core';
                                        if ($record->urutan == 1) $pos = 'Face';
                                        elseif ($record->urutan == $count && $count > 1) $pos = 'Back';
                                        return "Lapis {$record->urutan} · {$pos}";
                                    }),
                                TextEntry::make('barangSetengahJadi.label')
                                    ->label('Komposisi')
                                    ->weight(FontWeight::Medium)
                                    ->placeholder('-')
                                    ->default(fn ($record) => $record->material),
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
            ->recordAction(null) 
            ->modifyQueryUsing(fn ($query) => $query->with(['barangSetengahJadi', 'layers.barangSetengahJadi']))
            ->columns([
                TextColumn::make('barangSetengahJadi.label')
                    ->label('Nama Barang')
                    ->searchable()
                    ->formatStateUsing(function ($state, $record) {
                        $namaBarang = e($state ?? '- barang belum dipilih -');

                        if ($record->layers->isEmpty()) {
                            return new HtmlString(<<<HTML
                                <div class="flex items-center gap-2 text-sm py-1">
                                    <div class="w-4 h-4"></div> 
                                    <span class="font-medium text-gray-950 dark:text-white">{$namaBarang}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1 pl-6">Belum ada komposisi lapisan.</div>
                            HTML);
                        }

                        $layerCount = $record->layers->count();

                        $rows = $record->layers->map(function ($layer, $idx) use ($layerCount) {
                            $material = e($layer->barangSetengahJadi?->label ?? $layer->material ?? '-');

                            $posisi = 'Core';
                            $badgeClass = 'text-gray-600 bg-gray-100 dark:bg-gray-800 dark:text-gray-300';
                            
                            if ($idx === 0) {
                                $posisi = 'Face';
                                $badgeClass = 'text-primary-600 bg-primary-50 dark:bg-primary-950 dark:text-primary-400 font-bold';
                            } elseif ($idx === $layerCount - 1 && $layerCount > 1) {
                                $posisi = 'Back';
                                $badgeClass = 'text-warning-600 bg-warning-50 dark:bg-warning-950 dark:text-warning-400 font-bold';
                            }

                            return <<<HTML
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="py-1.5 pr-4 w-44">
                                        <span class="inline-flex items-center gap-1.5 text-xs">
                                            <span class="font-medium text-gray-700 dark:text-gray-300">Lapis {$layer->urutan}</span>
                                            <span class="text-gray-300 dark:text-gray-600">•</span>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] {$badgeClass}">{$posisi}</span>
                                        </span>
                                    </td>
                                    <td class="py-1.5 pr-4 font-medium text-xs text-gray-700 dark:text-gray-300">{$material}</td>
                                    <td class="py-1.5 text-xs text-gray-500 w-20 whitespace-nowrap">{$layer->qty} Lbr</td>
                                </tr>
                            HTML;
                        })->implode('');

                        return new HtmlString(<<<HTML
                            <div x-data="{ expanded: false }" 
                                 @toggle-row-{$record->id}.window="expanded = !expanded" 
                                 @click.stop="expanded = !expanded" 
                                 class="w-full cursor-pointer group py-1">
                                
                                <div class="flex items-center gap-2">
                                    <svg :class="{'rotate-180': expanded}" class="w-4 h-4 text-gray-400 group-hover:text-primary-600 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                    <span class="font-medium text-sm text-gray-950 dark:text-white group-hover:text-primary-600 transition-colors">{$namaBarang}</span>
                                </div>
                                
                                <div x-show="expanded" x-collapse x-cloak class="mt-2 pl-6">
                                    <div class="bg-gray-50/50 dark:bg-gray-800/30 rounded-lg p-3 border border-gray-100 dark:border-gray-700 max-w-xl cursor-default" @click.stop>
                                        <table class="w-full text-left">
                                            <thead>
                                                <tr class="text-[10px] uppercase text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                                    <th class="py-1 pr-4 font-semibold">Lapisan & Posisi</th>
                                                    <th class="py-1 pr-4 font-semibold">Komposisi</th>
                                                    <th class="py-1 font-semibold">Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody>{$rows}</tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        HTML);
                    }),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->suffix(' Lembar')
                    ->extraAttributes(fn ($record) => [
                        '@click.stop' => "\$dispatch('toggle-row-{$record->id}')",
                        'class' => 'cursor-pointer'
                    ]),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->limit(30)
                    ->extraAttributes(fn ($record) => [
                        '@click.stop' => "\$dispatch('toggle-row-{$record->id}')",
                        'class' => 'cursor-pointer'
                    ]),

                TextColumn::make('layers_count')
                    ->label('Komposisi')
                    ->counts('layers')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state} Lapisan" : 'Belum diisi')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'gray' : 'danger')
                    ->extraAttributes(fn ($record) => [
                        '@click.stop' => "\$dispatch('toggle-row-{$record->id}')",
                        'class' => 'cursor-pointer'
                    ]),

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
                EditAction::make()
                    ->label('Edit')
                    ->modalHeading('Form Detail Plywood'),
                DeleteAction::make(),
            ]);
    }
}