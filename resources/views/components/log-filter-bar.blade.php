@props(['bar', 'count' => 0])

@php
    $inputClass = 'text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500';
@endphp

<div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 p-3 mb-5 flex items-center gap-3 flex-wrap shadow-sm">
    <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Filter:</span>

    {{-- Pencarian bebas --}}
    <div class="relative">
        <input type="search" wire:model.live.debounce.400ms="search"
            placeholder="{{ $bar['placeholder'] }}"
            class="{{ $inputClass }} w-64 pr-7" />
        <span wire:loading wire:target="search" class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400">…</span>
    </div>

    {{-- Dropdown --}}
    @foreach ($bar['filters'] as $f)
        <select wire:model.live="filters.{{ $f['key'] }}" class="{{ $inputClass }}" title="{{ $f['label'] }}">
            <option value="">{{ $f['all'] }}</option>
            @foreach ($f['options'] as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    @endforeach

    {{-- Rentang tanggal --}}
    @if ($bar['hasDate'])
        <div class="flex items-center gap-1.5">
            <input type="date" wire:model.live="tanggalDari" class="{{ $inputClass }}" title="Dari tanggal" />
            <span class="text-[10px] text-gray-400 uppercase">s/d</span>
            <input type="date" wire:model.live="tanggalSampai" class="{{ $inputClass }}" title="Sampai tanggal" />
        </div>
    @endif

    {{ $slot }}

    @if ($bar['active'] > 0)
        <button type="button" wire:click="resetLogFilters"
            class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1.5 rounded-sm border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
            Reset ({{ $bar['active'] }})
        </button>
    @endif

    <span class="ml-auto text-[10px] font-black uppercase tracking-widest text-gray-400">{{ number_format($count) }} entri log</span>
</div>

@if ($count === 0 && $bar['active'] > 0)
    <div class="mb-5 px-4 py-3 rounded-sm border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/10 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-3 flex-wrap">
        <span>Tidak ada log yang cocok dengan filter/pencarian saat ini. Coba kurangi kata kunci atau ubah filter.</span>
        <button type="button" wire:click="resetLogFilters" class="font-black uppercase tracking-wider underline">Reset semua filter</button>
    </div>
@endif
