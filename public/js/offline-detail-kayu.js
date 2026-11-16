window.offlineDetailLogic = function (cfg) {
  const STORAGE_KEY = "kayu_offline_queue_v2";

  const newUuid = () => {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
      const r = (Math.random() * 16) | 0;
      return (c === "x" ? r : (r & 3) | 8).toString(16);
    });
  };

  const csrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.content || "";

  return {
    parentId: String(cfg.parentId),
    labels: cfg.labels,
    online: navigator.onLine,
    isSyncing: false,
    allItems: [],
    editingUuid: null,
    notice: "",
    noticeType: "info",
    form: {},

    init() {
      this.resetForm(true);
      this.load();

      window.addEventListener("online", () => (this.online = true));
      window.addEventListener("offline", () => (this.online = false));
      window.addEventListener("storage", (e) => {
        if (e.key === STORAGE_KEY) this.load();
      });

      if (navigator.storage && navigator.storage.persist) {
        navigator.storage.persist().catch(() => {});
      }

      this.migrateLegacy();
    },

    async migrateLegacy() {
      if (typeof localforage === "undefined") return;
      try {
        localforage.config({ name: "AppKayuOffline" });
        const key = "offline_kayu_masuk_" + this.parentId;
        const old = await localforage.getItem(key);
        if (!Array.isArray(old) || old.length === 0) return;

        await localforage.setItem(key + "_backup", old);

        let moved = 0;
        for (const it of old) {
          if (
            !it ||
            !(Number(it.diameter) > 0) ||
            !(Number(it.jumlah_batang) >= 1)
          )
            continue;
          this.allItems.push({
            uuid: newUuid(),
            parentId: this.parentId,
            id_lahan: String(it.id_lahan),
            id_jenis_kayu: String(it.id_jenis_kayu),
            panjang: String(it.panjang ?? "130"),
            grade: String(it.grade ?? "1"),
            diameter: Number(it.diameter),
            jumlah_batang: Number(it.jumlah_batang),
            created_at: new Date().toISOString(),
            error: null,
          });
          moved++;
        }

        if (this.persist()) {
          await localforage.removeItem(key);
          if (moved)
            this.say(`${moved} data lama dipindahkan ke antrean.`, "success");
        }
      } catch (e) {
        console.warn("Migrasi data lama gagal", e);
      }
    },

    get pendingItems() {
      return this.allItems.filter((i) => String(i.parentId) === this.parentId);
    },
    get otherCount() {
      return this.allItems.length - this.pendingItems.length;
    },
    get totalBatang() {
      return this.pendingItems.reduce(
        (s, i) => s + Number(i.jumlah_batang || 0),
        0,
      );
    },

    load() {
      try {
        this.allItems = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
      } catch (e) {
        this.allItems = [];
      }
    },
    persist() {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(this.allItems));
        return true;
      } catch (e) {
        this.say(
          "Penyimpanan perangkat penuh atau diblokir. Data TIDAK tersimpan!",
          "error",
        );
        return false;
      }
    },

    resetForm(full = false) {
      const keep = full ? {} : this.form;
      this.form = {
        id_lahan: keep.id_lahan ?? String(cfg.lahanDefault),
        id_jenis_kayu: keep.id_jenis_kayu ?? String(cfg.jenisDefault),
        panjang: keep.panjang ?? "130",
        grade: keep.grade ?? "1",
        jumlah_batang: 1,
        diameter: "",
      };
    },

    say(msg, type = "info") {
      this.notice = msg;
      this.noticeType = type;
    },

    closeModal() {
      try {
        this.$wire.unmountAction();
      } catch (e) {
        console.warn("Gagal menutup modal lewat unmountAction", e);
      }
    },

    create(closeAfter) {
      const diameter = parseFloat(this.form.diameter);
      const batang = parseInt(this.form.jumlah_batang, 10);

      if (!(diameter > 0))
        return this.say("Diameter wajib diisi (lebih dari 0).", "error");
      if (!(batang >= 1)) return this.say("Jumlah batang minimal 1.", "error");

      const payload = {
        uuid: this.editingUuid || newUuid(),
        parentId: this.parentId,
        id_lahan: String(this.form.id_lahan),
        id_jenis_kayu: String(this.form.id_jenis_kayu),
        panjang: String(this.form.panjang),
        grade: String(this.form.grade),
        diameter: diameter,
        jumlah_batang: batang,
        created_at: new Date().toISOString(),
        error: null,
      };

      if (this.editingUuid) {
        const idx = this.allItems.findIndex((i) => i.uuid === this.editingUuid);
        if (idx >= 0) {
          payload.created_at = this.allItems[idx].created_at;
          this.allItems.splice(idx, 1, payload);
        } else {
          this.allItems.push(payload);
        }
      } else {
        this.allItems.push(payload);
      }

      if (!this.persist()) return;

      this.editingUuid = null;
      this.say("Tersimpan di perangkat ini.", "success");

      if (closeAfter) {
        this.closeModal();
      } else {
        this.resetForm(false);
        this.$nextTick(() => this.$refs.diameterInput?.focus());
      }
    },

    edit(item) {
      this.editingUuid = item.uuid;
      this.form = {
        id_lahan: String(item.id_lahan),
        id_jenis_kayu: String(item.id_jenis_kayu),
        panjang: String(item.panjang),
        grade: String(item.grade),
        jumlah_batang: item.jumlah_batang,
        diameter: item.diameter,
      };
      this.$nextTick(() => this.$refs.diameterInput?.focus());
    },

    cancelEdit() {
      this.editingUuid = null;
      this.resetForm(false);
      this.form.diameter = "";
    },

    remove(item) {
      if (!confirm("Hapus data ini dari perangkat? Tidak bisa dikembalikan."))
        return;
      this.allItems = this.allItems.filter((i) => i.uuid !== item.uuid);
      this.persist();
    },

    clearAll() {
      if (
        !confirm(
          `Hapus ${this.pendingItems.length} data offline nota ini dari perangkat? Pastikan sudah di-download/diupload.`,
        )
      )
        return;
      this.allItems = this.allItems.filter(
        (i) => String(i.parentId) !== this.parentId,
      );
      this.persist();
    },

    async syncNow(onlyItem = null) {
      if (this.isSyncing) return;
      if (!navigator.onLine)
        return this.say("Tidak ada koneksi internet.", "error");

      const batch = onlyItem ? [onlyItem] : [...this.pendingItems];
      if (!batch.length) return;

      this.isSyncing = true;
      this.say("Mengupload...", "info");

      try {
        const res = await fetch(cfg.syncUrl, {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": csrf(),
            "X-Requested-With": "XMLHttpRequest",
          },
          body: JSON.stringify({ items: batch }),
        });

        if (res.status === 401 || res.status === 419) {
          this.say(
            "Sesi login habis. Login ulang lalu coba upload lagi. Data tetap aman di perangkat.",
            "error",
          );
          return;
        }
        if (!res.ok) {
          this.say(
            `Server menolak (kode ${res.status}). Data tetap aman di perangkat.`,
            "error",
          );
          return;
        }

        const { results } = await res.json();
        const okIds = new Set(results.filter((r) => r.ok).map((r) => r.uuid));
        const errMap = Object.fromEntries(
          results.filter((r) => !r.ok).map((r) => [r.uuid, r.error]),
        );

        this.allItems = this.allItems
          .filter((i) => !okIds.has(i.uuid))
          .map((i) => (errMap[i.uuid] ? { ...i, error: errMap[i.uuid] } : i));
        this.persist();

        const gagal = Object.keys(errMap).length;
        this.say(
          gagal
            ? `${okIds.size} berhasil, ${gagal} gagal (lihat status).`
            : `${okIds.size} data berhasil diupload.`,
          gagal ? "error" : "success",
        );

        if (okIds.size) {
          try {
            await this.$wire.$refresh();
          } catch (e) {}
        }
      } catch (e) {
        this.say(
          "Gagal terhubung ke server. Data tetap aman di perangkat.",
          "error",
        );
      } finally {
        this.isSyncing = false;
      }
    },

    exportFile() {
      const items = this.pendingItems;
      if (!items.length)
        return this.say("Tidak ada data untuk di-download.", "error");

      const blob = new Blob(
        [
          JSON.stringify(
            {
              app: "kayu-offline",
              version: 1,
              exported_at: new Date().toISOString(),
              items,
            },
            null,
            2,
          ),
        ],
        { type: "application/json" },
      );
      const a = document.createElement("a");
      const stamp = new Date().toISOString().slice(0, 16).replace(/[:T]/g, "-");
      a.href = URL.createObjectURL(blob);
      a.download = `kayu-offline-nota-${this.parentId}-${stamp}.json`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 1000);
      this.say(`${items.length} data di-download.`, "success");
    },

    async importFile(event) {
      const file = event.target.files?.[0];
      event.target.value = "";
      if (!file) return;

      try {
        const json = JSON.parse(await file.text());
        if (json.app !== "kayu-offline" || !Array.isArray(json.items)) {
          return this.say(
            "File bukan backup mode offline yang valid.",
            "error",
          );
        }

        const known = new Set(this.allItems.map((i) => i.uuid));
        let added = 0,
          dupe = 0,
          invalid = 0;

        for (const it of json.items) {
          const valid =
            it &&
            it.uuid &&
            it.parentId &&
            it.id_lahan &&
            it.id_jenis_kayu &&
            Number(it.diameter) > 0 &&
            Number(it.jumlah_batang) >= 1;
          if (!valid) {
            invalid++;
            continue;
          }
          if (known.has(it.uuid)) {
            dupe++;
            continue;
          }
          this.allItems.push({
            uuid: String(it.uuid),
            parentId: String(it.parentId),
            id_lahan: String(it.id_lahan),
            id_jenis_kayu: String(it.id_jenis_kayu),
            panjang: String(it.panjang ?? "130"),
            grade: String(it.grade ?? "1"),
            diameter: Number(it.diameter),
            jumlah_batang: Number(it.jumlah_batang),
            created_at: it.created_at || new Date().toISOString(),
            error: null,
          });
          known.add(it.uuid);
          added++;
        }

        if (!this.persist()) return;
        this.say(
          `Import selesai: ${added} baru, ${dupe} sudah ada, ${invalid} tidak valid.`,
          added ? "success" : "info",
        );
      } catch (e) {
        this.say("File tidak bisa dibaca.", "error");
      }
    },

    lahanLabel(id) {
      return this.labels.lahan[id] ?? `#${id}`;
    },
    jenisLabel(id) {
      return this.labels.jenis[id] ?? `#${id}`;
    },
    gradeLabel(g) {
      return String(g) === "1" ? "A" : String(g) === "2" ? "B" : "-";
    },
    kubikasi(i) {
      return (
        (Number(i.panjang) *
          i.diameter *
          i.diameter *
          i.jumlah_batang *
          0.785) /
        1_000_000
      ).toFixed(4);
    },
  };
};
