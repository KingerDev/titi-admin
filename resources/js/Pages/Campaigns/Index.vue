<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    campaigns: Array,
});

const page  = usePage();
const flash = computed(() => page.props.flash);

const toast = ref({ show: false, message: '', type: 'success' });
function showToast(message, type = 'success') {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3000);
}
if (flash.value?.success) showToast(flash.value.success);

const statusLabel = { draft: 'Draft', testing: 'Testovanie', active: 'Aktívna' };
const statusClass  = { draft: 'bg-gray-100 text-gray-600', testing: 'bg-amber-100 text-amber-700', active: 'bg-green-100 text-green-700' };

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('sk-SK', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function deleteCampaign(campaign) {
    if (!confirm(`Zmazať kampaň „${campaign.name}"?`)) return;
    router.delete(route('campaigns.destroy', campaign.id), {
        onSuccess: () => showToast('Kampaň bola zmazaná.'),
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Kampane</h2>
                <a :href="route('campaigns.create')"
                   class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nová kampaň
                </a>
            </div>
        </template>

        <!-- Toast -->
        <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200" leave-to-class="opacity-0">
            <div v-if="toast.show" :class="toast.type === 'error' ? 'bg-red-600' : 'bg-green-600'"
                 class="fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                {{ toast.message }}
            </div>
        </Transition>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

                <!-- Empty state -->
                <div v-if="campaigns.length === 0"
                     class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-white py-16 text-center">
                    <svg class="mb-4 h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <p class="text-sm font-medium text-gray-500">Žiadne kampane</p>
                    <p class="mt-1 text-xs text-gray-400">Vytvorte svoju prvú kampaň kliknutím na tlačidlo vyššie.</p>
                </div>

                <!-- Table -->
                <div v-else class="overflow-hidden rounded-xl bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Kampaň</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Stav</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Pushe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Expirácia</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Vytvorená</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Akcie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="c in campaigns" :key="c.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-900">{{ c.name }}</p>
                                    <p class="text-sm text-gray-500 truncate max-w-xs">{{ c.title }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="statusClass[c.status] ?? 'bg-gray-100 text-gray-600'"
                                          class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                                        {{ statusLabel[c.status] ?? c.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ c.pushes_count ?? 0 }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ formatDate(c.expires_at) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ formatDate(c.created_at) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a :href="route('campaigns.edit', c.id)"
                                           class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200 transition-colors">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Upraviť
                                        </a>
                                        <a :href="route('campaign-pushes.index', c.id)"
                                           class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 transition-colors">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                            </svg>
                                            Pushe
                                        </a>
                                        <button @click="deleteCampaign(c)"
                                                class="inline-flex items-center gap-1 rounded-md bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-100 transition-colors">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            Zmazať
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
