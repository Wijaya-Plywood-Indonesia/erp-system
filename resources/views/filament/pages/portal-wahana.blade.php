<x-filament-panels::page>
    @php
        $colorClasses = [
            'amber'   => ['hex' => '#f59e0b', 'iconBg' => 'bg-amber-100 dark:bg-amber-500/10',   'iconText' => 'text-amber-600 dark:text-amber-400',   'headerBg' => 'bg-amber-50/60 dark:bg-amber-500/[0.04]'],
            'orange'  => ['hex' => '#f97316', 'iconBg' => 'bg-orange-100 dark:bg-orange-500/10', 'iconText' => 'text-orange-600 dark:text-orange-400', 'headerBg' => 'bg-orange-50/60 dark:bg-orange-500/[0.04]'],
            'blue'    => ['hex' => '#3b82f6', 'iconBg' => 'bg-blue-100 dark:bg-blue-500/10',     'iconText' => 'text-blue-600 dark:text-blue-400',     'headerBg' => 'bg-blue-50/60 dark:bg-blue-500/[0.04]'],
            'emerald' => ['hex' => '#10b981', 'iconBg' => 'bg-emerald-100 dark:bg-emerald-500/10', 'iconText' => 'text-emerald-600 dark:text-emerald-400', 'headerBg' => 'bg-emerald-50/60 dark:bg-emerald-500/[0.04]'],
            'cyan'    => ['hex' => '#06b6d4', 'iconBg' => 'bg-cyan-100 dark:bg-cyan-500/10',     'iconText' => 'text-cyan-600 dark:text-cyan-400',     'headerBg' => 'bg-cyan-50/60 dark:bg-cyan-500/[0.04]'],
            'pink'    => ['hex' => '#ec4899', 'iconBg' => 'bg-pink-100 dark:bg-pink-500/10',     'iconText' => 'text-pink-600 dark:text-pink-400',     'headerBg' => 'bg-pink-50/60 dark:bg-pink-500/[0.04]'],
            'lime'    => ['hex' => '#84cc16', 'iconBg' => 'bg-lime-100 dark:bg-lime-500/10',     'iconText' => 'text-lime-600 dark:text-lime-400',     'headerBg' => 'bg-lime-50/60 dark:bg-lime-500/[0.04]'],
        ];

        $groups = collect($this->getModuleGroups())->map(function ($g) use ($colorClasses) {
            $c = $colorClasses[$g['color']] ?? $colorClasses['blue'];
            $g['borderHex'] = $c['hex']; // dipakai via inline style, anti ketiban CSS lain
            $g['iconBg'] = $c['iconBg'];
            $g['iconText'] = $c['iconText'];
            $g['headerBg'] = $c['headerBg'];
            $g['iconSvg'] = \Illuminate\Support\Facades\Blade::render(
                '<x-filament::icon icon="' . ($g['icon'] ?? 'heroicon-o-squares-2x2') . '" class="w-5 h-5" />'
            );
            return $g;
        })->values()->toArray();

        $totalMenu = collect($groups)->sum(fn ($g) => $g['count'] ?? 0);
    @endphp

    <div
        x-data="{
            search: '',
            showClass: false,
            groups: @js($groups),
            get filteredGroups() {
                if (!this.search.trim()) return this.groups;
                const q = this.search.toLowerCase();
                return this.groups.map(g => {
                    if (g.sections) {
                        const sections = {};
                        Object.entries(g.sections).forEach(([k, items]) => {
                            const f = items.filter(i => i.label.toLowerCase().includes(q));
                            if (f.length) sections[k] = f;
                        });
                        return { ...g, sections, count: Object.values(sections).flat().length };
                    }
                    const items = (g.items || []).filter(i => i.label.toLowerCase().includes(q));
                    return { ...g, items, count: items.length };
                }).filter(g => g.count > 0);
            }
        }"
        class="space-y-5"
    >
        {{-- Deskripsi singkat, sama seperti mockup, tanpa badge --}}
        <p class="text-gray-500 dark:text-gray-400 text-sm max-w-3xl">
            Satu pintu ke seluruh modul {{ \Filament\Facades\Filament::getCurrentPanel()?->getBrandName() ?? 'Wahana' }} ERP, dikelompokkan per divisi.
            Menu yang tampil menyesuaikan hak akses akun Anda &mdash; setiap baris membuka tepat satu menu, tidak ada menu gabungan.
            Ketik untuk mencari langsung sampai ke level menu.
        </p>

        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4 flex-wrap">
                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">
                    Semua Modul &middot; <span x-text="filteredGroups.reduce((a,g)=>a+g.count,0)"></span> menu
                </div>
                <label class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 cursor-pointer select-none">
                    <input type="checkbox" x-model="showClass" class="rounded border-gray-300 dark:border-white/10 text-primary-600 focus:ring-primary-500/40">
                    Tampilkan nama class
                </label>
            </div>

            <div class="relative w-full max-w-md">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                    x-model="search"
                    type="text"
                    placeholder="Cari menu... (Ctrl+K)"
                    class="w-full rounded-xl bg-white dark:bg-gray-900 border-2 border-gray-300 dark:border-white/10 pl-11 pr-4 py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 shadow-sm focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20 transition-all"
                    x-init="window.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); $el.focus(); } })"
                >
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <template x-for="group in filteredGroups" :key="group.label">
                {{--
                    Catatan fix dark mode: border kiri (warna) dipisah total dari
                    border 3 sisi lain (netral), supaya dark:border-white/5 tidak
                    pernah bisa menimpa balik warna border-l di dark mode.
                --}}
                <div
                    class="bg-white dark:bg-gray-950 border-t border-r border-b border-gray-200 dark:border-white/5 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg dark:hover:shadow-black/40 hover:-translate-y-0.5 transition-all duration-200 flex flex-col max-h-[420px]"
                    :style="`border-left: 3px solid ${group.borderHex}`"
                >
                    <div class="w-full px-5 py-4 border-b border-gray-200 dark:border-white/5 flex items-center gap-3" :class="group.headerBg">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :class="[group.iconBg, group.iconText]" x-html="group.iconSvg"></div>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-gray-800 dark:text-gray-200 text-sm" x-text="group.label"></h3>
                            <p class="text-[10px] text-gray-500 dark:text-gray-500" x-text="group.count + ' menu'"></p>
                        </div>
                    </div>

                    <div class="p-2.5 overflow-y-auto space-y-0.5 flex-1 scrollbar-hide">
                        <template x-if="group.items">
                            <template x-for="item in group.items" :key="item.class">
                                <a :href="item.url" class="flex flex-col px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/5 group hover:pl-4 transition-all">
                                    <span class="text-sm text-gray-600 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white" x-text="item.label"></span>
                                    <span x-show="showClass" x-text="item.shortClass" class="text-[10px] text-gray-400 dark:text-gray-500 font-mono"></span>
                                </a>
                            </template>
                        </template>

                        <template x-if="group.sections">
                            <template x-for="(items, sectionName) in group.sections" :key="sectionName">
                                <div>
                                    <p class="px-3 pt-1 pb-1 text-[10px] font-bold text-primary-600 dark:text-primary-400 uppercase tracking-wider" x-text="sectionName"></p>
                                    <template x-for="item in items" :key="item.class">
                                        <a :href="item.url" class="flex flex-col px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/5 group hover:pl-4 transition-all">
                                            <span class="text-sm text-gray-600 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white" x-text="item.label"></span>
                                            <span x-show="showClass" x-text="item.shortClass" class="text-[10px] text-gray-400 dark:text-gray-500 font-mono"></span>
                                        </a>
                                    </template>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <style>
        .scrollbar-hide {
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
    </style>
</x-filament-panels::page>