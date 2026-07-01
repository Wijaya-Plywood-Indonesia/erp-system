<?php

namespace App\Filament\Pages;

use App\Models\IndukAkun;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class TreeAkunPage extends Page
{
    use HasPageShield;
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    //    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.tree-akun-page';
    protected static ?string $navigationLabel = 'Chart of Accounts';
    protected static ?string $title = 'Chart of Accounts';

    public function getViewData(): array
    {
        $indukAkuns = IndukAkun::with([
            'anakAkuns',
            'anakAkuns.subAnakAkuns',
            'anakAkuns.children',
            'anakAkuns.children.subAnakAkuns',
            'anakAkuns.children.children',
            'anakAkuns.children.children.subAnakAkuns',
            'anakAkuns.children.children.children',
            'anakAkuns.children.children.children.subAnakAkuns',
            'anakAkuns.children.children.children.children',
            'anakAkuns.children.children.children.children.subAnakAkuns',
            'allAnakAkuns',
        ])
            ->where('status', 'aktif')
            ->orderBy('kode_induk_akun')
            ->get();

        return [
            'indukAkuns' => $indukAkuns,
        ];
    }
}
