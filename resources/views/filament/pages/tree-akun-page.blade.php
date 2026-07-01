<x-filament-panels::page>

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    /* ===== ROOT VARIABLES ===== */
    :root {
        --tree-font-body: 'DM Sans', sans-serif;
        --tree-font-mono: 'IBM Plex Mono', monospace;

        --tree-bg:            #f8f9fc;
        --tree-card-bg:       #ffffff;
        --tree-border:        #e4e7f0;
        --tree-line:          #d1d5e8;
        --tree-text-primary:  #1a1d2e;
        --tree-text-secondary:#5a6080;
        --tree-text-muted:    #9aa0bb;
        --tree-hover:         #f1f3fc;
        --tree-active:        #eef0ff;

        --lvl-0-accent: #4f46e5;
        --lvl-1-accent: #0891b2;
        --lvl-2-accent: #7c3aed;
        --lvl-3-accent: #059669;
        --lvl-4-accent: #d97706;
        --lvl-leaf-accent: #64748b;

        --badge-debet-bg:  #eff6ff;
        --badge-debet-txt: #1d4ed8;
        --badge-kredit-bg: #fdf2f8;
        --badge-kredit-txt:#9d174d;

        --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
    }

    .dark {
        --tree-bg:            #0f1117;
        --tree-card-bg:       #161822;
        --tree-border:        #252838;
        --tree-line:          #2e3249;
        --tree-text-primary:  #e8eaf4;
        --tree-text-secondary:#9aa0bb;
        --tree-text-muted:    #5a6080;
        --tree-hover:         #1c1f2e;
        --tree-active:        #1e2140;

        --badge-debet-bg:  #1a2540;
        --badge-debet-txt: #60a5fa;
        --badge-kredit-bg: #2a1530;
        --badge-kredit-txt:#f472b6;

        --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.4);
    }

    .tree-wrapper { font-family: var(--tree-font-body); color: var(--tree-text-primary); }

    /* TOOLBAR */
    .tree-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
    .tree-search { flex: 1; min-width: 200px; position: relative; }
    .tree-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--tree-text-muted); pointer-events: none; }
    .tree-search input {
        width: 100%; padding: 9px 14px 9px 38px;
        background: var(--tree-card-bg); border: 1.5px solid var(--tree-border);
        border-radius: 10px; font-family: var(--tree-font-body); font-size: 13.5px;
        color: var(--tree-text-primary); box-shadow: var(--shadow-sm);
        transition: border-color 0.2s, box-shadow 0.2s; outline: none;
    }
    .tree-search input::placeholder { color: var(--tree-text-muted); }
    .tree-search input:focus { border-color: var(--lvl-0-accent); box-shadow: 0 0 0 3px rgba(79,70,229,0.12); }

    .tree-action-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 14px; border-radius: 9px; font-family: var(--tree-font-body);
        font-size: 12.5px; font-weight: 500; cursor: pointer;
        border: 1.5px solid var(--tree-border); background: var(--tree-card-bg);
        color: var(--tree-text-secondary); box-shadow: var(--shadow-sm);
        transition: all 0.15s ease; white-space: nowrap;
    }
    .tree-action-btn:hover { border-color: var(--lvl-0-accent); color: var(--lvl-0-accent); background: var(--tree-active); }

    .tree-stats {
        font-size: 12px; color: var(--tree-text-muted);
        padding: 7px 12px; background: var(--tree-card-bg);
        border: 1.5px solid var(--tree-border); border-radius: 9px;
        box-shadow: var(--shadow-sm); white-space: nowrap;
    }

    /* LEGEND */
    .tree-legend {
        display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px;
        padding: 12px 14px; background: var(--tree-card-bg);
        border: 1.5px solid var(--tree-border); border-radius: 10px; box-shadow: var(--shadow-sm);
    }
    .legend-item { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; color: var(--tree-text-secondary); font-weight: 500; }
    .legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

    /* TREE CONTAINER */
    .tree-container { display: flex; flex-direction: column; gap: 8px; }

    /* INDUK CARD */
    .tree-induk-card {
        background: var(--tree-card-bg); border: 1.5px solid var(--tree-border);
        border-radius: 14px; overflow: hidden; box-shadow: var(--shadow-sm);
        transition: box-shadow 0.2s;
    }
    .tree-induk-card:hover { box-shadow: var(--shadow-md); }

    .tree-induk-header { display: flex; align-items: center; cursor: pointer; transition: background 0.15s; position: relative; }
    .tree-induk-header:hover { background: var(--tree-hover); }
    .tree-induk-header[aria-expanded="true"] { background: var(--tree-active); }
    .tree-induk-strip { width: 5px; align-self: stretch; background: var(--lvl-0-accent); flex-shrink: 0; }
    .tree-induk-content { flex: 1; display: flex; align-items: center; gap: 12px; padding: 14px 16px; min-width: 0; }

    .tree-chevron {
        width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;
        border-radius: 5px; background: rgba(79,70,229,0.1); color: var(--lvl-0-accent);
        flex-shrink: 0; transition: transform 0.2s ease;
    }
    [aria-expanded="true"] .tree-chevron { transform: rotate(90deg); }

    .tree-code {
        font-family: var(--tree-font-mono); font-size: 11.5px; font-weight: 600;
        padding: 3px 8px; border-radius: 6px;
        background: rgba(79,70,229,0.08); color: var(--lvl-0-accent);
        flex-shrink: 0; letter-spacing: 0.03em;
    }
    .tree-name { font-size: 14px; font-weight: 600; color: var(--tree-text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tree-meta { display: flex; align-items: center; gap: 8px; margin-left: auto; flex-shrink: 0; padding-right: 16px; }

    .badge { font-family: var(--tree-font-mono); font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 20px; letter-spacing: 0.05em; text-transform: uppercase; }
    .badge-debet  { background: var(--badge-debet-bg);  color: var(--badge-debet-txt); }
    .badge-kredit { background: var(--badge-kredit-bg); color: var(--badge-kredit-txt); }

    .count-pill { font-size: 11px; font-weight: 500; color: var(--tree-text-muted); background: var(--tree-hover); border: 1px solid var(--tree-border); padding: 2px 8px; border-radius: 20px; }

    /* CHILDREN */
    .tree-children-wrap { display: none; border-top: 1.5px solid var(--tree-border); }
    .tree-children-wrap.open { display: block; }
    .tree-body { padding: 10px 10px 10px 20px; display: flex; flex-direction: column; gap: 4px; }

    /* TREE NODE */
    .tree-node { position: relative; display: flex; flex-direction: column; }
    .tree-node::before { content: ''; position: absolute; left: -12px; top: 0; bottom: 0; width: 1.5px; background: var(--tree-line); }
    .tree-node:last-child::before { height: 22px; }
    .tree-node::after { content: ''; position: absolute; left: -12px; top: 20px; width: 10px; height: 1.5px; background: var(--tree-line); }

    .tree-node-row { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 9px; cursor: default; transition: background 0.12s, border-left 0.12s, padding-left 0.12s; position: relative; border-left: 2.5px solid transparent; }
    .tree-node-row.clickable { cursor: pointer; }
    .tree-node-row:hover { background: var(--tree-hover); }
    .tree-node-row.open-row { background: var(--tree-active); }

    .tree-node-code { font-family: var(--tree-font-mono); font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 5px; flex-shrink: 0; letter-spacing: 0.03em; }
    .tree-node-name { font-size: 13.5px; font-weight: 500; color: var(--tree-text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
    .tree-node-meta { display: flex; align-items: center; gap: 6px; margin-left: auto; flex-shrink: 0; }

    /* Level colors */
    .level-1 .tree-node-code { background: rgba(8,145,178,0.1); color: var(--lvl-1-accent); }
    .level-1 > .tree-node-row.clickable:hover, .level-1 > .tree-node-row.open-row { border-left-color: var(--lvl-1-accent); }
    .level-1 .tree-chevron   { background: rgba(8,145,178,0.1); color: var(--lvl-1-accent); }

    .level-2 .tree-node-code { background: rgba(124,58,237,0.1); color: var(--lvl-2-accent); }
    .level-2 > .tree-node-row.clickable:hover, .level-2 > .tree-node-row.open-row { border-left-color: var(--lvl-2-accent); }
    .level-2 .tree-chevron   { background: rgba(124,58,237,0.1); color: var(--lvl-2-accent); }

    .level-3 .tree-node-code { background: rgba(5,150,105,0.1); color: var(--lvl-3-accent); }
    .level-3 > .tree-node-row.clickable:hover, .level-3 > .tree-node-row.open-row { border-left-color: var(--lvl-3-accent); }
    .level-3 .tree-chevron   { background: rgba(5,150,105,0.1); color: var(--lvl-3-accent); }

    .level-4 .tree-node-code { background: rgba(217,119,6,0.1); color: var(--lvl-4-accent); }
    .level-4 > .tree-node-row.clickable:hover, .level-4 > .tree-node-row.open-row { border-left-color: var(--lvl-4-accent); }
    .level-4 .tree-chevron   { background: rgba(217,119,6,0.1); color: var(--lvl-4-accent); }

    /* Nested children */
    .tree-node-children { padding-left: 22px; padding-top: 4px; padding-bottom: 2px; position: relative; display: flex; flex-direction: column; gap: 3px; }
    .tree-node-children.collapsed { display: none; }

    /* LEAF NODE */
    .tree-leaf-row { display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-radius: 8px; transition: background 0.12s; }
    .tree-leaf-row:hover { background: var(--tree-hover); }
    .tree-leaf-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--tree-text-muted); flex-shrink: 0; margin: 0 2px; }
    .tree-leaf-code { font-family: var(--tree-font-mono); font-size: 10.5px; color: var(--tree-text-muted); flex-shrink: 0; letter-spacing: 0.02em; }
    .tree-leaf-name { font-size: 13px; font-weight: 400; color: var(--tree-text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }

    /* EMPTY STATE */
    .tree-empty { text-align: center; padding: 48px 24px; color: var(--tree-text-muted); font-size: 14px; display: none; }
    .tree-empty.visible { display: block; }

    @media (max-width: 640px) {
        .count-pill { display: none; }
        .tree-stats { display: none; }
    }
</style>
@endpush

<div class="tree-wrapper" id="treeWrapper">

    {{-- Toolbar --}}
    <div class="tree-toolbar">
        <div class="tree-search">
            <span class="tree-search-icon">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
            </span>
            <input type="text" id="treeSearch" placeholder="Cari kode atau nama akun..."
                   oninput="treeSearch(this.value)" autocomplete="off" />
        </div>
        <button class="tree-action-btn" onclick="treeExpandAll()">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
            </svg>
            Expand Semua
        </button>
        <button class="tree-action-btn" onclick="treeCollapseAll()">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/>
            </svg>
            Collapse Semua
        </button>
        <div class="tree-stats">
            {{ $indukAkuns->count() }} induk &middot;
            {{ $indukAkuns->sum(fn($i) => $i->allAnakAkuns->count()) }} akun
        </div>
    </div>

    {{-- Legend --}}
    <div class="tree-legend">
        <span class="legend-item"><span class="legend-dot" style="background:var(--lvl-0-accent)"></span>Induk Akun</span>
        <span class="legend-item"><span class="legend-dot" style="background:var(--lvl-1-accent)"></span>Anak Akun</span>
        <span class="legend-item"><span class="legend-dot" style="background:var(--lvl-2-accent)"></span>Sub Anak</span>
        <span class="legend-item"><span class="legend-dot" style="background:var(--lvl-3-accent)"></span>Sub-Sub Anak</span>
        <span class="legend-item"><span class="legend-dot" style="background:var(--lvl-leaf-accent)"></span>Detail Akun</span>
        &nbsp;
        <span class="legend-item"><span class="badge badge-debet" style="font-size:9px">DB</span> Debet</span>
        <span class="legend-item"><span class="badge badge-kredit" style="font-size:9px">CR</span> Kredit</span>
    </div>

    {{-- Tree --}}
    <div class="tree-container" id="treeContainer">
        @foreach ($indukAkuns as $induk)
            @php $totalAnak = $induk->allAnakAkuns->count(); @endphp
            <div class="tree-induk-card" id="induk-{{ $induk->id }}">
                <div class="tree-induk-header" aria-expanded="false"
                     onclick="toggleInduk(this)" data-id="{{ $induk->id }}"
                     data-search="{{ strtolower($induk->kode_induk_akun . ' ' . $induk->nama_induk_akun) }}">
                    <div class="tree-induk-strip"></div>
                    <div class="tree-induk-content">
                        <span class="tree-chevron">
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor"><path d="M3 2l4 3-4 3V2z"/></svg>
                        </span>
                        <span class="tree-code">{{ $induk->kode_induk_akun }}</span>
                        <span class="tree-name">{{ $induk->nama_induk_akun }}</span>
                    </div>
                    <div class="tree-meta">
                        @if($induk->saldo_normal)
                            <span class="badge badge-{{ $induk->saldo_normal }}">{{ strtoupper(substr($induk->saldo_normal,0,2)) }}</span>
                        @endif
                        @if($totalAnak > 0)
                            <span class="count-pill">{{ $totalAnak }} akun</span>
                        @endif
                    </div>
                </div>

                {{-- ============================================================
                     PERUBAHAN: whereNull('parent') ditambahkan agar hanya
                     root nodes (akun tanpa parent) yang dirender di level ini.
                     Akun yang punya parent (seperti "Kas" dengan parent=1) akan
                     muncul sebagai children dari induknya, bukan dobel di sini.
                     ============================================================ --}}
                @if($induk->anakAkuns->whereNull('parent')->isNotEmpty())
                    <div class="tree-children-wrap" id="children-induk-{{ $induk->id }}">
                        <div class="tree-body">
                            @foreach($induk->anakAkuns->whereNull('parent') as $anak)
                                @include('filament.components._tree_node', ['node' => $anak, 'level' => 1])
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        @endforeach
    </div>

    <div class="tree-empty" id="treeEmpty">
        <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin: 0 auto 12px; color: var(--tree-text-muted)">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <div>Tidak ada akun yang cocok dengan pencarian.</div>
    </div>

</div>

@push('scripts')
<script>
    function toggleInduk(el) {
        const expanded = el.getAttribute('aria-expanded') === 'true';
        const id = el.dataset.id;
        const children = document.getElementById('children-induk-' + id);
        el.setAttribute('aria-expanded', String(!expanded));
        if (children) children.classList.toggle('open', !expanded);
    }

    function toggleNode(rowEl) {
        const node = rowEl.closest('.tree-node');
        const childrenEl = node.querySelector(':scope > .tree-node-children');
        if (!childrenEl) return;
        const isOpen = !childrenEl.classList.contains('collapsed');
        childrenEl.classList.toggle('collapsed', isOpen);
        rowEl.classList.toggle('open-row', !isOpen);
        const chevron = rowEl.querySelector('.tree-chevron');
        if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(90deg)';
    }

    function treeExpandAll() {
        document.querySelectorAll('.tree-induk-header').forEach(h => h.setAttribute('aria-expanded', 'true'));
        document.querySelectorAll('.tree-children-wrap').forEach(el => el.classList.add('open'));
        document.querySelectorAll('.tree-node-children').forEach(el => el.classList.remove('collapsed'));
        document.querySelectorAll('.tree-node-row.clickable').forEach(el => {
            el.classList.add('open-row');
            const chevron = el.querySelector('.tree-chevron');
            if (chevron) chevron.style.transform = 'rotate(90deg)';
        });
    }

    function treeCollapseAll() {
        document.querySelectorAll('.tree-induk-header').forEach(h => h.setAttribute('aria-expanded', 'false'));
        document.querySelectorAll('.tree-children-wrap').forEach(el => el.classList.remove('open'));
        document.querySelectorAll('.tree-node-children').forEach(el => el.classList.add('collapsed'));
        document.querySelectorAll('.tree-node-row').forEach(el => {
            el.classList.remove('open-row');
            const chevron = el.querySelector('.tree-chevron');
            if (chevron) chevron.style.transform = '';
        });
    }

    let searchTimeout;
    function treeSearch(query) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => doSearch(query), 200);
    }

    function doSearch(query) {
        const q = query.toLowerCase().trim();
        const container = document.getElementById('treeContainer');
        const empty = document.getElementById('treeEmpty');

        if (!q) {
            container.querySelectorAll('.tree-induk-card').forEach(c => c.style.display = '');
            treeCollapseAll();
            empty.classList.remove('visible');
            return;
        }

        container.querySelectorAll('.tree-induk-card').forEach(card => card.style.display = 'none');

        let anyVisible = false;

        const showCard = (card, indukHeader) => {
            card.style.display = '';
            anyVisible = true;
            const id = indukHeader.dataset.id;
            indukHeader.setAttribute('aria-expanded', 'true');
            const ch = document.getElementById('children-induk-' + id);
            if (ch) ch.classList.add('open');
        };

        container.querySelectorAll('[data-search]').forEach(el => {
            if (el.getAttribute('data-search').includes(q)) {
                const card = el.closest('.tree-induk-card');
                if (card) showCard(card, el);
            }
        });

        container.querySelectorAll('[data-node-search]').forEach(el => {
            if (el.getAttribute('data-node-search').includes(q)) {
                const card = el.closest('.tree-induk-card');
                const header = card?.querySelector('.tree-induk-header');
                if (card && header) showCard(card, header);

                // Expand parent nodes
                let parent = el.closest('.tree-node-children');
                while (parent) {
                    parent.classList.remove('collapsed');
                    const row = parent.previousElementSibling;
                    if (row) {
                        row.classList.add('open-row');
                        const chevron = row.querySelector('.tree-chevron');
                        if (chevron) chevron.style.transform = 'rotate(90deg)';
                    }
                    parent = parent.closest('.tree-node')?.closest('.tree-node-children');
                }
            }
        });

        empty.classList.toggle('visible', !anyVisible);
    }
</script>
@endpush

</x-filament-panels::page>