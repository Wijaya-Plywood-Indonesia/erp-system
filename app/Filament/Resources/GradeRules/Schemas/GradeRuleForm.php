<?php

namespace App\Filament\Resources\GradeRules\Schemas;

use App\Models\Criteria;
use App\Models\Grade;
use App\Models\GradeRule;
use App\Models\KategoriBarang;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class GradeRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(12)->schema([

                // --- SIDEBAR ---
                Grid::make(1)->schema([
                    Section::make('Filter')
                        ->schema([
                            Select::make('id_kategori_barang')
                                ->label('FILTER KATEGORI')
                                ->options(KategoriBarang::pluck('nama_kategori', 'id'))
                                ->default(fn() => KategoriBarang::where('nama_kategori', 'Plywood')->first()?->id)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                    $set('id_criteria', null);
                                    $set('selected_criteria_name', null);
                                    $set('rules_repeater', []);

                                    if (!$state) return;

                                    $firstCriteria = Criteria::where('id_kategori_barang', $state)
                                        ->orderBy('urutan')
                                        ->first();

                                    if ($firstCriteria) {
                                        $set('id_criteria', $firstCriteria->id);
                                        self::loadRepeater($set, $firstCriteria->id, $state);
                                    }
                                })
                                ->native(false)
                                ->extraAttributes(['class' => 'font-bold']),

                            ToggleButtons::make('id_criteria')
                                ->label('PILIH PARAMETER / KRITERIA')
                                ->options(fn(Get $get) => Criteria::where('id_kategori_barang', $get('id_kategori_barang'))
                                    ->orderBy('urutan')
                                    ->pluck('nama_kriteria', 'id'))
                                ->required()
                                ->live()
                                ->inline(false)
                                ->colors(['primary' => 'warning'])
                                ->extraAttributes([
                                    'class' => 'w-full [&_div]:grid [&_div]:grid-cols-1 [&_div]:gap-2 [&_label]:w-full [&_label]:justify-start [&_label]:px-4 [&_label]:py-3',
                                ])
                                ->visible(fn(Get $get) => filled($get('id_kategori_barang')))
                                // afterStateHydrated dibiarkan kosong agar tidak ada otomasi saat load
                                ->afterStateHydrated(function (Set $set, $state) {
                                    // Tidak melakukan apa-apa agar tetap manual
                                })
                                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                    if (!$state) return;

                                    $criteria = Criteria::find($state);
                                    if (!$criteria) return;

                                    // Ambil data untuk feedback nama
                                    $set('selected_criteria_name', $criteria->nama_kriteria);

                                    // Load data repeater secara manual berdasarkan klik
                                    $catId = $get('id_kategori_barang') ?: $criteria->id_kategori_barang;
                                    self::loadRepeater($set, $state, $catId);
                                }),
                        ]),
                ])->columnSpan(4),

                // --- MAIN CONTENT ---
                Section::make('main_config')
                    ->heading('PENGATURAN KEBIJAKAN')
                    ->description(fn(Get $get): ?string => $get('selected_criteria_name'))
                    ->columnSpan(8)
                    ->visible(fn(Get $get) => filled($get('id_criteria')))
                    ->schema([
                        Hidden::make('selected_criteria_name'),

                        Repeater::make('rules_repeater')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(12)->schema([
                                    Hidden::make('id_grade'),
                                    Hidden::make('nama_grade'),

                                    Grid::make(1)->schema([
                                        ToggleButtons::make('kondisi')
                                            ->label(fn(Get $get) => strtoupper($get('nama_grade') ?? 'GRADE'))
                                            ->options([
                                                'not_allowed' => 'Fatal',
                                                'conditional' => 'Toleransi',
                                                'allowed'     => 'Diizinkan',
                                            ])
                                            ->colors([
                                                'not_allowed' => 'danger',
                                                'conditional' => 'warning',
                                                'allowed'     => 'success',
                                            ])
                                            ->required()
                                            ->inline()
                                            ->live(),

                                        Grid::make(2)->schema([
                                            TextInput::make('poin_lulus')
                                                ->label('SKOR MAX')
                                                ->numeric()
                                                ->default(100)
                                                ->required(),

                                            TextInput::make('poin_parsial')
                                                ->label('SKOR PENALTI')
                                                ->numeric()
                                                ->default(0)
                                                ->required()
                                                ->visible(fn(Get $get) => $get('kondisi') === 'conditional'),
                                        ]),
                                    ])->columnSpan(7),

                                    Textarea::make('penjelasan')
                                        ->label('DASAR KEPUTUSAN')
                                        ->placeholder('Input standar teknis di sini...')
                                        ->rows(5)
                                        ->columnSpan(5),
                                ]),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->itemLabel(fn(array $state): ?string => strtoupper($state['nama_grade'] ?? 'GRADE')),
                    ]),

            ])->columnSpanFull(),
        ]);
    }

    /**
     * FIX: Pisahkan loadRepeater menjadi method yang menerima parameter eksplisit
     * (bukan bergantung pada Get() yang tidak reliable saat hydration).
     *
     * @param Set    $set        Filament Set closure
     * @param int    $criteriaId ID criteria yang dipilih
     * @param int    $catId      ID kategori barang (dari criteria, bukan dari Get)
     */
    protected static function loadRepeater(Set $set, int $criteriaId, int $catId): void
    {
        $grades = Grade::where('id_kategori_barang', $catId)->get();

        if ($grades->isEmpty()) return;

        $existingRules = GradeRule::where('id_criteria', $criteriaId)
            ->whereIn('id_grade', $grades->pluck('id'))
            ->get()
            ->keyBy('id_grade');

        $repeaterItems = $grades->map(function ($grade) use ($existingRules) {
            $existing = $existingRules->get($grade->id);
            return [
                'id_grade'     => $grade->id,
                'nama_grade'   => (string) $grade->nama_grade,
                'kondisi'      => $existing?->kondisi ?? 'not_allowed',
                'poin_lulus'   => $existing?->poin_lulus ?? 100,
                'poin_parsial' => $existing?->poin_parsial ?? 0,
                'penjelasan'   => $existing?->penjelasan ?? '',
            ];
        })->toArray();

        $set('rules_repeater', $repeaterItems);
    }

    /**
     * Helper lama — dipertahankan untuk kompatibilitas jika masih dipakai di tempat lain.
     * Sekarang memanggil loadRepeater yang lebih aman.
     */
    protected static function updateRepeater(Get $get, Set $set, $state): void
    {
        $criteriaId = $state;

        if (!$criteriaId) {
            $catId      = $get('id_kategori_barang')
                ?: KategoriBarang::where('nama_kategori', 'Plywood')->first()?->id;
            $criteriaId = Criteria::where('id_kategori_barang', $catId)
                ->orderBy('urutan')
                ->first()?->id;
        }

        if (!$criteriaId) return;

        $criteria = Criteria::find($criteriaId);
        if (!$criteria) return;

        $catId = $get('id_kategori_barang') ?: $criteria->id_kategori_barang;
        if (!$catId) return;

        $set('selected_criteria_name', $criteria->nama_kriteria);
        $set('id_criteria', $criteriaId);

        self::loadRepeater($set, $criteriaId, $catId);
    }
}
