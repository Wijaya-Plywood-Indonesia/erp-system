<?php

namespace App\Providers;

use App\Models\ModalSanding;
use App\Models\NotaKayu;
use App\Models\PenggunaanLahanRotary;
use App\Observers\ModalSandingObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use App\Models\RencanaKerjaHp;
use App\Observers\NotaKayuObserver;
use App\Observers\RencanaKerjaHpObserver;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use App\Models\ValidasiHasilRotary;
use App\Observers\RotaryObserver;
use App\Observers\ValidasiHasilRotaryObserver;
use App\Models\ValidasiPressDryer;
use App\Models\ValidasiStik;
use App\Models\ValidasiKedi;
use App\Observers\ProductionValidationObserver;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        FilamentView::registerRenderHook(
    PanelsRenderHook::BODY_END,
    fn (): HtmlString => new HtmlString(<<<'HTML'
        <!-- Lightbox Overlay untuk preview foto -->
        <div id="foto-lightbox"
             style="display:none; position:fixed; inset:0; z-index:9999;
                    background:rgba(0,0,0,0.85); cursor:zoom-out;
                    align-items:center; justify-content:center;">
            <img id="foto-lightbox-img"
                 src=""
                 style="max-width:90vw; max-height:90vh;
                        border-radius:8px; box-shadow:0 8px 40px rgba(0,0,0,0.6);
                        object-fit:contain;" />
        </div>

        <script>
            // ✅ Pakai event delegation agar berfungsi setelah Livewire re-render
            document.addEventListener('click', function(e) {
                const img = e.target.closest('img.foto-preview-trigger');
                if (!img) return;

                const lightbox    = document.getElementById('foto-lightbox');
                const lightboxImg = document.getElementById('foto-lightbox-img');

                lightboxImg.src    = img.src;
                lightbox.style.display = 'flex';
            });

            // Tutup lightbox saat overlay di-klik
            document.getElementById('foto-lightbox').addEventListener('click', function() {
                this.style.display = 'none';
                document.getElementById('foto-lightbox-img').src = '';
            });

            // Tutup dengan tombol ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const lightbox = document.getElementById('foto-lightbox');
                    lightbox.style.display = 'none';
                }
            });
        </script>
    HTML),
);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ⬆⬆⬆  WAJIB: supaya MySQL ikut Asia/Jakarta
        ModalSanding::observe(ModalSandingObserver::class);
        RencanaKerjaHp::observe(RencanaKerjaHpObserver::class);
        ValidasiHasilRotary::observe(ValidasiHasilRotaryObserver::class);
        NotaKayu::observe(NotaKayuObserver::class);
        PenggunaanLahanRotary::observe(RotaryObserver::class);
        ValidasiPressDryer::observe(ProductionValidationObserver::class);
        ValidasiStik::observe(ProductionValidationObserver::class);
        ValidasiKedi::observe(ProductionValidationObserver::class);
        // PlatformHasilHp::observe(PlatformHasilHpObserver::class);
        // TriplekHasilHp::observe(TriplekHasilHpObserver::class);

        FilamentView::registerRenderHook(
            'panels::body.end',
            fn(): string => Blade::render(<<<'HTML'

                <!-- Load Library LocalForage -->
                <script src="https://cdnjs.cloudflare.com/ajax/libs/localforage/1.10.0/localforage.min.js"></script>

                <script>
                    /**
                     * ==========================================
                     * 1. LOGIC UNTUK DETAIL KAYU MASUK (LAMA)
                     * ==========================================
                     */
                    window.offlineDetailLogic = function(config) {
                        return {
                            online: navigator.onLine,
                            isSyncing: false,
                            pendingItems: [],
                            
                            form: {
                                id_lahan: localStorage.getItem('sticky_lahan_' + config.parentId) || config.lahanDefault || '',
                                id_jenis_kayu: localStorage.getItem('sticky_jenis_' + config.parentId) || config.jenisDefault || '',
                                panjang: localStorage.getItem('sticky_panjang_' + config.parentId) || '130',
                                grade: localStorage.getItem('sticky_grade_' + config.parentId) || '1',
                                jumlah_batang: localStorage.getItem('sticky_jumlah_' + config.parentId) || '1',
                                diameter: ''
                            },
                            
                            storageKey: 'offline_kayu_masuk_' + config.parentId,

                            init() {
                                localforage.config({ name: 'AppKayuOffline' });
                                this.loadStorage();
                                window.addEventListener('online', () => this.online = true);
                                window.addEventListener('offline', () => this.online = false);
                                this.$nextTick(() => { if(this.$refs.diameterInput) this.$refs.diameterInput.focus(); });
                            },

                            async loadStorage() {
                                this.pendingItems = await localforage.getItem(this.storageKey) || [];
                            },

                            async removeItem(index) {
                                if(!confirm('Hapus data ini dari draft?')) return;
                                this.pendingItems.splice(index, 1);
                                await localforage.setItem(this.storageKey, JSON.parse(JSON.stringify(this.pendingItems)));
                                new FilamentNotification().title('Data dihapus').success().send();
                            },

                            async create(closeAfter = true) {
                                if (!this.form.id_lahan || !this.form.id_jenis_kayu || !this.form.diameter || !this.form.jumlah_batang) {
                                    new FilamentNotification().title('Data belum lengkap!').danger().send(); return;
                                }

                                localStorage.setItem('sticky_lahan_' + config.parentId, this.form.id_lahan);
                                localStorage.setItem('sticky_jenis_' + config.parentId, this.form.id_jenis_kayu);
                                localStorage.setItem('sticky_panjang_' + config.parentId, this.form.panjang);
                                localStorage.setItem('sticky_grade_' + config.parentId, this.form.grade);
                                localStorage.setItem('sticky_jumlah_' + config.parentId, this.form.jumlah_batang);

                                const newItem = JSON.parse(JSON.stringify(this.form));
                                this.pendingItems.unshift(newItem);
                                await localforage.setItem(this.storageKey, JSON.parse(JSON.stringify(this.pendingItems)));
                                
                                new FilamentNotification().title('Tersimpan (Offline)').success().send();

                                if (closeAfter) {
                                    this.$dispatch('close-modal', { id: 'modal-offline-input' });
                                } else {
                                    this.form.diameter = '';
                                    this.$nextTick(() => { if (this.$refs.diameterInput) this.$refs.diameterInput.focus(); });
                                }
                            },

                            async syncNow() {
                                this.isSyncing = true;
                                try {
                                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                                    const payloadItems = JSON.parse(JSON.stringify(this.pendingItems));

                                    // URL: KAYU MASUK
                                    const res = await fetch('/api/offline/sync-detail-kayu-masuk', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                                        body: JSON.stringify({ parent_id: config.parentId, items: payloadItems })
                                    });

                                    const data = await res.json();
                                    if (res.ok) {
                                        this.pendingItems = [];
                                        await localforage.removeItem(this.storageKey);
                                        Livewire.dispatch('refreshDatatable'); 
                                        new FilamentNotification().title('Berhasil Sinkronisasi Kayu Masuk').success().send();
                                    } else { throw new Error(data.message || 'Gagal Sync'); }
                                } catch (e) {
                                    new FilamentNotification().title('Gagal Upload: ' + e.message).danger().send();
                                } finally { this.isSyncing = false; }
                            }
                        };
                    };

                    /**
                     * ==========================================
                     * 2. LOGIC UNTUK TURUSAN KAYU (BARU & DIPERBAIKI)
                     * ==========================================
                     */
                    window.offlineTurusanLogic = function(config) {
                        return {
                            online: navigator.onLine,
                            isSyncing: false,
                            pendingItems: [],
                            
                            // Nama field disesuaikan dengan view offline-turusan-modal.blade.php
                            form: {
                                lahan_id: localStorage.getItem('sticky_turus_lahan_' + config.parentId) || config.lahanDefault || '',
                                jenis_kayu_id: localStorage.getItem('sticky_turus_jenis_' + config.parentId) || config.jenisDefault || '',
                                panjang: localStorage.getItem('sticky_turus_panjang_' + config.parentId) || '130',
                                grade: localStorage.getItem('sticky_turus_grade_' + config.parentId) || '1',
                                kuantitas: localStorage.getItem('sticky_turus_kuantitas_' + config.parentId) || '1',
                                diameter: ''
                            },
                            
                            storageKey: 'offline_turusan_' + config.parentId,

                            init() {
                                localforage.config({ name: 'AppKayuOffline' });
                                this.loadStorage();
                                window.addEventListener('online', () => this.online = true);
                                window.addEventListener('offline', () => this.online = false);
                                this.$nextTick(() => { if(this.$refs.diameterInput) this.$refs.diameterInput.focus(); });
                            },

                            async loadStorage() {
                                this.pendingItems = await localforage.getItem(this.storageKey) || [];
                            },

                            async removeItem(index) {
                                if(!confirm('Hapus data ini dari draft?')) return;
                                this.pendingItems.splice(index, 1);
                                await localforage.setItem(this.storageKey, JSON.parse(JSON.stringify(this.pendingItems)));
                                new FilamentNotification().title('Data dihapus').success().send();
                            },

                            async create(closeAfter = true) {
                                if (!this.form.lahan_id || !this.form.jenis_kayu_id || !this.form.diameter) {
                                    new FilamentNotification().title('Data belum lengkap!').danger().send(); return;
                                }

                                // Sticky khusus Turusan (Key berbeda agar tidak bentrok)
                                localStorage.setItem('sticky_turus_lahan_' + config.parentId, this.form.lahan_id);
                                localStorage.setItem('sticky_turus_jenis_' + config.parentId, this.form.jenis_kayu_id);
                                localStorage.setItem('sticky_turus_panjang_' + config.parentId, this.form.panjang);
                                localStorage.setItem('sticky_turus_grade_' + config.parentId, this.form.grade);

                                const newItem = JSON.parse(JSON.stringify(this.form));
                                this.pendingItems.unshift(newItem);
                                await localforage.setItem(this.storageKey, JSON.parse(JSON.stringify(this.pendingItems)));
                                
                                new FilamentNotification().title('Turusan Tersimpan (Offline)').success().send();

                                if (closeAfter) {
                                    this.$dispatch('close-modal', { id: 'modal-offline-turusan' });
                                } else {
                                    this.form.diameter = '';
                                    this.$nextTick(() => { if (this.$refs.diameterInput) this.$refs.diameterInput.focus(); });
                                }
                            },

                            async syncNow() {
                                this.isSyncing = true;
                                try {
                                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                                    const payloadItems = JSON.parse(JSON.stringify(this.pendingItems));

                                    // URL: TURUSAN (Sudah Diperbaiki)
                                    const res = await fetch('/api/offline/sync-detail-turusan-kayu', {
                                        method: 'POST',
                                        headers: { 
                                            'Content-Type': 'application/json', 
                                            'Accept': 'application/json', 
                                            'X-CSRF-TOKEN': csrfToken 
                                        },
                                        body: JSON.stringify({ parent_id: config.parentId, items: payloadItems })
                                    });

                                    const data = await res.json();
                                    if (res.ok) {
                                        this.pendingItems = [];
                                        await localforage.removeItem(this.storageKey);
                                        Livewire.dispatch('refreshDatatable'); 
                                        new FilamentNotification().title('Berhasil Sinkronisasi Turusan').success().send();
                                    } else { 
                                        throw new Error(data.message || 'Gagal Sync'); 
                                    }
                                } catch (e) {
                                    console.error(e);
                                    new FilamentNotification().title('Gagal: ' + e.message).danger().send();
                                } finally { 
                                    this.isSyncing = false; 
                                }
                            }
                        };
                    };
                </script>
            HTML)
        );
    }
}
