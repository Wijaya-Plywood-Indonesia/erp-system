<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Facades\Filament;
use UnitEnum;
use BackedEnum;

class LaporanHub extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Pusat Laporan';
    protected static ?string $title = 'Pusat Laporan';

    protected string $view = 'filament.pages.laporan-hub';

    public function getLaporanList(): array
    {
        $pages = Filament::getPages(); // key = path, value = class string

        $laporanPages = collect(array_values($pages))
            ->filter(function (string $pageClass) {
                // Skip class yang tidak valid / tidak extends Page
                if (!class_exists($pageClass) || !is_subclass_of($pageClass, Page::class)) {
                    return false;
                }

                // Jangan tampilkan hub ini sendiri
                if ($pageClass === static::class) {
                    return false;
                }

                $group = $pageClass::getNavigationGroup();
                $groupLabel = $group instanceof UnitEnum
                    ? ($group->getLabel() ?? $group->value ?? $group->name)
                    : $group;

                return $groupLabel === 'Laporan';
            })
            ->map(function (string $pageClass) {
                return [
                    'label' => $pageClass::getNavigationLabel(),
                    'icon' => $pageClass::getNavigationIcon() ?? 'heroicon-o-document-chart-bar',
                    'url' => $pageClass::getUrl(),
                    'sort' => $pageClass::getNavigationSort() ?? 999,
                    'permission' => 'View:' . class_basename($pageClass),
                ];
            })
            ->filter(fn(array $item) => auth()->user()?->can($item['permission']) ?? false)
            ->sortBy('sort')
            ->values()
            ->toArray();

        return $laporanPages;
    }
}