    <?php

    namespace App\Filament\Resources\GradeRules\Tables;

    use Filament\Actions\DeleteAction;
    use Filament\Actions\EditAction;
    use Filament\Tables;
    use Filament\Tables\Table;

    class GradeRulesTable
    {
        /**
         * Konfigurasi Schema Tabel untuk Aturan Grade.
         */
        public static function configure(Table $table): Table
        {
            return $table
                ->columns([
                    Tables\Columns\TextColumn::make('grade.kategoriBarang.nama_kategori')
                        ->label('Kategori')
                        ->badge()
                        ->color('gray')
                        ->sortable(),

                    Tables\Columns\TextColumn::make('grade.nama_grade')
                        ->label('Grade')
                        ->weight('bold')
                        ->color('primary')
                        ->sortable(),

                    Tables\Columns\TextColumn::make('criteria.nama_kriteria')
                        ->label('Parameter Pemeriksaan')
                        ->description(fn($record) => $record->penjelasan)
                        ->wrap()
                        ->searchable(),

                    Tables\Columns\TextColumn::make('kondisi')
                        ->label('Status Kebijakan')
                        ->badge()
                        ->color(fn(string $state): string => match ($state) {
                            'not_allowed' => 'danger',
                            'conditional' => 'warning',
                            'allowed'     => 'success',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn($state) => match ($state) {
                            'not_allowed' => 'Dilarang',
                            'conditional' => 'Bersyarat',
                            'allowed'     => 'Boleh',
                            default => $state,
                        }),

                    Tables\Columns\TextColumn::make('poin_lulus')
                        ->label('Poin Max')
                        ->numeric()
                        ->alignCenter(),

                    Tables\Columns\TextColumn::make('poin_parsial')
                        ->label('Poin Parsial')
                        ->numeric()
                        ->alignCenter()
                        ->placeholder('-'),
                ])
                ->defaultSort('id_grade', 'asc')
                ->filters([
                    Tables\Filters\SelectFilter::make('id_grade')
                        ->label('Filter Grade')
                        ->relationship('grade', 'nama_grade'),

                    Tables\Filters\SelectFilter::make('kondisi')
                        ->options([
                            'not_allowed' => 'Dilarang',
                            'conditional' => 'Bersyarat',
                            'allowed'     => 'Boleh',
                        ]),
                ])
                ->actions([
                    EditAction::make(),
                    DeleteAction::make(),
                ]);
        }
    }
