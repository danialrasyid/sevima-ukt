<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { rupiah, tanggal } from '@/lib/format';
import CreateTagihanDialog from './components/CreateTagihanDialog.vue';
import TagihanDetailDialog from './components/TagihanDetailDialog.vue';

const props = defineProps({
    tagihan: { type: Object, required: true },
    mahasiswa: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
});

const status = ref(props.filters.status ?? '');
const q = ref(props.filters.q ?? '');

const dialogBuat = ref(false);
const detailId = ref(null);

function terapkanFilter() {
    router.get(
        '/admin/tagihan',
        { status: status.value || undefined, q: q.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let timer;
watch(status, terapkanFilter);
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(terapkanFilter, 300);
});
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Head title="Tagihan" />

    <AppLayout>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Tagihan UKT</h1>
                <p class="text-sm text-slate-500">{{ tagihan.total }} tagihan ditemukan</p>
            </div>

            <button
                type="button"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                @click="dialogBuat = true"
            >
                + Buat tagihan
            </button>
        </div>

        <div class="mb-4 flex flex-wrap gap-3">
            <select
                v-model="status"
                aria-label="Filter status"
                class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
            >
                <option v-for="opsi in statusOptions" :key="opsi.value" :value="opsi.value">
                    {{ opsi.label }}
                </option>
            </select>

            <input
                v-model="q"
                type="search"
                placeholder="Cari nomor tagihan / nama / NIM"
                aria-label="Cari tagihan"
                class="w-72 rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nomor</th>
                        <th class="px-4 py-3">Mahasiswa</th>
                        <th class="px-4 py-3">Semester</th>
                        <th class="px-4 py-3">Nominal</th>
                        <th class="px-4 py-3">Kedaluwarsa</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!tagihan.data.length">
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                            Tidak ada tagihan yang cocok.
                        </td>
                    </tr>

                    <tr v-for="t in tagihan.data" :key="t.id">
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900">{{ t.nomor_tagihan }}</td>
                        <td class="px-4 py-3">
                            {{ t.mahasiswa?.nama }}
                            <p class="text-xs text-slate-500">{{ t.mahasiswa?.nim }}</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">{{ t.semester }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ rupiah(t.nominal) }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ tanggal(t.tanggal_kedaluwarsa) }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <StatusBadge :status="t.status_efektif" />
                            <span
                                v-if="t.perlu_review"
                                class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800"
                                title="Ada transaksi yang perlu ditinjau"
                            >
                                Perlu review
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <button
                                type="button"
                                class="text-indigo-700 hover:text-indigo-900"
                                @click="detailId = t.id"
                            >
                                Detail
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="tagihan.links && tagihan.links.length > 3"
            class="mt-4 flex flex-wrap gap-1"
            aria-label="Paginasi"
        >
            <template v-for="(tautan, i) in tagihan.links" :key="i">
                <Link
                    v-if="tautan.url"
                    :href="tautan.url"
                    preserve-scroll
                    class="rounded border px-3 py-1 text-sm"
                    :class="tautan.active ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'"
                    v-html="tautan.label"
                />
                <span
                    v-else
                    class="rounded border border-slate-200 px-3 py-1 text-sm text-slate-400"
                    v-html="tautan.label"
                />
            </template>
        </nav>

        <CreateTagihanDialog :open="dialogBuat" :mahasiswa="mahasiswa" @close="dialogBuat = false" />
        <TagihanDetailDialog :open="detailId !== null" :tagihan-id="detailId" @close="detailId = null" />
    </AppLayout>
</template>
