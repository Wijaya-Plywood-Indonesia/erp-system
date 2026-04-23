<?php

namespace App\Filament\Resources\ProduksiStiks\RelationManagers;

use App\Filament\Resources\DetailMasuks\Schemas\DetailMasukForm;
use App\Filament\Resources\DetailMasuks\Tables\DetailMasuksTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class DetailMasukStikRelationManager extends RelationManager
{
    protected static ?string $title = 'Modal';
    protected static string $relationship = 'detailMasukStik';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $idProduksiStik = $this->getOwnerRecord()->id;
        return DetailMasukForm::configure($schema, $idProduksiStik, 'stik');
    }

    public function table(Table $table): Table
    {

        $adaPaletDiterima = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
            ->where('tipe', 'stik')
            ->exists();

        return DetailMasuksTable::configure($table, $adaPaletDiterima, 'stik');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['no_palet_select']);
        unset($data['af_preview']);
        $data['no_palet'] = (int) ($data['no_palet'] ?? 0);
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['no_palet_select']);
        unset($data['af_preview']);
        $data['no_palet'] = (int) ($data['no_palet'] ?? 0);
        return $data;
    }
}
