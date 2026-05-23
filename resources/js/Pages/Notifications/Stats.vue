<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';

const props = defineProps({
    campaigns:  Array,
    standalone: Array,
    summary:    Object,
});

// ── Toast ─────────────────────────────────────────────────────────────────────

const toast = ref({ show: false, message: '', type: 'success' });
function showToast(message, type = 'success') {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 4000);
}

// ── Local reactive copies so sync updates reflect without full page reload ────

const campaigns  = reactive(props.campaigns.map(c => ({ ...c })));
const standalone = reactive(props.standalone.map(p => ({ ...p })));
const summary    = reactive({ ...props.summary });

function recalcSummary() {
    const allD = campaigns.reduce((s, c) => s + (c.delivered ?? 0), 0)
               + standalone.reduce((s, p) => s + (p.delivered ?? 0), 0);
    const allR = campaigns.reduce((s, c) => s + (c.recipients ?? 0), 0)
               + standalone.reduce((s, p) => s + (p.recipients ?? 0), 0);
    const allC = campaigns.reduce((s, c) => s + (c.converted ?? 0), 0)
               + standalone.reduce((s, p) => s + (p.converted ?? 0), 0);
    const allF = campaigns.reduce((s, c) => s + (c.failed ?? 0), 0)
               + standalone.reduce((s, p) => s + (p.failed ?? 0), 0);

    summary.total_recipients = allR;
    summary.total_delivered  = allD;
    summary.total_failed     = allF;
    summary.total_converted  = allC;
    summary.delivery_rate    = allR > 0 ? Math.round(allD / allR * 1000) / 10 : null;
    summary.ctr              = allD > 0 ? Math.round(allC / allD * 1000) / 10 : null;
}

// ── Active tab ────────────────────────────────────────────────────────────────

const activeTab = ref('campaigns');

// ── Sync campaign ─────────────────────────────────────────────────────────────

const syncingCampaign = reactive({});

async function syncCampaign(campaign) {
    if (syncingCampaign[campaign.id]) return;
    syncingCampaign[campaign.id] = true;
    try {
        const res = await fetch(route('campaign-pushes.sync-stats', campaign.id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                'Accept': 'application/json',
            },
        });
        if (!res.ok) throw new Error();
        const data = await res.json();

        const idx = campaigns.findIndex(c => c.id === campaign.id);
        if (idx !== -1) {
            campaigns[idx].recipients    = data.recipients;
            campaigns[idx].delivered     = data.delivered;
            campaigns[idx].failed        = data.failed;
            campaigns[idx].converted     = data.converted;
            campaigns[idx].synced_pushes = data.push_count;
            campaigns[idx].delivery_rate = data.recipients > 0
                ? Math.round(data.delivered / data.recipients * 1000) / 10 : null;
            campaigns[idx].ctr           = data.delivered > 0
                ? Math.round(data.converted / data.delivered * 1000) / 10 : null;
            campaigns[idx].stats_synced_at = data.synced_at;
        }
        recalcSummary();
        showToast('Štatistiky kampane boli aktualizované.');
    } catch (_) {
        showToast('Chyba pri synchronizácii štatistík.', 'error');
    }
    syncingCampaign[campaign.id] = false;
}

// ── Sync standalone ───────────────────────────────────────────────────────────

const syncingStandalone = reactive({});

async function syncStandalone(push) {
    if (syncingStandalone[push.id] || !push.onesignal_id) return;
    syncingStandalone[push.id] = true;
    try {
        const res = await fetch(route('standalone-pushes.stats', push.id));
        if (!res.ok) throw new Error();
        const data = await res.json();

        const idx = standalone.findIndex(p => p.id === push.id);
        if (idx !== -1) {
            standalone[idx].recipients    = data.recipients ?? 0;
            standalone[idx].delivered     = data.delivered  ?? 0;
            standalone[idx].failed        = data.failed     ?? 0;
            standalone[idx].converted     = data.converted  ?? 0;
            standalone[idx].delivery_rate = (data.recipients ?? 0) > 0
                ? Math.round(data.delivered / data.recipients * 1000) / 10 : null;
            standalone[idx].ctr           = (data.delivered ?? 0) > 0
                ? Math.round(data.converted / data.delivered * 1000) / 10 : null;
            standalone[idx].stats_synced_at = new Date().toISOString();
        }
        recalcSummary();
        showToast('Štatistiky notifikácie boli aktualizované.');
    } catch (_) {
        showToast('Chyba pri synchronizácii štatistík.', 'error');
    }
    syncingStandalone[push.id] = false;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const campaignStatusLabel = { draft: 'Draft', testing: 'Testovanie', active: 'Aktívna' };
const campaignStatusClass = {
    draft:   'bg-gray-100 text-gray-500',
    testing: 'bg-amber-100 text-amber-700',
    active:  'bg-green-100 text-green-700',
};

const targetLabel = {
    all:      'Všetci',
    testers:  'Testeri',
    tester:   'Tester',
    store:    'Predajňa',
    segment:  'Segment',
    filtered: 'Filter',
};

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('sk-SK', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function pct(val) {
    return val !== null && val !== undefined ? val.toFixed(1) + ' %' : '—';
}

function num(val) {
    return val !== null && val !== undefined ? val.toLocaleString('sk') : '—';
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Štatistiky notifikácií</h2>
                <a :href="route('notifications.index')"
                   class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    ← Späť na notifikácie
                </a>
            </div>
        </template>

        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <!-- Toast -->
            <Transition enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 translate-y-2"
                        leave-active-class="transition duration-150 ease-in"
                        leave-to-class="opacity-0 translate-y-2">
                <div v-if="toast.show"
                     :class="['fixed bottom-6 right-6 z-50 px-5 py-3 rounded-xl shadow-lg text-sm font-medium',
                              toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-green-600 text-white']">
                    {{ toast.message }}
                </div>
            </Transition>

            <!-- Summary cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Príjemcovia</p>
                    <p class="text-2xl font-bold text-gray-800">{{ num(summary.total_recipients) }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Doručené</p>
                    <p class="text-2xl font-bold text-green-700">{{ num(summary.total_delivered) }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Nedoručené</p>
                    <p class="text-2xl font-bold text-red-600">{{ num(summary.total_failed) }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Konverzie</p>
                    <p class="text-2xl font-bold text-indigo-700">{{ num(summary.total_converted) }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Delivery rate</p>
                    <p class="text-2xl font-bold text-gray-800">{{ pct(summary.delivery_rate) }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">CTR</p>
                    <p class="text-2xl font-bold text-gray-800">{{ pct(summary.ctr) }}</p>
                </div>
            </div>

            <!-- Info note -->
            <p class="text-xs text-gray-400">
                Štatistiky sú ukladané z OneSignal pri každom otvorení detailu push notifikácie alebo po kliknutí na
                <strong>Sync</strong>. Údaje sa automaticky neaktualizujú.
            </p>

            <!-- Tabs -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex border-b border-gray-100">
                    <button v-for="tab in [{ key: 'campaigns', label: 'Kampane' }, { key: 'standalone', label: 'Samostatné notifikácie' }]"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            :class="['px-6 py-3 text-sm font-medium transition-colors',
                                     activeTab === tab.key
                                         ? 'border-b-2 border-indigo-600 text-indigo-600 bg-indigo-50/40'
                                         : 'text-gray-500 hover:text-gray-700']">
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Campaigns tab -->
                <div v-if="activeTab === 'campaigns'" class="overflow-x-auto">
                    <table v-if="campaigns.length" class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wide">
                                <th class="px-5 py-3 font-medium">Kampaň</th>
                                <th class="px-5 py-3 font-medium">Stav</th>
                                <th class="px-5 py-3 font-medium text-right">Pushe</th>
                                <th class="px-5 py-3 font-medium text-right">Príjemcovia</th>
                                <th class="px-5 py-3 font-medium text-right">Doručené</th>
                                <th class="px-5 py-3 font-medium text-right">Nedoruč.</th>
                                <th class="px-5 py-3 font-medium text-right">Delivery</th>
                                <th class="px-5 py-3 font-medium text-right">Konverzie</th>
                                <th class="px-5 py-3 font-medium text-right">CTR</th>
                                <th class="px-5 py-3 font-medium text-right">Posl. sync</th>
                                <th class="px-5 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="c in campaigns" :key="c.id"
                                class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-4">
                                    <a :href="route('campaigns.edit', c.id)"
                                       class="font-medium text-gray-800 hover:text-indigo-600 transition-colors">
                                        {{ c.name }}
                                    </a>
                                    <p class="text-xs text-gray-400 mt-0.5 truncate max-w-xs">{{ c.title }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', campaignStatusClass[c.status]]">
                                        {{ campaignStatusLabel[c.status] }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right text-gray-600">
                                    {{ c.synced_pushes }}<span class="text-gray-400">/{{ c.total_pushes }}</span>
                                </td>
                                <td class="px-5 py-4 text-right font-medium text-gray-700">{{ num(c.recipients) }}</td>
                                <td class="px-5 py-4 text-right font-medium text-green-700">{{ num(c.delivered) }}</td>
                                <td class="px-5 py-4 text-right font-medium text-red-600">{{ num(c.failed) }}</td>
                                <td class="px-5 py-4 text-right">
                                    <span :class="['font-semibold', c.delivery_rate !== null && c.delivery_rate >= 80 ? 'text-green-700' : c.delivery_rate !== null && c.delivery_rate >= 50 ? 'text-amber-600' : 'text-gray-500']">
                                        {{ pct(c.delivery_rate) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right font-medium text-indigo-700">{{ num(c.converted) }}</td>
                                <td class="px-5 py-4 text-right">
                                    <span :class="['font-semibold', c.ctr !== null && c.ctr >= 5 ? 'text-indigo-700' : c.ctr !== null && c.ctr >= 2 ? 'text-amber-600' : 'text-gray-500']">
                                        {{ pct(c.ctr) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right text-xs text-gray-400">
                                    {{ formatDate(c.stats_synced_at) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <button @click="syncCampaign(c)"
                                            :disabled="syncingCampaign[c.id]"
                                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100 disabled:opacity-50 transition-colors">
                                        <svg v-if="syncingCampaign[c.id]" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                        </svg>
                                        <svg v-else class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M4 9a9 9 0 0115 0M20 15a9 9 0 01-15 0"/>
                                        </svg>
                                        Sync
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="px-6 py-10 text-center text-sm text-gray-400">
                        Žiadne kampane s push notifikáciami.
                    </div>
                </div>

                <!-- Standalone tab -->
                <div v-if="activeTab === 'standalone'" class="overflow-x-auto">
                    <table v-if="standalone.length" class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wide">
                                <th class="px-5 py-3 font-medium">Správa</th>
                                <th class="px-5 py-3 font-medium">Dátum</th>
                                <th class="px-5 py-3 font-medium">Cieľ</th>
                                <th class="px-5 py-3 font-medium text-right">Príjemcovia</th>
                                <th class="px-5 py-3 font-medium text-right">Doručené</th>
                                <th class="px-5 py-3 font-medium text-right">Nedoruč.</th>
                                <th class="px-5 py-3 font-medium text-right">Delivery</th>
                                <th class="px-5 py-3 font-medium text-right">Konverzie</th>
                                <th class="px-5 py-3 font-medium text-right">CTR</th>
                                <th class="px-5 py-3 font-medium text-right">Posl. sync</th>
                                <th class="px-5 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="p in standalone" :key="p.id"
                                class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-4 max-w-xs">
                                    <p class="font-medium text-gray-800 truncate">{{ p.title }}</p>
                                    <span :class="['inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-xs font-medium',
                                                   p.status === 'sent' ? 'bg-green-100 text-green-700' :
                                                   p.status === 'error' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500']">
                                        {{ p.status === 'sent' ? 'Odoslaná' : p.status === 'error' ? 'Chyba' : 'Čaká' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-xs text-gray-500 whitespace-nowrap">{{ formatDate(p.created_at) }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                        {{ targetLabel[p.target_type] ?? p.target_type }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right font-medium text-gray-700">{{ num(p.recipients) }}</td>
                                <td class="px-5 py-4 text-right font-medium text-green-700">{{ num(p.delivered) }}</td>
                                <td class="px-5 py-4 text-right font-medium text-red-600">{{ num(p.failed) }}</td>
                                <td class="px-5 py-4 text-right">
                                    <span :class="['font-semibold', p.delivery_rate !== null && p.delivery_rate >= 80 ? 'text-green-700' : p.delivery_rate !== null && p.delivery_rate >= 50 ? 'text-amber-600' : 'text-gray-500']">
                                        {{ pct(p.delivery_rate) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right font-medium text-indigo-700">{{ num(p.converted) }}</td>
                                <td class="px-5 py-4 text-right">
                                    <span :class="['font-semibold', p.ctr !== null && p.ctr >= 5 ? 'text-indigo-700' : p.ctr !== null && p.ctr >= 2 ? 'text-amber-600' : 'text-gray-500']">
                                        {{ pct(p.ctr) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right text-xs text-gray-400">
                                    {{ formatDate(p.stats_synced_at) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <button @click="syncStandalone(p)"
                                            :disabled="syncingStandalone[p.id] || !p.onesignal_id"
                                            :title="!p.onesignal_id ? 'Notifikácia ešte nebola odoslaná' : ''"
                                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                        <svg v-if="syncingStandalone[p.id]" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                        </svg>
                                        <svg v-else class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M4 9a9 9 0 0115 0M20 15a9 9 0 01-15 0"/>
                                        </svg>
                                        Sync
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="px-6 py-10 text-center text-sm text-gray-400">
                        Žiadne samostatné notifikácie.
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
