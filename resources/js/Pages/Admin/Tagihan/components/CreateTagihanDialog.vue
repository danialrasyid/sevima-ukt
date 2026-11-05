<script setup>
import { useForm } from '@inertiajs/vue3';
import BaseModal from '@/Components/BaseModal.vue';

defineProps({
    open: { type: Boolean, default: false },
    // Daftar mahasiswa milik institusi admin (sudah dibatasi di server).
    mahasiswa: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const form = useForm({
    mahasiswa_id: '',
    nomor_tagihan: '',
    semester: '',
    nominal: '',
    tanggal_kedaluwarsa: '',
});

function tutup() {
    form.clearErrors();
    emit('close');
}

function submit() {
    form
        // Input datetime-local tidak membawa zona waktu; kirim sebagai ISO agar instan waktunya tepat.
        .transform((data) => ({
            ...data,
            tanggal_kedaluwarsa: data.tanggal_kedaluwarsa
                ? new Date(data.tanggal_kedaluwarsa).toISOString()
                : '',
        }))
        .post('/admin/tagihan', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                emit('close');
            },
        });
}

const kelasInput =
    'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500';
</script>

<template>
    <BaseModal :open="open" title="Buat tagihan UKT" @close="tutup">
        <form id="form-tagihan" class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="mahasiswa_id" class="block text-sm font-medium text-slate-700">Mahasiswa</label>
                <select id="mahasiswa_id" v-model="form.mahasiswa_id" required :class="kelasInput">
                    <option value="" disabled>Pilih mahasiswa…</option>
                    <option v-for="m in mahasiswa" :key="m.id" :value="m.id">
                        {{ m.nim }} · {{ m.nama }}
                    </option>
                </select>
                <p v-if="form.errors.mahasiswa_id" class="mt-1 text-xs text-rose-600">{{ form.errors.mahasiswa_id }}</p>
            </div>

            <div>
                <label for="nomor_tagihan" class="block text-sm font-medium text-slate-700">Nomor tagihan</label>
                <input id="nomor_tagihan" v-model="form.nomor_tagihan" type="text" required maxlength="50" placeholder="UKT-2026-010" :class="kelasInput" />
                <p v-if="form.errors.nomor_tagihan" class="mt-1 text-xs text-rose-600">{{ form.errors.nomor_tagihan }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="semester" class="block text-sm font-medium text-slate-700">Semester</label>
                    <input id="semester" v-model="form.semester" type="text" required maxlength="30" placeholder="2026/2027 Ganjil" :class="kelasInput" />
                    <p v-if="form.errors.semester" class="mt-1 text-xs text-rose-600">{{ form.errors.semester }}</p>
                </div>
                <div>
                    <label for="nominal" class="block text-sm font-medium text-slate-700">Nominal (Rp)</label>
                    <input id="nominal" v-model="form.nominal" type="number" required min="1" step="1" placeholder="2500000" :class="kelasInput" />
                    <p v-if="form.errors.nominal" class="mt-1 text-xs text-rose-600">{{ form.errors.nominal }}</p>
                </div>
            </div>

            <div>
                <label for="tanggal_kedaluwarsa" class="block text-sm font-medium text-slate-700">Tanggal kedaluwarsa</label>
                <input id="tanggal_kedaluwarsa" v-model="form.tanggal_kedaluwarsa" type="datetime-local" required :class="kelasInput" />
                <p v-if="form.errors.tanggal_kedaluwarsa" class="mt-1 text-xs text-rose-600">{{ form.errors.tanggal_kedaluwarsa }}</p>
            </div>
        </form>

        <template #footer>
            <button
                type="button"
                class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
                @click="tutup"
            >
                Batal
            </button>
            <button
                type="submit"
                form="form-tagihan"
                :disabled="form.processing"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
            >
                {{ form.processing ? 'Menyimpan…' : 'Simpan tagihan' }}
            </button>
        </template>
    </BaseModal>
</template>
