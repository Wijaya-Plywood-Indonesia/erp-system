<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referensi Harga Produksi</title>
    <!-- Google Fonts & Tailwind -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-panel {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .header-gradient {
            background: linear-gradient(135deg, #4f46e5 0%, #312e81 100%);
        }
        .btn-gradient {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen font-sans antialiased">

    <!-- Navbar/Header -->
    <div class="header-gradient text-white pb-32 pt-8 px-6 shadow-lg">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <div class="flex items-center gap-2 text-indigo-200 text-sm font-semibold uppercase tracking-wider">
                    <i class="fa-solid fa-book"></i> Jurnal / Modul Master
                </div>
                <h1 class="text-3xl font-bold mt-1 tracking-tight">Referensi Harga Produksi</h1>
                <p class="text-indigo-100 text-sm mt-1">Mengelola standar acuan harga produksi barang untuk jurnal produksi.</p>
            </div>
            <div>
                <a href="{{ route('referensi-harga-produksi.create') }}" class="btn-gradient hover:opacity-90 text-white font-medium px-5 py-3 rounded-xl shadow-md transition-all duration-200 flex items-center gap-2 transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-plus"></i> Tambah Referensi
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-6 -mt-24 pb-16">
        <!-- Success Alert -->
        @if(session('success'))
        <div id="alert-success" class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 p-4 rounded-r-xl shadow-sm flex items-center justify-between transition-all duration-300 transform scale-100">
            <div class="flex items-center gap-3">
                <div class="bg-emerald-500 text-white rounded-full p-1.5 flex items-center justify-center">
                    <i class="fa-solid fa-check text-xs"></i>
                </div>
                <span class="font-medium text-sm">{{ session('success') }}</span>
            </div>
            <button onclick="document.getElementById('alert-success').remove()" class="text-emerald-500 hover:text-emerald-800 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        @endif

        <div class="glass-panel rounded-2xl shadow-xl overflow-hidden">
            <!-- Search & Filters -->
            <div class="p-6 border-b border-slate-200/80 bg-white/50">
                <form action="{{ route('referensi-harga-produksi.index') }}" method="GET" class="flex flex-col md:flex-row gap-4 items-center justify-between">
                    <!-- Carry sort parameters -->
                    <input type="hidden" name="sort" value="{{ request('sort', 'created_at') }}">
                    <input type="hidden" name="order" value="{{ request('order', 'desc') }}">

                    <div class="relative w-full md:w-96">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari ukuran, jenis kayu, barang, kw..." 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                        @if(request('search'))
                            <a href="{{ route('referensi-harga-produksi.index') }}" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </a>
                        @endif
                    </div>
                    <div class="flex gap-2 w-full md:w-auto">
                        <button type="submit" class="w-full md:w-auto bg-indigo-50 text-indigo-600 hover:bg-indigo-100 font-semibold px-5 py-2.5 rounded-xl transition-all text-sm">
                            Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <th class="py-4 px-6">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'nama', 'order' => request('sort') == 'nama' && request('order') == 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1.5 hover:text-indigo-600 transition-colors">
                                    Nama
                                    @if(request('sort') == 'nama')
                                        <i class="fa-solid {{ request('order') == 'asc' ? 'fa-sort-up' : 'fa-sort-down' }} text-indigo-600"></i>
                                    @else
                                        <i class="fa-solid fa-sort text-slate-300"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="py-4 px-6">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'ukuran', 'order' => request('sort') == 'ukuran' && request('order') == 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1.5 hover:text-indigo-600 transition-colors">
                                    Ukuran
                                    @if(request('sort') == 'ukuran')
                                        <i class="fa-solid {{ request('order') == 'asc' ? 'fa-sort-up' : 'fa-sort-down' }} text-indigo-600"></i>
                                    @else
                                        <i class="fa-solid fa-sort text-slate-300"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="py-4 px-6">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'jenis_kayu', 'order' => request('sort') == 'jenis_kayu' && request('order') == 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1.5 hover:text-indigo-600 transition-colors">
                                    Jenis Kayu
                                    @if(request('sort') == 'jenis_kayu')
                                        <i class="fa-solid {{ request('order') == 'asc' ? 'fa-sort-up' : 'fa-sort-down' }} text-indigo-600"></i>
                                    @else
                                        <i class="fa-solid fa-sort text-slate-300"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="py-4 px-6">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'jenis_barang', 'order' => request('sort') == 'jenis_barang' && request('order') == 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1.5 hover:text-indigo-600 transition-colors">
                                    Jenis Barang
                                    @if(request('sort') == 'jenis_barang')
                                        <i class="fa-solid {{ request('order') == 'asc' ? 'fa-sort-up' : 'fa-sort-down' }} text-indigo-600"></i>
                                    @else
                                        <i class="fa-solid fa-sort text-slate-300"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="py-4 px-6">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'kw', 'order' => request('sort') == 'kw' && request('order') == 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1.5 hover:text-indigo-600 transition-colors">
                                    KW
                                    @if(request('sort') == 'kw')
                                        <i class="fa-solid {{ request('order') == 'asc' ? 'fa-sort-up' : 'fa-sort-down' }} text-indigo-600"></i>
                                    @else
                                        <i class="fa-solid fa-sort text-slate-300"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="py-4 px-6">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'sub_anak_akun', 'order' => request('sort') == 'sub_anak_akun' && request('order') == 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1.5 hover:text-indigo-600 transition-colors">
                                    Sub Anak Akun
                                    @if(request('sort') == 'sub_anak_akun')
                                        <i class="fa-solid {{ request('order') == 'asc' ? 'fa-sort-up' : 'fa-sort-down' }} text-indigo-600"></i>
                                    @else
                                        <i class="fa-solid fa-sort text-slate-300"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="py-4 px-6">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'harga', 'order' => request('sort') == 'harga' && request('order') == 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1.5 hover:text-indigo-600 transition-colors">
                                    Harga
                                    @if(request('sort') == 'harga')
                                        <i class="fa-solid {{ request('order') == 'asc' ? 'fa-sort-up' : 'fa-sort-down' }} text-indigo-600"></i>
                                    @else
                                        <i class="fa-solid fa-sort text-slate-300"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="py-4 px-6 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                        @forelse($data as $item)
                        <tr class="hover:bg-indigo-50/30 transition-colors group">
                            <td class="py-4 px-6 font-medium text-slate-900">
                                {{ $item->nama ?? '-' }}
                            </td>
                            <td class="py-4 px-6 font-medium text-slate-900">
                                @if($item->ukuran)
                                    {{ $item->ukuran->panjang }}mm x {{ $item->ukuran->lebar }}mm x {{ $item->ukuran->tebal }}mm
                                @else
                                    -
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                <span class="bg-slate-100 text-slate-700 text-xs px-2.5 py-1 rounded-lg font-medium">
                                    {{ optional($item->jenisKayu)->kode_kayu }} - {{ optional($item->jenisKayu)->nama_kayu }}
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                @php
                                    $badgeColor = match($item->jenis_barang) {
                                        'Veneer', 'Veneer Jadi' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                                        'Veneer Kering' => 'bg-cyan-50 text-cyan-700 border border-cyan-200',
                                        'Veneer Basah' => 'bg-sky-50 text-sky-700 border border-sky-200',
                                        'Platform' => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                                        'Afalan' => 'bg-rose-50 text-rose-700 border border-rose-200',
                                        'Plywood' => 'bg-amber-50 text-amber-700 border border-amber-200',
                                        'Barang' => 'bg-blue-50 text-blue-700 border border-blue-200',
                                        'Lain-Lain' => 'bg-slate-100 text-slate-700 border border-slate-200',
                                        default => 'bg-slate-100 text-slate-700 border border-slate-200',
                                    };
                                @endphp
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $badgeColor }}">
                                    {{ $item->jenis_barang }}
                                </span>
                            </td>
                            <td class="py-4 px-6 font-medium">
                                {{ $item->kw }}
                            </td>
                            <td class="py-4 px-6">
                                @if($item->subAnakAkun)
                                    <span class="text-slate-900 font-medium">{{ $item->subAnakAkun->kode_sub_anak_akun }}</span><br>
                                    <span class="text-slate-500 text-xs">{{ $item->subAnakAkun->nama_sub_anak_akun }}</span>
                                @else
                                    <span class="text-slate-400 font-normal italic">-</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 font-bold text-slate-900">
                                Rp {{ number_format($item->harga, 2, ',', '.') }}
                            </td>
                            <td class="py-4 px-6 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('referensi-harga-produksi.edit', $item->id) }}" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" title="Edit Data">
                                        <i class="fa-solid fa-pen-to-square text-base"></i>
                                    </a>
                                    <button onclick="confirmDelete({{ $item->id }}, '{{ optional($item->ukuran)->nama_ukuran }} - {{ optional($item->jenisKayu)->nama_kayu }}')" class="p-2 text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="Hapus Data">
                                        <i class="fa-solid fa-trash-can text-base"></i>
                                    </button>
                                    <form action="{{ route('referensi-harga-produksi.destroy', $item->id) }}" method="POST" id="delete-form-{{ $item->id }}" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="py-12 px-6 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <i class="fa-solid fa-inbox text-4xl mb-2 text-slate-300"></i>
                                    <p class="text-base font-semibold">Tidak Ada Data</p>
                                    <p class="text-sm">Tidak ditemukan data referensi harga produksi.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($data->hasPages())
            <div class="p-6 border-t border-slate-100 bg-slate-50/50">
                {{ $data->links() }}
            </div>
            @endif
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="delete-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-white px-6 pb-6 pt-8 sm:p-8 sm:pb-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-rose-50 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fa-solid fa-triangle-exclamation text-rose-600 text-lg"></i>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-lg font-bold leading-6 text-slate-900" id="modal-title">Konfirmasi Hapus</h3>
                            <div class="mt-2">
                                <p class="text-sm text-slate-500">Apakah Anda yakin ingin menghapus referensi harga produksi untuk <span id="delete-target-name" class="font-bold text-slate-800"></span>? Tindakan ini tidak dapat dibatalkan.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex flex-row-reverse gap-2 sm:px-8">
                    <button type="button" id="confirm-delete-btn" class="inline-flex w-full justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 sm:w-auto transition-colors">Hapus</button>
                    <button type="button" onclick="closeModal()" class="inline-flex w-full justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:w-auto transition-colors">Batal</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        let deleteId = null;

        function confirmDelete(id, name) {
            deleteId = id;
            document.getElementById('delete-target-name').innerText = name;
            document.getElementById('delete-modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('delete-modal').classList.add('hidden');
            deleteId = null;
        }

        document.getElementById('confirm-delete-btn').addEventListener('click', function() {
            if (deleteId) {
                document.getElementById('delete-form-' + deleteId).submit();
            }
        });
    </script>
</body>
</html>
