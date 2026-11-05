<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const tampilan = {
    belum_bayar: { label: 'Belum bayar', kelas: 'bg-amber-100 text-amber-800 ring-amber-200' },
    lunas: { label: 'Lunas', kelas: 'bg-emerald-100 text-emerald-800 ring-emerald-200' },
    kedaluwarsa: { label: 'Kedaluwarsa', kelas: 'bg-rose-100 text-rose-800 ring-rose-200' },
};

const item = computed(
    () => tampilan[props.status] ?? { label: props.status, kelas: 'bg-slate-100 text-slate-700 ring-slate-200' },
);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
        :class="item.kelas"
    >
        {{ item.label }}
    </span>
</template>
