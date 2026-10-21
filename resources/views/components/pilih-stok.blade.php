{{--
    Komponen: <x-gudang.pilih-stok />
    Pencarian stok fleksibel untuk pop up "Catat Barang Keluar".

    Props:
      items       : array of ['id','kayu','kw','p','l','t','sisa']  (p/l/t = panjang/lebar/tebal dalam mm)
      model       : nama properti Livewire yang menyimpan id stok terpilih (mis. selectedStokId)
      label       : teks label di atas input
      placeholder : placeholder input

    Cara cari (kata kunci dipisah spasi, urutan bebas, semua harus cocok):
      9m / 9mm / 9 mm -> tebal = 9 mm
      1220x2440x9     -> dimensi penuh atau sebagian (1220x2440, 2440x9)
      meranti / mer   -> nama jenis kayu (sebagian kata)
      kw a / kwa / a  -> KW (cocok persis)
      9 (angka polos) -> tebal = 9, atau panjang/lebar = 9
      Contoh: "9m meranti kw a"
--}}
@props([
    'items' => [],
    'model' => 'selectedStokId',
    'label' => 'Pilih Barang',
    'placeholder' => 'Ketik ukuran, jenis kayu, atau KW',
])

<div class="space-y-1.5 relative" x-data="{
        isDropdownOpen: false,
        searchTerm: '',
        selectedStokId: $wire.$entangle('{{ $model }}'),
        options: @js($items),

        fmt(n) { return parseFloat(Number(n).toFixed(2)).toString(); },
        dim(o) { return this.fmt(o.p) + 'x' + this.fmt(o.l) + 'x' + this.fmt(o.t); },
        kwNorm(o) { return String(o.kw ?? '').toLowerCase().replace(/^kw\s*/, '').replace(/\s+/g, ''); },
        kwLabel(o) {
            let k = String(o.kw ?? '').trim();
            if (k === '') return '-';
            return /^kw/i.test(k) ? k : 'KW ' + k;
        },
        nama(o) { return (o.kayu ?? '') + ' (' + this.kwLabel(o) + ') - Sisa: ' + Number(o.sisa).toLocaleString('en-US') + ' lbr'; },
        label(o) { return this.dim(o) + ' | ' + this.nama(o); },

        tokens() {
            let s = this.searchTerm.toLowerCase().trim();
            s = s.replace(/(\d),(\d)/g, '$1.$2')
                 .replace(/(\d)\s*(mm|m)\b/g, '$1mm')
                 .replace(/\bkw\s+(\S)/g, 'kw$1')
                 .replace(/\s*[x*×]\s*/g, 'x');
            return s.split(/\s+/).filter(Boolean);
        },
        same(a, b) { return Math.abs(Number(a) - Number(b)) < 0.005; },
        matchToken(o, t) {
            let m;
            if ((m = t.match(/^(\d+(?:\.\d+)?)mm$/))) return this.same(o.t, m[1]);
            if (/^\d+(?:\.\d+)?(?:x\d+(?:\.\d+)?)+x?$/.test(t)) {
                let parts = t.replace(/x$/, '').split('x');
                let dims = [o.p, o.l, o.t];
                for (let i = 0; i + parts.length <= 3; i++) {
                    if (parts.every((v, j) => this.same(dims[i + j], v))) return true;
                }
                return false;
            }
            t = t.replace(/x$/, '');
            if ((m = t.match(/^kw(.+)$/))) return this.kwNorm(o) === m[1];
            if (/^\d+(?:\.\d+)?$/.test(t)) {
                if (this.same(o.t, t) || this.same(o.p, t) || this.same(o.l, t) || this.kwNorm(o) === t) return true;
                return t.length >= 3 && (this.fmt(o.p).startsWith(t) || this.fmt(o.l).startsWith(t));
            }
            if (t.length === 1) return this.kwNorm(o) === t;
            return String(o.kayu ?? '').toLowerCase().includes(t) || this.kwNorm(o) === t;
        },
        get filteredOptions() {
            let ts = this.tokens();
            if (ts.length === 0) return this.options;
            return this.options.filter(o => ts.every(t => this.matchToken(o, t)));
        },
        selectItem(item) {
            this.selectedStokId = item.id;
            this.searchTerm = this.label(item);
            this.isDropdownOpen = false;
        },
        clearItem() {
            this.selectedStokId = null;
            this.searchTerm = '';
        },
        init() {
            let found = this.options.find(o => o.id == this.selectedStokId);
            if (found) this.searchTerm = this.label(found);
        }
    }" @click.away="isDropdownOpen = false">
    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">{{ $label }}</label>
    <div class="relative flex items-center">
        <input type="text" x-model="searchTerm" autocomplete="off"
            @focus="isDropdownOpen = true"
            @input="selectedStokId = null; isDropdownOpen = true"
            @keydown.enter.prevent="if (filteredOptions.length) selectItem(filteredOptions[0])"
            @keydown.escape="isDropdownOpen = false"
            placeholder="{{ $placeholder }}"
            class="w-full px-3 py-2 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-sm font-bold text-gray-900 dark:text-gray-100 outline-none pr-10 focus:border-amber-500 text-sm placeholder:text-gray-400 dark:placeholder:text-gray-600 placeholder:font-normal placeholder:text-xs">
        <button type="button" x-show="searchTerm.length > 0 || selectedStokId" @click="clearItem()"
            class="absolute right-3 text-gray-400 dark:text-gray-500 hover:text-red-500 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    <div x-show="isDropdownOpen" x-cloak
        class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-sm shadow-2xl max-h-48 overflow-y-auto p-1 divide-y divide-gray-100 dark:divide-gray-900/60">
        <template x-for="item in filteredOptions" :key="item.id">
            <button type="button" @click="selectItem(item)"
                class="w-full text-left px-3 py-2 hover:bg-amber-500 hover:text-gray-950 flex flex-col transition-colors group">
                <span class="text-[11px] text-gray-500 dark:text-gray-400 group-hover:text-gray-900 font-medium"
                    x-text="nama(item)"></span>
                <span class="font-bold text-gray-800 dark:text-gray-200 group-hover:text-gray-950 text-xs"
                    x-text="dim(item)"></span>
            </button>
        </template>
        <div x-show="filteredOptions.length === 0"
            class="text-gray-400 dark:text-gray-600 p-3 text-center italic text-[11px]">Barang tidak ditemukan</div>
    </div>
</div>