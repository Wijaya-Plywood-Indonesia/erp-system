<x-filament-panels::page>
  <div x-data="groupTreeEditor()" class="p-4">
    <template x-if="tree.length === 0">
      <p class="text-gray-500">Belum ada grup.</p>
    </template>

    <ul class="space-y-2" id="tree-root">
      <template x-for="node in tree" :key="node.id">
        <li>
          <div
            class="flex items-center justify-between bg-white dark:bg-gray-800 border px-3 py-2 rounded-lg shadow-sm"
          >
            <div class="flex items-center gap-2">
              <button @click="node._open = !node._open">🔽</button>
              <input
                type="text"
                class="border rounded px-2 py-1"
                x-model="node.nama"
                @change="renameGroup(node.id, node.nama)"
              />
            </div>

            <div class="flex gap-2">
              <button
                @click="addGroup(node.id)"
                class="px-2 py-1 bg-green-600 text-white rounded"
              >
                Tambah
              </button>
              <button
                @click="deleteGroup(node.id)"
                class="px-2 py-1 bg-red-600 text-white rounded"
              >
                Hapus
              </button>
            </div>
          </div>

          <ul
            x-show="node._open"
            class="ml-8 mt-2 space-y-2 nested"
            :id="'node-' + node.id"
          >
            <!-- AKUN DALAM GRUP -->
            <template x-for="(ak, i) in node.akun" :key="i">
              <li
                class="ml-4 px-3 py-1 bg-blue-50 dark:bg-blue-900 border rounded draggable-akun"
                x-text="'Akun: ' + ak"
              ></li>
            </template>

            <!-- SUBGRUP -->
            <!-- <template x-for="child in node.children" :key="child.id">
              <li x-html="renderNode(child)"></li>
            </template> -->
            <template x-for="child in node.children" :key="child.id">
              <li>
                <div
                  class="flex items-center justify-between bg-white dark:bg-gray-800 border px-3 py-2 rounded-lg shadow-sm"
                >
                  <div class="flex items-center gap-2">
                    <button @click="child._open = !child._open">🔽</button>

                    <input
                      type="text"
                      class="border rounded px-2 py-1"
                      x-model="child.nama"
                      @change="renameGroup(child.id, child.nama)"
                    />
                  </div>

                  <div class="flex gap-2">
                    <button
                      @click="addGroup(child.id)"
                      class="px-2 py-1 bg-green-600 text-white rounded"
                    >
                      Tambah
                    </button>
                    <button
                      @click="deleteGroup(child.id)"
                      class="px-2 py-1 bg-red-600 text-white rounded"
                    >
                      Hapus
                    </button>
                  </div>
                </div>

                <ul
                  x-show="child._open"
                  class="ml-8 mt-2 space-y-2 nested"
                  :id="'node-' + child.id"
                >
                  <!-- AKUN -->
                  <template x-for="(ak, akIndex) in child.akun" :key="akIndex">
                    <li
                      class="ml-4 px-3 py-1 bg-blue-50 dark:bg-blue-900 border rounded draggable-akun"
                      x-text="'Akun: ' + ak"
                    ></li>
                  </template>

                  <!-- RECURSIVE SUB CHILDREN -->
                  <template x-for="sub in child.children" :key="sub.id">
                    <li>
                      <!-- RECURSIVE BLOCK DIPANGGIL LAGI -->
                      <template x-if="true">
                        <div x-data="{ node: sub }">
                          <!-- BLOCK INI HARUS IDENTIK DENGAN ATAS -->
                          <div
                            class="flex items-center justify-between bg-white dark:bg-gray-800 border px-3 py-2 rounded-lg shadow-sm"
                          >
                            <div class="flex items-center gap-2">
                              <button @click="sub._open = !sub._open">
                                🔽
                              </button>

                              <input
                                type="text"
                                class="border rounded px-2 py-1"
                                x-model="sub.nama"
                                @change="renameGroup(sub.id, sub.nama)"
                              />
                            </div>

                            <div class="flex gap-2">
                              <button
                                @click="addGroup(sub.id)"
                                class="px-2 py-1 bg-green-600 text-white rounded"
                              >
                                Tambah
                              </button>
                              <button
                                @click="deleteGroup(sub.id)"
                                class="px-2 py-1 bg-red-600 text-white rounded"
                              >
                                Hapus
                              </button>
                            </div>
                          </div>

                          <ul
                            x-show="sub._open"
                            class="ml-8 mt-2 space-y-2 nested"
                            :id="'node-' + sub.id"
                          >
                            <template
                              x-for="(ak2, akIndex2) in sub.akun"
                              :key="akIndex2"
                            >
                              <li
                                class="ml-4 px-3 py-1 bg-blue-50 dark:bg-blue-900 border rounded draggable-akun"
                                x-text="'Akun: ' + ak2"
                              ></li>
                            </template>

                            <!-- SUB SUB CHILDREN (recursive) -->
                            <template
                              x-for="subSub in sub.children"
                              :key="subSub.id"
                            >
                              <li>
                                <!-- sama seperti di atas -->
                                <div
                                  x-data="{ node: subSub }"
                                  x-html="/* recursive */ ''"
                                ></div>
                              </li>
                            </template>
                          </ul>
                        </div>
                      </template>
                    </li>
                  </template>
                </ul>
              </li>
            </template>
          </ul>
        </li>
      </template>
    </ul>

    <button
      @click="save()"
      class="mt-6 px-4 py-2 bg-blue-600 text-white rounded"
    >
      Simpan Perubahan
    </button>
  </div>

  <script>
    function groupTreeEditor() {
        return {
            tree: @js($this->tree),

            init() {
                this.$nextTick(() => {
                    this.makeSortable();
                });
            },

            makeSortable() {
                document.querySelectorAll('.nested').forEach(el => {
                    new Sortable(el, {
                        group: 'group',
                        fallbackOnBody: true,
                        animation: 150,
                        onEnd: () => this.syncData(),
                    });
                });
            },

            syncData() {
                // Optional: implement DOM → JSON convert
            },

            addGroup(parentId) {
                $wire.addGroup(parentId);
            },

            renameGroup(id, name) {
                $wire.renameGroup(id, name);
            },

            deleteGroup(id) {
                if (confirm('Hapus grup ini?')) {
                    $wire.deleteGroup(id);
                }
            },

            save() {
                $wire.saveTree(this.tree);
            },
        };
    }
  </script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
</x-filament-panels::page>
