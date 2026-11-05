<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { labelEvent, rupiah, tanggal } from '@/lib/format';

defineProps({
    mahasiswa: { type: Object, required: true },
    tagihan: { type: Array, default: () => [] },
    bisaIsiKrs: { type: Boolean, default: false },
});
</script>

<template>
    <Head title="Tagihan Saya" />

    <AppLayout>
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-slate-900">Tagihan Saya</h1>
            <p class="text-sm text-slate-500">{{ mahasiswa.nama }} · NIM {{ mahasiswa.nim }}</p>
        </div>

        <div
            class="mb-6 rounded-md border px-4 py-3 text-sm"
            :class="bisaIsiKrs ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900'"
            role="status"
        >
            <template v-if="bisaIsiKrs">UKT terbaru sudah lunas. Anda dapat mengisi KRS.</template>
            <template v-else>UKT terbaru belum lunas. Anda belum dapat mengisi KRS.</template>
        </div>

        <p v-if="!tagihan.length" class="rounded-lg border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
            Belum ada tagihan.
        </p>

        <div class="space-y-5">
            <section
                v-for="t in tagihan"
                :key="t.id"
                class="rounded-lg border border-slate-200 bg-white"
            >
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <div>
                        <p class="font-semibold text-slate-900">{{ t.nomor_tagihan }}</p>
                        <p class="text-sm text-slate-500">Semester {{ t.semester }}</p>
                    </div>
                    <StatusBadge :status="t.status_efektif" />
                </div>

                <dl class="grid grid-cols-1 gap-3 px-4 py-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-slate-500">Nominal</dt>
                        <dd class="font-medium text-slate-900">{{ rupiah(t.nominal) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Batas pembayaran</dt>
                        <dd class="font-medium text-slate-900">{{ tanggal(t.tanggal_kedaluwarsa) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Dibayar pada</dt>
                        <dd class="font-medium text-slate-900">{{ tanggal(t.paid_at) }}</dd>
                    </div>
                </dl>

                <div class="border-t border-slate-100 px-4 py-3">
                    <h2 class="mb-2 text-sm font-semibold text-slate-900">Riwayat transaksi</h2>

                    <p v-if="!t.riwayat.length" class="text-sm text-slate-500">Belum ada transaksi.</p>

                    <ul v-else class="space-y-2">
                        <li
                            v-for="x in t.riwayat"
                            :key="x.id"
                            class="rounded-md bg-slate-50 px-3 py-2 text-sm"
                        >
                            <div class="flex flex-wrap justify-between gap-2">
                                <span class="font-medium text-slate-800">{{ labelEvent[x.event_type] ?? x.event_type }}</span>
                                <span class="text-slate-500">{{ tanggal(x.occurred_at) }}</span>
                            </div>
                            <p v-if="x.nominal !== null" class="text-slate-600">{{ rupiah(x.nominal) }}</p>
                            <p v-if="x.keterangan" class="text-slate-500">{{ x.keterangan }}</p>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
