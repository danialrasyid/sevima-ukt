<script setup>
import { onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'max-w-lg' },
});

const emit = defineEmits(['close']);

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
        >
            <div class="absolute inset-0 bg-slate-900/50" @click="emit('close')"></div>

            <div class="relative w-full rounded-lg bg-white shadow-xl" :class="maxWidth">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
                    <button
                        type="button"
                        class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Tutup"
                        @click="emit('close')"
                    >
                        ✕
                    </button>
                </div>

                <div class="max-h-[75vh] overflow-y-auto px-5 py-4">
                    <slot />
                </div>

                <div v-if="$slots.footer" class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
