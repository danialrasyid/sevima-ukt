<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const flash = computed(() => page.props.flash?.success ?? null);

const nav = computed(() =>
    user.value?.role === 'admin_keuangan'
        ? [{ href: '/admin/tagihan', label: 'Tagihan' }]
        : [{ href: '/mahasiswa/tagihan', label: 'Tagihan Saya' }],
);

const labelRole = computed(() =>
    user.value?.role === 'admin_keuangan' ? 'Admin Keuangan' : 'Mahasiswa',
);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-800">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <div class="flex items-center gap-6">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Tagihan UKT</p>
                        <p v-if="user?.institusi" class="text-xs text-slate-500">
                            {{ user.institusi.nama }} ({{ user.institusi.kode }})
                        </p>
                    </div>
                    <nav class="flex gap-4 text-sm">
                        <Link
                            v-for="item in nav"
                            :key="item.href"
                            :href="item.href"
                            class="font-medium text-indigo-700 hover:text-indigo-900"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>

                <div v-if="user" class="flex items-center gap-3 text-sm">
                    <div class="text-right">
                        <p class="font-medium text-slate-900">{{ user.name }}</p>
                        <p class="text-xs text-slate-500">{{ labelRole }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-100"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6">
            <div
                v-if="flash"
                class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"
                role="status"
            >
                {{ flash }}
            </div>

            <slot />
        </main>
    </div>
</template>
