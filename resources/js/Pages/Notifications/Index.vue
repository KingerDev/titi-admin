<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    campaigns: Array,
    pushes:    Array,
    stores:    Array,
    testers:   Array,
});

const page = usePage();

// ── Toast ────────────────────────────────────────────────────────────────────

const toast = ref({ show: false, message: '', type: 'success' });
function showToast(message, type = 'success') {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 4000);
}
if (page.props.flash?.success) showToast(page.props.flash.success);

// ── Campaign selection ───────────────────────────────────────────────────────

const selectedCampaignId = ref(null);

const selectedCampaign = computed(() =>
    props.campaigns?.find(c => c.id === selectedCampaignId.value) ?? null
);

const filteredPushes = computed(() =>
    selectedCampaignId.value
        ? props.pushes?.filter(p => p.notification_id === selectedCampaignId.value)
        : []
);

function selectCampaign(id) {
    selectedCampaignId.value = id;
    // Load stats for pushes of this campaign that have a onesignal_id
    filteredPushes.value.forEach(p => { if (p.onesignal_id && !pushStats.value[p.id]) loadStats(p); });
}

// ── Push modal ───────────────────────────────────────────────────────────────

const showModal = ref(false);

const pushForm = useForm({
    target_type:      'all',
    target_store_id:  null,
    target_tester_id: null,
    condition:        'none',
    send_mode:        'now',
    send_at:          '',
});

function openModal() {
    if (!selectedCampaignId.value) return;
    pushForm.reset();
    showModal.value = true;
}

function submitPush() {
    pushForm.post(route('campaign-pushes.store', selectedCampaignId.value), {
        onSuccess: () => {
            showModal.value = false;
            showToast(pushForm.send_mode === 'now' ? 'Notifikácia bola odoslaná.' : 'Notifikácia bola naplánovaná.');
        },
        onError: () => showToast('Skontrolujte chyby vo formulári.', 'error'),
    });
}

function cancelPush(push) {
    if (!confirm('Zrušiť túto push notifikáciu?')) return;
    router.delete(route('campaign-pushes.destroy', { campaignId: push.notification_id, pushId: push.id }), {
        onSuccess: () => showToast('Push notifikácia bola zrušená.'),
    });
}

// ── Stats ────────────────────────────────────────────────────────────────────

const pushStats = ref({});

async function loadStats(push) {
    if (!push.onesignal_id) return;
    try {
        const res  = await fetch(route('campaign-pushes.stats', { campaignId: push.notification_id, pushId: push.id }));
        const data = await res.json();
        pushStats.value[push.id] = data;
    } catch (_) {}
}

// ── Helpers ──────────────────────────────────────────────────────────────────

const campaignStatusLabel = { draft: 'Draft', testing: 'Testovanie', active: 'Aktívna' };
const campaignStatusClass  = {
    draft:   'bg-gray-100 text-gray-500',
    testing: 'bg-amber-100 text-amber-700',
    active:  'bg-green-100 text-green-700',
};

const targetLabel = {
    all:     'Všetci používatelia',
    testers: 'Všetci testeri',
    tester:  'Konkrétny tester',
    store:   'Zákazníci predajne',
};

const pushStatusLabel = { pending: 'Čaká', sent: 'Odoslaná', cancelled: 'Zrušená' };
const pushStatusClass  = {
    pending:   'bg-blue-100 text-blue-700',
    sent:      'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
};

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('sk-SK', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function testerName(id) {
    return props.testers?.find(t => t.customer_id === id)?.note || `ID ${id}`;
}

function storeName(id) {
    return props.stores?.find(s => s.store_id === id)?.name || `ID ${id}`;
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Notifikácie</h2>
                <button
                    @click="openModal"
                    :disabled="!selectedCampaignId"
                    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nový push
                </button>
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
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6 items-start">

                    <!-- Campaign list (sidebar) -->
                    <div class="w-72 shrink-0">
                        <div class="rounded-xl bg-white shadow overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Kampane</p>
                            </div>

                            <div v-if="!campaigns?.length" class="px-4 py-6 text-center text-sm text-gray-400">
                                Žiadne kampane
                            </div>

                            <ul v-else class="divide-y divide-gray-50">
                                <li v-for="c in campaigns" :key="c.id">
                                    <button
                                        @click="selectCampaign(c.id)"
                                        :class="selectedCampaignId === c.id
                                            ? 'bg-indigo-50 border-l-2 border-indigo-500'
                                            : 'hover:bg-gray-50 border-l-2 border-transparent'"
                                        class="w-full text-left px-4 py-3 transition-colors"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-sm font-medium text-gray-900 truncate">{{ c.name }}</span>
                                            <span v-if="c.pushes_count"
                                                  class="shrink-0 inline-flex items-center justify-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                {{ c.pushes_count }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span :class="campaignStatusClass[c.status]"
                                                  class="rounded-full px-1.5 py-0.5 text-xs font-medium">
                                                {{ campaignStatusLabel[c.status] }}
                                            </span>
                                            <span class="text-xs text-gray-400 truncate">{{ c.title }}</span>
                                        </div>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <p class="mt-3 text-center">
                            <a :href="route('campaigns.index')" class="text-xs text-gray-400 hover:text-indigo-600 transition-colors">
                                Spravovať kampane →
                            </a>
                        </p>
                    </div>

                    <!-- Push list (main area) -->
                    <div class="flex-1 min-w-0">

                        <!-- No campaign selected -->
                        <div v-if="!selectedCampaignId"
                             class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-white py-20 text-center">
                            <svg class="mb-4 h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <p class="text-sm font-medium text-gray-500">Vyberte kampaň</p>
                            <p class="mt-1 text-xs text-gray-400">Kliknutím na kampaň vľavo zobrazíte jej push notifikácie.</p>
                        </div>

                        <!-- Selected campaign header -->
                        <template v-else>
                            <div class="mb-4 flex items-center justify-between">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">{{ selectedCampaign?.name }}</h3>
                                    <p class="text-sm text-gray-500">{{ selectedCampaign?.title }}</p>
                                </div>
                                <a :href="route('campaigns.edit', selectedCampaignId)"
                                   class="text-xs text-gray-400 hover:text-indigo-600 transition-colors">
                                    Upraviť obsah kampane →
                                </a>
                            </div>

                            <!-- No pushes yet -->
                            <div v-if="!filteredPushes.length"
                                 class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-white py-16 text-center">
                                <p class="text-sm font-medium text-gray-500 mb-1">Žiadne push notifikácie</p>
                                <p class="text-xs text-gray-400 mb-4">Kampaň zatiaľ nebola odoslaná žiadnemu segmentu.</p>
                                <button @click="openModal"
                                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Pridať prvý push
                                </button>
                            </div>

                            <!-- Push list -->
                            <div v-else class="space-y-3">
                                <div v-for="push in filteredPushes" :key="push.id"
                                     class="rounded-xl bg-white shadow p-5">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="space-y-2 min-w-0">
                                            <!-- Target -->
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-sm font-semibold text-gray-900">{{ targetLabel[push.target_type] }}</span>
                                                <span v-if="push.target_type === 'tester'"
                                                      class="rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-700">
                                                    {{ testerName(push.target_tester_id) }}
                                                </span>
                                                <span v-if="push.target_type === 'store'"
                                                      class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">
                                                    {{ storeName(push.target_store_id) }}
                                                </span>
                                                <span v-if="push.condition === 'unread_only'"
                                                      class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">
                                                    len neprečítaní
                                                </span>
                                            </div>

                                            <!-- Timing -->
                                            <p class="text-sm text-gray-500">
                                                <span v-if="push.send_at">
                                                    <span class="font-medium text-gray-700">Naplánovaná</span> na {{ formatDate(push.send_at) }}
                                                </span>
                                                <span v-else>
                                                    <span class="font-medium text-gray-700">Odoslaná</span> {{ formatDate(push.created_at) }}
                                                </span>
                                            </p>

                                            <!-- Stats -->
                                            <div v-if="pushStats[push.id]" class="flex items-center gap-6 pt-1">
                                                <div class="text-center">
                                                    <p class="text-lg font-bold text-gray-900">{{ pushStats[push.id].delivered ?? '—' }}</p>
                                                    <p class="text-xs text-gray-400">doručených</p>
                                                </div>
                                                <div class="text-center">
                                                    <p class="text-lg font-bold text-gray-900">{{ pushStats[push.id].converted ?? '—' }}</p>
                                                    <p class="text-xs text-gray-400">kliknutí</p>
                                                </div>
                                                <div class="text-center">
                                                    <p class="text-lg font-bold text-gray-900">{{ pushStats[push.id].failed ?? '—' }}</p>
                                                    <p class="text-xs text-gray-400">neúspešných</p>
                                                </div>
                                                <button @click="loadStats(push)"
                                                        class="ml-2 inline-flex items-center gap-1 text-xs text-indigo-500 hover:text-indigo-700 transition-colors">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                    </svg>
                                                    Obnoviť
                                                </button>
                                            </div>
                                            <div v-else-if="push.onesignal_id">
                                                <button @click="loadStats(push)"
                                                        class="text-xs text-indigo-500 hover:text-indigo-700 transition-colors">
                                                    Načítať štatistiky
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Status + cancel -->
                                        <div class="flex flex-col items-end gap-2 shrink-0">
                                            <span :class="pushStatusClass[push.status]"
                                                  class="rounded-full px-3 py-1 text-xs font-medium">
                                                {{ pushStatusLabel[push.status] }}
                                            </span>
                                            <button v-if="push.status === 'pending'"
                                                    @click="cancelPush(push)"
                                                    class="text-xs text-red-500 hover:text-red-700 transition-colors">
                                                Zrušiť
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                </div>
            </div>
        </div>

        <!-- Push modal -->
        <Teleport to="body">
            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0"
                        enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-to-class="opacity-0">
                <div v-if="showModal"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                     @click.self="showModal = false">
                    <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">Nový push</h3>
                                <p class="text-xs text-gray-400 mt-0.5">{{ selectedCampaign?.name }}</p>
                            </div>
                            <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="p-6 space-y-4">

                            <!-- Target type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Komu odoslať</label>
                                <div class="space-y-1.5">
                                    <label v-for="opt in [
                                        { value: 'all',     label: 'Všetci používatelia' },
                                        { value: 'testers', label: 'Všetci testeri' },
                                        { value: 'tester',  label: 'Konkrétny tester' },
                                        { value: 'store',   label: 'Zákazníci predajne' },
                                    ]" :key="opt.value" class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="pushForm.target_type" type="radio" :value="opt.value" class="text-indigo-600"/>
                                        <span class="text-sm text-gray-700">{{ opt.label }}</span>
                                    </label>
                                </div>
                                <p v-if="pushForm.errors.target_type" class="mt-1 text-xs text-red-500">{{ pushForm.errors.target_type }}</p>
                            </div>

                            <!-- Tester select -->
                            <div v-if="pushForm.target_type === 'tester'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tester <span class="text-red-500">*</span></label>
                                <select v-model="pushForm.target_tester_id"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                    <option :value="null" disabled>Vybrať testera...</option>
                                    <option v-for="t in testers" :key="t.customer_id" :value="t.customer_id">
                                        {{ t.note || 'ID ' + t.customer_id }}
                                    </option>
                                </select>
                                <p v-if="pushForm.errors.target_tester_id" class="mt-1 text-xs text-red-500">{{ pushForm.errors.target_tester_id }}</p>
                            </div>

                            <!-- Store select -->
                            <div v-if="pushForm.target_type === 'store'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Predajňa <span class="text-red-500">*</span></label>
                                <select v-model="pushForm.target_store_id"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                    <option :value="null" disabled>Vybrať predajňu...</option>
                                    <option v-for="s in stores" :key="s.store_id" :value="s.store_id">{{ s.name }}</option>
                                </select>
                                <p v-if="pushForm.errors.target_store_id" class="mt-1 text-xs text-red-500">{{ pushForm.errors.target_store_id }}</p>
                            </div>

                            <!-- Condition -->
                            <div v-if="pushForm.target_type === 'all' || pushForm.target_type === 'store'">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="pushForm.condition" type="checkbox"
                                           true-value="unread_only" false-value="none" class="rounded text-indigo-600"/>
                                    <span class="text-sm text-gray-700">Poslať len tým, ktorí si kampaň ešte neprečítali</span>
                                </label>
                            </div>

                            <!-- Send mode -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Kedy odoslať</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="pushForm.send_mode" type="radio" value="now" class="text-indigo-600"/>
                                        <span class="text-sm text-gray-700">Ihneď</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="pushForm.send_mode" type="radio" value="scheduled" class="text-indigo-600"/>
                                        <span class="text-sm text-gray-700">Naplánovať</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Send at -->
                            <div v-if="pushForm.send_mode === 'scheduled'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Dátum a čas <span class="text-red-500">*</span></label>
                                <input v-model="pushForm.send_at" type="datetime-local"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                <p v-if="pushForm.errors.send_at" class="mt-1 text-xs text-red-500">{{ pushForm.errors.send_at }}</p>
                            </div>

                        </div>

                        <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
                            <button @click="showModal = false"
                                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                                Zrušiť
                            </button>
                            <button @click="submitPush" :disabled="pushForm.processing"
                                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                {{ pushForm.send_mode === 'now' ? 'Odoslať' : 'Naplánovať' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AuthenticatedLayout>
</template>
