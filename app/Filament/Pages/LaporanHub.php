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

    protected function resolveIcon(mixed $icon): string
    {
        $value = null;

        if ($icon instanceof BackedEnum) {
            $value = $icon->value;
        } elseif (is_object($icon) && property_exists($icon, 'value')) {
            $value = $icon->value;
        } elseif (is_string($icon)) {
            $value = $icon;
        }

        if (blank($value)) {
            return 'heroicon-o-document-chart-bar';
        }

        if (!str_starts_with($value, 'heroicon-')) {
            $value = 'heroicon-' . $value;
        }

        return $value;
    }

    public function getLaporanList(): array
    {
        $pages = Filament::getPages(); // key = path, value = class string
        $resources = Filament::getResources(); // key = path, value = class string

        // Ambil laporan dari custom Page
        $fromPages = collect(array_values($pages))
            ->filter(function (string $pageClass) {
                if (!class_exists($pageClass) || !is_subclass_of($pageClass, Page::class)) {
                    return false;
                }

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
                    'icon' => $this->resolveIcon($pageClass::getNavigationIcon() ?? 'heroicon-o-document-chart-bar'),
                    'url' => $pageClass::getUrl(),
                    'sort' => $pageClass::getNavigationSort() ?? 999,
                    'permission' => 'View:' . class_basename($pageClass),
                ];
            });

        // Ambil laporan dari Resource
        $fromResources = collect(array_values($resources))
            ->filter(function (string $resourceClass) {
                if (!class_exists($resourceClass)) {
                    return false;
                }

                $group = $resourceClass::getNavigationGroup();
                $groupLabel = $group instanceof UnitEnum
                    ? ($group->getLabel() ?? $group->value ?? $group->name)
                    : $group;

                return $groupLabel === 'Laporan';
            })
            ->map(function (string $resourceClass) {
                return [
                    'label' => $resourceClass::getNavigationLabel(),
                    'icon' => $this->resolveIcon($resourceClass::getNavigationIcon() ?? 'heroicon-o-document-chart-bar'),
                    'url' => $resourceClass::getUrl('index'),
                    'sort' => $resourceClass::getNavigationSort() ?? 999,
                    'permission' => 'ViewAny:' . class_basename($resourceClass::getModel()),
                ];
            });

        return $fromPages
            ->merge($fromResources)
            ->filter(fn(array $item) => auth()->user()?->can($item['permission']) ?? false)
            ->sortBy('sort')
            ->values()
            ->toArray();
    }
}