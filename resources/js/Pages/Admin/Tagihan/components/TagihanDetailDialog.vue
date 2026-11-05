<script setup>
import { reactive, watch } from 'vue';
import BaseModal from '@/Components/BaseModal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { labelEvent, labelHasil, rupiah, tanggal } from '@/lib/format';

const props = defineProps({
    open: { type: Boolean, default: false },
    tagihanId: { type: Number, default: null },
});

const emit = defineEmits(['close']);

const state = reactive({ loading: false, error: null, data: null });

// Mencegah respons lama menimpa respons baru bila dialog dibuka berturut-turut.
let permintaanTerakhir = 0;

async function muat() {
    if (!props.open || !props.tagihanId) {
        return;
    }

    const nomor = ++permintaanTerakhir;
    state.loading = true;
    state.error = null;
    state.data = null;

    try {
        // Memakai endpoint JSON "status tagihan + riwayat transaksi".
        const respons = await fetch(`/api/tagihan/${props.tagihanId}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!respons.ok) {
            throw new Error(`HTTP ${respons.status}`);
        }

        const json = await respons.json();

        if (nomor === permintaanTerakhir) {
            state.data = json.data;
        }
    } catch {
        if (nomor === permintaanTerakhir) {
            state.error = 'Gagal memuat detail tagihan. Coba lagi.';
        }
    } finally {
        if (nomor === permintaanTerakhir) {
            state.loading = false;
        }
    }
}

watch(() => [props.open, props.tagihanId], muat, { immediate: true });
</script>

<template>
    <BaseModal :open="open" title="Detail tagihan" max-width="max-w-3xl" @close="emit('close')">
        <p v-if="state.loading" class="text-sm text-slate-500">Memuat…</p>
        <p v-else-if="state.error" class="text-sm text-rose-600" role="alert">{{ state.error }}</p>

        <div v-else-if="state.data" class="space-y-5">
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-slate-500">Nomor tagihan</dt>
                    <dd class="font-medium text-slate-900">{{ state.data.nomor_tagihan }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Status</dt>
                    <dd><StatusBadge :status="state.data.status_efektif" /></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Mahasiswa</dt>
                    <dd class="font-medium text-slate-900">
                        {{ state.data.mahasiswa?.nama }} ({{ state.data.mahasiswa?.nim }})
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Semester</dt>
                    <dd class="font-medium text-slate-900">{{ state.data.semester }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Nominal</dt>
                    <dd class="font-medium text-slate-900">{{ rupiah(state.data.nominal) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Kedaluwarsa</dt>
                    <dd class="font-medium text-slate-900">{{ tanggal(state.data.tanggal_kedaluwarsa) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Dibayar pada</dt>
                    <dd class="font-medium text-slate-900">{{ tanggal(state.data.paid_at) }}</dd>
                </div>
            </dl>

            <div
                v-if="state.data.perlu_review"
                class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
            >
                <p class="font-medium">Perlu ditinjau</p>
                <p class="whitespace-pre-line">{{ state.data.catatan_review }}</p>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold text-slate-900">Riwayat transaksi</h3>

                <p v-if="!state.data.riwayat.length" class="text-sm text-slate-500">Belum ada transaksi.</p>

                <div v-else class="overflow-x-auto rounded-md border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Waktu</th>
                                <th class="px-3 py-2">Event</th>
                                <th class="px-3 py-2">Hasil</th>
                                <th class="px-3 py-2">Nominal</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="x in state.data.riwayat" :key="x.id" class="align-top">
                                <td class="whitespace-nowrap px-3 py-2">{{ tanggal(x.occurred_at) }}</td>
                                <td class="px-3 py-2">
                                    {{ labelEvent[x.event_type] ?? x.event_type }}
                                    <p class="font-mono text-xs text-slate-400">{{ x.event_id }}</p>
                                </td>
                                <td class="px-3 py-2">{{ labelHasil[x.hasil] ?? x.hasil }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ rupiah(x.nominal) }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ x.status_sebelum }} → {{ x.status_sesudah }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ x.keterangan ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <template #footer>
            <button
                type="button"
                class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
                @click="emit('close')"
            >
                Tutup
            </button>
        </template>
    </BaseModal>
</template>
