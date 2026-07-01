@php
    $children    = $node->children;
    $subAkuns    = $node->subAnakAkuns;
    $hasChildren = $children->isNotEmpty();
    $hasSub      = $subAkuns->isNotEmpty();
    $hasAny      = $hasChildren || $hasSub;
    $saldo       = $node->saldo_normal;
    $totalBelow  = $children->count() + $subAkuns->count();
    $nodeId      = 'node-' . $node->id . '-' . $level;
@endphp

<div class="tree-node level-{{ $level }}" id="{{ $nodeId }}">

    <div
        class="tree-node-row {{ $hasAny ? 'clickable' : '' }}"
        data-node-search="{{ strtolower($node->kode_anak_akun . ' ' . $node->nama_anak_akun) }}"
        @if($hasAny) onclick="toggleNode(this)" @endif
    >
        @if($hasAny)
            <span class="tree-chevron" style="transition: transform 0.2s ease; flex-shrink:0;">
                <svg width="9" height="9" viewBox="0 0 10 10" fill="currentColor">
                    <path d="M3 2l4 3-4 3V2z"/>
                </svg>
            </span>
        @else
            <span style="width:20px; flex-shrink:0;"></span>
        @endif

        <span class="tree-node-code">{{ $node->kode_anak_akun }}</span>

        <span class="tree-node-name" title="{{ $node->nama_anak_akun }}">
            {{ $node->nama_anak_akun }}
        </span>

        <div class="tree-node-meta">
            @if($saldo)
                <span class="badge badge-{{ $saldo }}">{{ strtoupper(substr($saldo,0,2)) }}</span>
            @endif
            @if($totalBelow > 0)
                <span class="count-pill">{{ $totalBelow }}</span>
            @endif
        </div>
    </div>

    @if($hasAny)
        <div class="tree-node-children collapsed">

            @foreach($children as $child)
                @include('filament.components._tree_node', [
                    'node'  => $child,
                    'level' => $level + 1
                ])
            @endforeach

            @foreach($subAkuns as $sub)
                <div class="tree-node level-leaf">
                    <div
                        class="tree-leaf-row"
                        data-node-search="{{ strtolower($sub->kode_sub_anak_akun . ' ' . $sub->nama_sub_anak_akun) }}"
                    >
                        <span class="tree-leaf-dot"></span>
                        <span class="tree-leaf-code">{{ $sub->kode_sub_anak_akun }}</span>
                        <span class="tree-leaf-name" title="{{ $sub->nama_sub_anak_akun }}">{{ $sub->nama_sub_anak_akun }}</span>
                        <div class="tree-node-meta">
                            @if($sub->saldo_normal)
                                <span class="badge badge-{{ $sub->saldo_normal }}">{{ strtoupper(substr($sub->saldo_normal,0,2)) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

        </div>
    @endif

</div>
