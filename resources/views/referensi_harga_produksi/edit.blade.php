<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Referensi Harga Produksi</title>
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
    <!-- SlimSelect CSS & JS for searchable dropdown -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/slim-select/2.8.2/slimselect.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/slim-select/2.8.2/slimselect.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen font-sans antialiased">

    <!-- Header -->
    <div class="header-gradient text-white pb-32 pt-8 px-6 shadow-lg">
        <div class="max-w-3xl mx-auto flex items-center justify-between">
            <div>
                <a href="{{ route('referensi-harga-produksi.index') }}" class="text-indigo-200 hover:text-white transition-colors flex items-center gap-1.5 text-sm font-semibold uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar
                </a>
                <h1 class="text-3xl font-bold tracking-tight">Edit Referensi Harga</h1>
                <p class="text-indigo-100 text-sm mt-1 font-medium font-medium">Ubah detail data referensi harga produksi.</p>
            </div>
        </div>
    </div>

    <!-- Main Content Form -->
    <div class="max-w-3xl mx-auto px-6 -mt-24 pb-16">
        <div class="glass-panel rounded-2xl shadow-xl overflow-hidden bg-white">
            <div class="p-8 border-b border-slate-100 bg-white/50">
                <h2 class="text-lg font-bold text-slate-900">Formulir Edit Referensi Harga</h2>
                <p class="text-slate-500 text-xs mt-0.5">Ubah data di bawah ini dan tekan simpan untuk memperbarui referensi harga.</p>
            </div>

            <form action="{{ route('referensi-harga-produksi.update', $referensiHargaProduksi->id) }}" method="POST" class="p-8 space-y-6">
                @csrf
                @method('PUT')

                <!-- Nama -->
                <div class="flex flex-col gap-1.5">
                    <label for="nama" class="text-xs font-bold text-slate-700 uppercase tracking-wider">Nama (Opsional)</label>
                    <input type="text" name="nama" id="nama" value="{{ old('nama', $referensiHargaProduksi->nama) }}" placeholder="Masukkan nama referensi" 
                        class="w-full bg-slate-50 border @error('nama') border-rose-500 @else border-slate-200 @enderror rounded-xl py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                    @error('nama')
                        <p class="text-rose-500 text-xs font-medium mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Ukuran -->
                    <div class="flex flex-col gap-1.5">
                        <label for="id_ukuran" class="text-xs font-bold text-slate-700 uppercase tracking-wider">Ukuran</label>
                        <select name="id_ukuran" id="id_ukuran" class="w-full bg-slate-50 border @error('id_ukuran') border-rose-500 @else border-slate-200 @enderror rounded-xl py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                            <option value="">-- Pilih Ukuran --</option>
                            @foreach($ukurans as $ukuran)
                                <option value="{{ $ukuran->id }}" {{ old('id_ukuran', $referensiHargaProduksi->id_ukuran) == $ukuran->id ? 'selected' : '' }}>
                                    {{ $ukuran->panjang }}mm x {{ $ukuran->lebar }}mm x {{ $ukuran->tebal }}mm
                                </option>
                            @endforeach
                        </select>
                        @error('id_ukuran')
                            <p class="text-rose-500 text-xs font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Jenis Kayu -->
                    <div class="flex flex-col gap-1.5">
                        <label for="id_jenis_kayu" class="text-xs font-bold text-slate-700 uppercase tracking-wider">Jenis Kayu</label>
                        <select name="id_jenis_kayu" id="id_jenis_kayu" class="w-full bg-slate-50 border @error('id_jenis_kayu') border-rose-500 @else border-slate-200 @enderror rounded-xl py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                            <option value="">-- Pilih Jenis Kayu --</option>
                            @foreach($jenisKayus as $jenisKayu)
                                <option value="{{ $jenisKayu->id }}" {{ old('id_jenis_kayu', $referensiHargaProduksi->id_jenis_kayu) == $jenisKayu->id ? 'selected' : '' }}>
                                    {{ $jenisKayu->kode_kayu }} - {{ $jenisKayu->nama_kayu }}
                                </option>
                            @endforeach
                        </select>
                        @error('id_jenis_kayu')
                            <p class="text-rose-500 text-xs font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Jenis Barang -->
                    <div class="flex flex-col gap-1.5">
                        <label for="jenis_barang" class="text-xs font-bold text-slate-700 uppercase tracking-wider">Jenis Barang</label>
                        <select name="jenis_barang" id="jenis_barang" class="w-full bg-slate-50 border @error('jenis_barang') border-rose-500 @else border-slate-200 @enderror rounded-xl py-2 px-3 text-sm focus:outline-none transition-all">
                            <option value="">-- Pilih atau Ketik Baru --</option>
                            @foreach($jenisBarangs as $item)
                                <option value="{{ $item }}" {{ old('jenis_barang', $referensiHargaProduksi->jenis_barang) == $item ? 'selected' : '' }}>
                                    {{ $item }}
                                </option>
                            @endforeach
                        </select>
                        @error('jenis_barang')
                            <p class="text-rose-500 text-xs font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- KW -->
                    <div class="flex flex-col gap-1.5">
                        <label for="kw" class="text-xs font-bold text-slate-700 uppercase tracking-wider">KW</label>
                        <select name="kw" id="kw" class="w-full bg-slate-50 border @error('kw') border-rose-500 @else border-slate-200 @enderror rounded-xl py-2 px-3 text-sm focus:outline-none transition-all">
                            <option value="">-- Pilih atau Ketik Baru --</option>
                            @foreach($kws as $kwItem)
                                <option value="{{ $kwItem }}" {{ old('kw', $referensiHargaProduksi->kw) == $kwItem ? 'selected' : '' }}>
                                    {{ $kwItem }}
                                </option>
                            @endforeach
                            @if(old('kw', $referensiHargaProduksi->kw) && !$kws->contains(old('kw', $referensiHargaProduksi->kw)))
                                <option value="{{ old('kw', $referensiHargaProduksi->kw) }}" selected>{{ old('kw', $referensiHargaProduksi->kw) }}</option>
                            @endif
                        </select>
                        @error('kw')
                            <p class="text-rose-500 text-xs font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Sub Anak Akun -->
                    <div class="flex flex-col gap-1.5">
                        <label for="id_sub_anak_akun" class="text-xs font-bold text-slate-700 uppercase tracking-wider">Sub Anak Akun (Opsional)</label>
                        <select name="id_sub_anak_akun" id="id_sub_anak_akun" class="w-full bg-slate-50 border @error('id_sub_anak_akun') border-rose-500 @else border-slate-200 @enderror rounded-xl py-2 px-3 text-sm focus:outline-none transition-all">
                            <option value="">-- Pilih Sub Anak Akun --</option>
                            @foreach($subAnakAkuns as $subAkun)
                                <option value="{{ $subAkun->id }}" {{ old('id_sub_anak_akun', $referensiHargaProduksi->id_sub_anak_akun) == $subAkun->id ? 'selected' : '' }}>
                                    {{ $subAkun->kode_sub_anak_akun }} - {{ $subAkun->nama_sub_anak_akun }}
                                </option>
                            @endforeach
                        </select>
                        @error('id_sub_anak_akun')
                            <p class="text-rose-500 text-xs font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Harga -->
                    <div class="flex flex-col gap-1.5">
                        <label for="harga" class="text-xs font-bold text-slate-700 uppercase tracking-wider">Harga Produksi (Rp)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 text-sm font-semibold">
                                Rp
                            </span>
                            <input type="number" name="harga" id="harga" step="0.0001" min="0" value="{{ old('harga', $referensiHargaProduksi->harga) }}" placeholder="0.0000" 
                                class="w-full bg-slate-50 border @error('harga') border-rose-500 @else border-slate-200 @enderror rounded-xl py-2.5 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                        </div>
                        @error('harga')
                            <p class="text-rose-500 text-xs font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 border-t border-slate-100 flex justify-end gap-3">
                    <a href="{{ route('referensi-harga-produksi.index') }}" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-sm transition-all">
                        Batal
                    </a>
                    <button type="submit" class="px-5 py-3 btn-gradient hover:opacity-90 text-white font-semibold rounded-xl text-sm transition-all shadow-md transform hover:-translate-y-0.5">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Initialize SlimSelect -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new SlimSelect({
                select: '#id_sub_anak_akun',
                settings: {
                    placeholderText: 'Pilih Sub Anak Akun',
                    searchText: 'Tidak ada data ditemukan',
                    searchPlaceholder: 'Cari sub anak akun...',
                }
            });

            new SlimSelect({
                select: '#kw',
                settings: {
                    placeholderText: 'Pilih atau Ketik Baru',
                    searchText: 'Tidak ada data ditemukan',
                    searchPlaceholder: 'Cari atau ketik baru...',
                },
                events: {
                    addable: function (value) {
                        return {
                            text: value,
                            value: value
                        }
                    }
                }
            });

            new SlimSelect({
                select: '#jenis_barang',
                settings: {
                    placeholderText: 'Pilih atau Ketik Baru',
                    searchText: 'Tidak ada data ditemukan',
                    searchPlaceholder: 'Cari atau ketik baru...',
                },
                events: {
                    addable: function (value) {
                        return {
                            text: value,
                            value: value
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
