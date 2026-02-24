<x-filament-panels::page>
<div class="space-y-6" x-data="neracaUI()">

    {{-- ==================== FILTER PERIODE ==================== --}}
    <div class="p-4 rounded-xl shadow-sm border bg-white dark:bg-gray-800 dark:border-gray-700">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

            {{-- BULAN MULAI --}}
            <div>
                <label class="font-semibold text-sm dark:text-gray-200">Bulan Mulai</label>
                <select wire:model="bulan_mulai" wire:change="loadData"
                    class="w-full border rounded-lg p-2 dark:bg-gray-700">
                    @foreach(range(1,12) as $bln)
                        <option value="{{ $bln }}">{{ \Carbon\Carbon::create()->month($bln)->translatedFormat('F') }}</option>
                    @endforeach
                </select>
            </div>

            {{-- TAHUN MULAI --}}
            <div>
                <label class="font-semibold text-sm dark:text-gray-200">Tahun Mulai</label>
                <input type="number" wire:model="tahun_mulai" wire:change="loadData"
                    class="w-full border rounded-lg p-2 dark:bg-gray-700">
            </div>

            {{-- BULAN AKHIR --}}
            <div>
                <label class="font-semibold text-sm dark:text-gray-200">Bulan Akhir</label>
                <select wire:model="bulan_akhir" wire:change="loadData"
                    class="w-full border rounded-lg p-2 dark:bg-gray-700">
                    @foreach(range(1,12) as $bln)
                        <option value="{{ $bln }}">{{ \Carbon\Carbon::create()->month($bln)->translatedFormat('F') }}</option>
                    @endforeach
                </select>
            </div>

            {{-- TAHUN AKHIR --}}
            <div>
                <label class="font-semibold text-sm dark:text-gray-200">Tahun Akhir</label>
                <input type="number" wire:model="tahun_akhir" wire:change="loadData"
                    class="w-full border rounded-lg p-2 dark:bg-gray-700">
            </div>

        </div>

        <div class="mt-4 flex items-center gap-3">
            <button wire:click="debug"
                class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">
                Debug (dd)
            </button>

            <button @click="expandAll"
                class="px-3 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                Expand All
            </button>

            <button @click="collapseAll"
                class="px-3 py-2 rounded-lg bg-gray-700 text-white hover:bg-gray-800">
                Collapse All
            </button>
        </div>
    </div>


    {{-- ==================== PER PERIODE ==================== --}}
    @foreach($result as $periode)
    <div class="p-6 rounded-xl shadow-sm border bg-white dark:bg-gray-800 dark:border-gray-700">

        <h2 class="text-2xl font-bold mb-6 dark:text-gray-100">
            {{ $periode['label'] }}
        </h2>

        {{-- =============== 2 KOLOM: AKTIVA | PASIVA =============== --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- ===================== LEFT: AKTIVA ===================== --}}
            <div class="p-4 rounded-lg border shadow bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
                <h3 class="text-xl font-bold mb-4">AKTIVA</h3>

                @foreach($periode['groups'] as $group)
                    @if($group['nama'] !== 'AKTIVA')
                        @continue
                    @endif

                    @foreach($group['sub'] as $sub)
                        {{-- COLLAPSABLE WRAPPER --}}
                        <div class="mt-4" x-data="{ open: true }">

                            {{-- SUB HEADER --}}
                            <div class="flex items-center justify-between cursor-pointer"
                                @click="open = !open">
                                <h4 class="font-semibold dark:text-gray-200">{{ $sub['nama'] }}</h4>
                                <span x-text="open ? '-' : '+'" class="font-bold text-lg"></span>
                            </div>

                            {{-- CONTENT --}}
                            <div x-show="open" class="mt-2">
                                <table class="w-full text-sm">
                                    @foreach($sub['akun'] as $akun)
                                        <tr class="border-b dark:border-gray-700">
                                            <td class="p-2 w-28 font-mono">{{ $akun['kode'] }}</td>
                                            <td class="p-2">{{ $akun['nama'] }}</td>
                                            <td class="p-2 w-32 font-semibold text-right">
                                                {{ number_format($akun['saldo'], 0, ',', '.') }}
                                            </td>
                                        </tr>

                                        {{-- CHILDREN --}}
                                        @if(isset($akun['children']))
                                            @foreach($akun['children'] as $child)
                                                <tr class="bg-gray-100 dark:bg-gray-800">
                                                    <td class="p-2 pl-6 font-mono text-xs">{{ $child['kode'] }}</td>
                                                    <td class="p-2 text-xs">{{ $child['nama'] }}</td>
                                                    <td class="p-2 text-xs text-right">
                                                        {{ number_format($child['saldo'], 0, ',', '.') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    @endforeach
                                </table>
                            </div>

                        </div>
                    @endforeach

                @endforeach
            </div>



            {{-- ===================== RIGHT: PASIVA ===================== --}}
            <div class="p-4 rounded-lg border shadow bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
                <h3 class="text-xl font-bold mb-4">PASIVA</h3>

                @foreach($periode['groups'] as $group)
                    @if($group['nama'] !== 'PASIVA')
                        @continue
                    @endif

                    @foreach($group['sub'] as $sub)
                        {{-- COLLAPSABLE WRAPPER --}}
                        <div class="mt-4" x-data="{ open: true }">

                            {{-- SUB HEADER --}}
                            <div class="flex items-center justify-between cursor-pointer"
                                @click="open = !open">
                                <h4 class="font-semibold dark:text-gray-200">{{ $sub['nama'] }}</h4>
                                <span x-text="open ? '-' : '+'" class="font-bold text-lg"></span>
                            </div>

                            {{-- CONTENT --}}
                            <div x-show="open" class="mt-2">
                                <table class="w-full text-sm">
                                    @foreach($sub['akun'] as $akun)
                                        <tr class="border-b dark:border-gray-700">
                                            <td class="p-2 w-28 font-mono">{{ $akun['kode'] }}</td>
                                            <td class="p-2">{{ $akun['nama'] }}</td>
                                            <td class="p-2 w-32 font-semibold text-right">
                                                {{ number_format($akun['saldo'], 0, ',', '.') }}
                                            </td>
                                        </tr>

                                        {{-- CHILDREN --}}
                                        @if(isset($akun['children']))
                                            @foreach($akun['children'] as $child)
                                                <tr class="bg-gray-100 dark:bg-gray-800">
                                                    <td class="p-2 pl-6 font-mono text-xs">{{ $child['kode'] }}</td>
                                                    <td class="p-2 text-xs">{{ $child['nama'] }}</td>
                                                    <td class="p-2 text-xs text-right">
                                                        {{ number_format($child['saldo'], 0, ',', '.') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    @endforeach
                                </table>
                            </div>

                        </div>
                    @endforeach

                @endforeach
            </div>

        </div>

    </div>
    @endforeach

</div>




{{-- ========================== ALPINE JS GLOBAL ========================== --}}
@push('scripts')
@verbatim
<script>
function neracaUI() {
    return {
        expandAll() {
            document.querySelectorAll('[x-data]').forEach(el => {
                if (el.__x && el.__x.$data.open !== undefined) {
                    el.__x.$data.open = true;
                }
            });
        },
        collapseAll() {
            document.querySelectorAll('[x-data]').forEach(el => {
                if (el.__x && el.__x.$data.open !== undefined) {
                    el.__x.$data.open = false;
                }
            });
        }
    }
}
</script>
@endverbatim
@endpush

</x-filament-panels::page>