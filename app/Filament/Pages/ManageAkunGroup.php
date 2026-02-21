<?php

namespace App\Filament\Pages;

use App\Models\AkunGroup;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use BackedEnum;
use UnitEnum;
class ManageAkunGroup extends Page
{
    protected static ?string $navigationLabel = 'Kelola Grup Akun';
    protected static ?string $title = 'Kelola Struktur Grup Akun';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'filament.pages.manage-akun-group';

    public $tree = [];

    public function mount()
    {
        $this->loadTree();
    }

    public function loadTree()
    {
        $this->tree = $this->buildTree(null);
    }

    private function buildTree($parentId)
    {
        return AkunGroup::where('parent_id', $parentId)
            ->orderBy('order')
            ->get()
            ->map(fn($group) => [
                'id' => $group->id,
                'nama' => $group->nama,
                'hidden' => $group->hidden,
                'akun' => $group->akun,
                'children' => $this->buildTree($group->id),
            ])
            ->toArray();
    }

    public function saveTree($newTree)
    {
        $this->updateNode($newTree, null);

        $this->loadTree();
    }

    private function updateNode($nodes, $parentId)
    {
        foreach ($nodes as $index => $node) {

            AkunGroup::where('id', $node['id'])->update([
                'parent_id' => $parentId,
                'order' => $index,
                'akun' => $node['akun'] ?? [],
            ]);

            if (!empty($node['children'])) {
                $this->updateNode($node['children'], $node['id']);
            }
        }
    }

    public function addGroup($parentId)
    {
        AkunGroup::create([
            'nama' => 'Grup Baru',
            'parent_id' => $parentId,
            'akun' => [],
            'order' => 999,
        ]);

        $this->loadTree();
    }

    public function renameGroup($id, $name)
    {
        AkunGroup::where('id', $id)->update(['nama' => $name]);
        $this->loadTree();
    }

    public function deleteGroup($id)
    {
        AkunGroup::where('id', $id)->delete();
        $this->loadTree();
    }
}