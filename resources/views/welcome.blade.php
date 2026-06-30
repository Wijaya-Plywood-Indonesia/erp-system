<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>PT Plywood Indonesia - Solusi Kayu Lapis Berkualitas</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
            rel="stylesheet"
        />
        <style>
            body {
                font-family: "Inter", sans-serif;
            }
        </style>
    </head>
    <body class="bg-gray-50 text-gray-800 antialiased">
        <!-- Navbar -->
        <nav
            class="fixed top-0 left-0 right-0 bg-white/95 backdrop-blur-sm shadow-sm z-50 border-b border-gray-200"
            x-data="{ mobileMenu: false }"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <a href="#" class="text-xl font-bold text-yellow-600"
                        >PT. Plywood Indonesia</a
                    >

                    <!-- Desktop Menu -->
                    <div class="hidden md:flex items-center space-x-8">
                        <a
                            href="#profil"
                            class="text-gray-700 hover:text-yellow-600 transition"
                            >Profil</a
                        >
                        <a
                            href="#produk"
                            class="text-gray-700 hover:text-yellow-600 transition"
                            >Produk</a
                        >
                        <a
                            href="#tim"
                            class="text-gray-700 hover:text-yellow-600 transition"
                            >Tim</a
                        >
                        <a
                            href="{{ route('filament.admin.auth.login') }}"
                            class="px-4 py-2 bg-yellow-500 text-gray-900 rounded-sm hover:bg-yellow-400 transition shadow-sm text-sm font-semibold"
                        >
                            Login
                        </a>
                    </div>

                    <!-- Mobile Menu Button -->
                    <button
                        @click="mobileMenu = !mobileMenu"
                        class="md:hidden p-2 rounded-md hover:bg-gray-100 transition"
                    >
                        <svg
                            x-show="!mobileMenu"
                            class="w-6 h-6 text-gray-700"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            ></path>
                        </svg>
                        <svg
                            x-show="mobileMenu"
                            class="w-6 h-6 text-gray-700"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            ></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Menu Dropdown -->
            <div
                x-show="mobileMenu"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click.away="mobileMenu = false"
                class="md:hidden bg-white border-t border-gray-200"
            >
                <div class="px-4 py-3 space-y-1">
                    <a
                        href="#profil"
                        class="block py-2.5 px-3 text-gray-700 hover:text-yellow-600 hover:bg-yellow-50 rounded-md transition"
                        >Profil</a
                    >
                    <a
                        href="#produk"
                        class="block py-2.5 px-3 text-gray-700 hover:text-yellow-600 hover:bg-yellow-50 rounded-md transition"
                        >Produk</a
                    >
                    <a
                        href="#tim"
                        class="block py-2.5 px-3 text-gray-700 hover:text-yellow-600 hover:bg-yellow-50 rounded-md transition"
                        >Tim</a
                    >
                    <a
                        href="{{ route('filament.admin.auth.login') }}"
                        class="block py-2.5 px-3 text-yellow-600 font-medium hover:bg-yellow-50 rounded-md transition"
                        >Login</a
                    >
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section
            class="relative h-screen flex items-center justify-center bg-gradient-to-br from-yellow-50 via-amber-50 to-yellow-50 overflow-hidden"
        >
            <div
                class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1581092921461-0d20d7a5d2b5?ixlib=rb-4.0.3&auto=format&fit=crop&q=80')] bg-cover bg-center opacity-20"
            ></div>
            <div class="relative z-10 text-center px-4 max-w-4xl mx-auto">
                <h1
                    class="text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 mb-4"
                >
                    Solusi Plywood
                    <span class="text-yellow-600">Berkualitas Tinggi</span>
                </h1>
                <p
                    class="text-lg md:text-xl text-gray-700 mb-8 max-w-2xl mx-auto"
                >
                    Produsen kayu lapis terkemuka di Indonesia, mendukung
                    konstruksi, furnitur, dan industri maritim dengan standar
                    ekspor.
                </p>
                <a
                    href="#produk"
                    class="inline-flex items-center px-6 py-3 bg-yellow-500 text-gray-900 font-semibold rounded-xl hover:bg-yellow-400 transition shadow-lg"
                >
                    Lihat Produk Kami
                    <svg
                        class="ml-2 w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5l7 7-7 7"
                        ></path>
                    </svg>
                </a>
            </div>
            <div
                class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce"
            >
                <svg
                    class="w-6 h-6 text-yellow-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 14l-7 7m0 0l-7-7m7 7V3"
                    ></path>
                </svg>
            </div>
        </section>

        <!-- Profil Perusahaan -->
        <section id="profil" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2
                        class="text-3xl md:text-4xl font-bold text-gray-900 mb-4"
                    >
                        Tentang Kami
                    </h2>
                    <p class="text-lg text-gray-600 max-w-3xl mx-auto">
                        PT Plywood Indonesia berdiri sejak 1998, berlokasi di
                        Jawa Tengah, dengan kapasitas produksi 50.000 m³/tahun.
                        Kami memproduksi plywood bersertifikat FSC dan SVLK
                        untuk pasar domestik dan ekspor ke Asia, Eropa, dan
                        Amerika.
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="group">
                        <div
                            class="overflow-hidden rounded-2xl shadow-md group-hover:shadow-xl transition"
                        >
                            <img
                                src="https://images.unsplash.com/photo-1581092580496-6d8b2b1b9e9f?ixlib=rb-4.0.3&auto=format&fit=crop&q=80"
                                alt="Pabrik Produksi"
                                class="w-full h-64 object-cover group-hover:scale-105 transition duration-300"
                            />
                        </div>
                        <h3 class="mt-4 text-xl font-semibold text-gray-900">
                            Pabrik Modern
                        </h3>
                        <p class="text-gray-600">
                            Mesin rotary peeler & hot press dari Jepang
                        </p>
                    </div>
                    <div class="group">
                        <div
                            class="overflow-hidden rounded-2xl shadow-md group-hover:shadow-xl transition"
                        >
                            <img
                                src="https://images.unsplash.com/photo-1581093450021-4a2e8c4b3b9a?ixlib=rb-4.0.3&auto=format&fit=crop&q=80"
                                alt="Gudang Bahan Baku"
                                class="w-full h-64 object-cover group-hover:scale-105 transition duration-300"
                            />
                        </div>
                        <h3 class="mt-4 text-xl font-semibold text-gray-900">
                            Bahan Baku Terjamin
                        </h3>
                        <p class="text-gray-600">
                            Kayu jabon & sengon dari hutan tanaman industri
                        </p>
                    </div>
                    <div class="group">
                        <div
                            class="overflow-hidden rounded-2xl shadow-md group-hover:shadow-xl transition"
                        >
                            <img
                                src="https://images.unsplash.com/photo-1581093588401-1c0b0a1f5a9c?ixlib=rb-4.0.3&auto=format&fit=crop&q=80"
                                alt="Sertifikasi"
                                class="w-full h-64 object-cover group-hover:scale-105 transition duration-300"
                            />
                        </div>
                        <h3 class="mt-4 text-xl font-semibold text-gray-900">
                            Sertifikasi Internasional
                        </h3>
                        <p class="text-gray-600">
                            FSC, SVLK, JIS, CARB P2 compliant
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Produk Unggulan -->
        <section id="produk" class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2
                        class="text-3xl md:text-4xl font-bold text-gray-900 mb-4"
                    >
                        Produk Unggulan
                    </h2>
                    <p class="text-lg text-gray-600">
                        Pilihan terbaik untuk konstruksi, interior, dan aplikasi
                        khusus
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Produk 1 -->
                    <div
                        class="bg-white rounded-2xl shadow-sm hover:shadow-lg transition overflow-hidden"
                    >
                        <div
                            class="h-48 bg-gradient-to-br from-yellow-100 to-amber-100 flex items-center justify-center"
                        >
                            <div
                                class="bg-white/80 backdrop-blur-sm rounded-xl p-6 shadow-inner"
                            >
                                <div
                                    class="w-24 h-24 mx-auto bg-yellow-200 rounded-lg border-2 border-dashed border-yellow-400"
                                ></div>
                            </div>
                        </div>
                        <div class="p-6">
                            <h3
                                class="text-xl font-semibold text-gray-900 mb-2"
                            >
                                Plywood Premium MR
                            </h3>
                            <p class="text-gray-600 mb-4">
                                Tahan lembab, cocok untuk furnitur & interior.
                                Tebal 3–18mm.
                            </p>
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-sm text-yellow-600 font-medium"
                                    >Mulai dari Rp185.000</span
                                >
                                <a
                                    href="#"
                                    class="text-yellow-600 hover:text-yellow-700 font-medium text-sm flex items-center"
                                >
                                    Detail
                                    <svg
                                        class="ml-1 w-4 h-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 5l7 7-7 7"
                                        ></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Produk 2 -->
                    <div
                        class="bg-white rounded-2xl shadow-sm hover:shadow-lg transition overflow-hidden"
                    >
                        <div
                            class="h-48 bg-gradient-to-br from-yellow-100 to-lime-100 flex items-center justify-center"
                        >
                            <div
                                class="bg-white/80 backdrop-blur-sm rounded-xl p-6 shadow-inner"
                            >
                                <div
                                    class="w-24 h-24 mx-auto bg-lime-200 rounded-lg border-2 border-dashed border-lime-400"
                                ></div>
                            </div>
                        </div>
                        <div class="p-6">
                            <h3
                                class="text-xl font-semibold text-gray-900 mb-2"
                            >
                                Marine Plywood WBP
                            </h3>
                            <p class="text-gray-600 mb-4">
                                100% tahan air, untuk kapal, dermaga, dan
                                outdoor. Phenolic glue.
                            </p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-lime-600 font-medium"
                                    >Mulai dari Rp425.000</span
                                >
                                <a
                                    href="#"
                                    class="text-lime-600 hover:text-lime-700 font-medium text-sm flex items-center"
                                >
                                    Detail
                                    <svg
                                        class="ml-1 w-4 h-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 5l7 7-7 7"
                                        ></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Produk 3 -->
                    <div
                        class="bg-white rounded-2xl shadow-sm hover:shadow-lg transition overflow-hidden"
                    >
                        <div
                            class="h-48 bg-gradient-to-br from-amber-100 to-yellow-100 flex items-center justify-center"
                        >
                            <div
                                class="bg-white/80 backdrop-blur-sm rounded-xl p-6 shadow-inner"
                            >
                                <div
                                    class="w-24 h-24 mx-auto bg-amber-200 rounded-lg border-2 border-dashed border-amber-400"
                                ></div>
                            </div>
                        </div>
                        <div class="p-6">
                            <h3
                                class="text-xl font-semibold text-gray-900 mb-2"
                            >
                                Fancy Plywood
                            </h3>
                            <p class="text-gray-600 mb-4">
                                Veneer dekoratif: teak, mahoni, oak. Untuk panel
                                dinding & mebel premium.
                            </p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-amber-600 font-medium"
                                    >Mulai dari Rp325.000</span
                                >
                                <a
                                    href="#"
                                    class="text-amber-600 hover:text-amber-700 font-medium text-sm flex items-center"
                                >
                                    Detail
                                    <svg
                                        class="ml-1 w-4 h-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 5l7 7-7 7"
                                        ></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tim Profesional -->
        <section id="tim" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2
                        class="text-3xl md:text-4xl font-bold text-gray-900 mb-4"
                    >
                        Tim Profesional Kami
                    </h2>
                    <p class="text-lg text-gray-600">
                        Didukung oleh ahli di bidang kehutanan, produksi, dan
                        ekspor
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="text-center">
                        <div
                            class="w-32 h-32 mx-auto mb-4 rounded-full overflow-hidden border-4 border-yellow-100"
                        >
                            <img
                                src="https://randomuser.me/api/portraits/men/32.jpg"
                                alt="Andi Wijaya"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            Andi Wijaya
                        </h3>
                        <p class="text-yellow-600 font-medium">CEO & Founder</p>
                        <p class="text-sm text-gray-600 mt-1">
                            25+ tahun di industri kayu lapis
                        </p>
                    </div>
                    <div class="text-center">
                        <div
                            class="w-32 h-32 mx-auto mb-4 rounded-full overflow-hidden border-4 border-yellow-100"
                        >
                            <img
                                src="https://randomuser.me/api/portraits/women/44.jpg"
                                alt="Sinta Prameswari"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            Sinta Prameswari
                        </h3>
                        <p class="text-yellow-600 font-medium">
                            Marketing Director
                        </p>
                        <p class="text-sm text-gray-600 mt-1">
                            Spesialis ekspor Asia & Eropa
                        </p>
                    </div>
                    <div class="text-center">
                        <div
                            class="w-32 h-32 mx-auto mb-4 rounded-full overflow-hidden border-4 border-yellow-100"
                        >
                            <img
                                src="https://randomuser.me/api/portraits/men/86.jpg"
                                alt="Budi Santoso"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            Budi Santoso
                        </h3>
                        <p class="text-yellow-600 font-medium">
                            Head of Production
                        </p>
                        <p class="text-sm text-gray-600 mt-1">
                            Ahli teknologi pengolahan veneer
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer
            class="bg-gradient-to-r from-yellow-500 to-amber-500 text-white py-10"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <p class="text-sm">
                    &copy; {{ date("Y") }} PT Plywood Indonesia. Hak cipta
                    dilindungi.
                </p>
                <p class="text-xs mt-2 opacity-80">
                    Jl. Raya Semarang-Kendal KM 12, Wringinanom, Grobogan, Jawa
                    Tengah
                </p>
            </div>
        </footer>
    </body>
</html>
