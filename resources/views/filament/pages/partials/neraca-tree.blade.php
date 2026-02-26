@php
    $indentClass = 'ml-' . ($level * 4);
@endphp

{{-- NAMA GROUP --}}
<div class="{{ $indentClass }} font-semibold mt-4">
    {{ $group['nama'] }}
</div>

{{-- DETAIL AKUN --}}
@if(!empty($group['accounts']))
    @foreach($group['accounts'] as $akun)
        <div class="{{ 'ml-' . (($level + 1) * 4) }} flex justify-between text-sm py-1 text-gray-600 dark:text-gray-300">

            <div>{{ $akun['kode'] }} - {{ $akun['nama'] }}</div>

            <div>
                Rp {{ number_format($akun['total'], 4, ',', '.') }}
            </div>
        </div>
    @endforeach
@endif

{{-- CHILD GROUP --}}
@if(!empty($group['children']))
    @foreach($group['children'] as $child)
        @include('filament.pages.partials.neraca-tree', [
            'group' => $child,
            'level' => $level + 1
        ])
    @endforeach
@endif

{{-- TOTAL GROUP --}}
<div class="{{ $indentClass }} flex justify-between font-bold border-t-2 border-gray-800 dark:border-gray-300 mt-2 pt-2">
    <div>Total {{ $group['nama'] }}</div>
    <div>
        Rp {{ number_format($group['total'], 4, ',', '.') }}
    </div>
</div>