<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    campaigns:  Array,
    pushes:     Array,
    standalone: Array,
    stores:     Array,
    testers:    Array,
});

const page = usePage();

// ── Toast ─────────────────────────────────────────────────────────────────────

const toast = ref({ show: false, message: '', type: 'success' });
function showToast(message, type = 'success') {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 4000);
}
if (page.props.flash?.success) showToast(page.props.flash.success);

// ── Constants / helpers ───────────────────────────────────────────────────────

const SCHEME = 'titiapp://';

const campaignStatusLabel = { draft: 'Draft', testing: 'Testovanie', active: 'Aktívna' };
const campaignStatusClass  = {
    draft:   'bg-gray-100 text-gray-500',
    testing: 'bg-amber-100 text-amber-700',
    active:  'bg-green-100 text-green-700',
};

const targetLabel = {
    all:      'Všetci používatelia',
    testers:  'Všetci testeri',
    tester:   'Konkrétny tester',
    store:    'Zákazníci predajne',
    segment:  'Segment',
    filtered: 'Vlastné filtre',
};

const pushStatusLabel = { pending: 'Čaká', sent: 'Odoslaná', cancelled: 'Zrušená', error: 'Chyba' };
const pushStatusClass  = {
    pending:   'bg-blue-100 text-blue-700',
    sent:      'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
    error:     'bg-red-100 text-red-700',
};

const ttlOptions = [
    { value: '',      label: 'Predvolené (3 dni)' },
    { value: 3600,    label: '1 hodina' },
    { value: 21600,   label: '6 hodín' },
    { value: 86400,   label: '1 deň' },
    { value: 259200,  label: '3 dni' },
    { value: 604800,  label: '1 týždeň' },
    { value: 2419200, label: '28 dní' },
];

const relationOptions = ['=', '!=', '<', '<=', '>', '>=', 'exists', 'not_exists'];

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('sk-SK', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function testerName(id) { return props.testers?.find(t => t.customer_id === id)?.note || `ID ${id}`; }
function storeName(id)  { return props.stores?.find(s => s.store_id === id)?.name  || `ID ${id}`; }

function newFilterRow() { return { key: '', relation: '=', value: '' }; }

// ── Tabs ──────────────────────────────────────────────────────────────────────

const activeTab = ref('campaigns'); // campaigns | standalone | scheduled | history

// ── App Stats ─────────────────────────────────────────────────────────────────

const appStats        = ref(null);
const appStatsLoading = ref(false);

async function loadAppStats() {
    appStatsLoading.value = true;
    try {
        const res = await fetch(route('notifications.app-stats'));
        appStats.value = await res.json();
    } catch (_) {}
    appStatsLoading.value = false;
}

// ── Segments autocomplete ─────────────────────────────────────────────────────

const segments                    = ref([]);
const campaignSegmentQuery        = ref('');
const standaloneSegmentQuery      = ref('');
const showCampaignSegmentDrop     = ref(false);
const showStandaloneSegmentDrop   = ref(false);

async function loadSegments() {
    try {
        const res  = await fetch(route('notifications.segments'));
        const data = await res.json();
        segments.value = Array.isArray(data) ? data : (data.segments ?? []);
    } catch (_) {}
}

const filteredSegmentsCampaign = computed(() => {
    const q = campaignSegmentQuery.value.toLowerCase();
    return segments.value.filter(s => !q || s.toLowerCase().includes(q)).slice(0, 10);
});

const filteredSegmentsStandalone = computed(() => {
    const q = standaloneSegmentQuery.value.toLowerCase();
    return segments.value.filter(s => !q || s.toLowerCase().includes(q)).slice(0, 10);
});

// ── Campaign selection + aggregate stats ──────────────────────────────────────

const selectedCampaignId = ref(null);

const selectedCampaign = computed(() =>
    props.campaigns?.find(c => c.id === selectedCampaignId.value) ?? null
);

const filteredPushes = computed(() =>
    selectedCampaignId.value
        ? props.pushes?.filter(p => p.notification_id === selectedCampaignId.value)
        : []
);

const campaignAggregate = computed(() => {
    let delivered = 0, converted = 0, failed = 0, counted = 0;
    filteredPushes.value.forEach(p => {
        const s = pushStats.value[p.id];
        if (s) { delivered += s.delivered ?? 0; converted += s.converted ?? 0; failed += s.failed ?? 0; counted++; }
    });
    return counted > 0 ? { delivered, converted, failed, counted } : null;
});

function selectCampaign(id) {
    selectedCampaignId.value = id;
    const pushes = props.pushes?.filter(p => p.notification_id === id) ?? [];
    pushes.forEach(p => { if (p.onesignal_id && !pushStats.value[p.id]) loadStats(p, false); });
}

// ── Recipient preview ─────────────────────────────────────────────────────────

function recipientPreview(targetType) {
    if (targetType === 'all')     return appStats.value?.messageable != null ? appStats.value.messageable.toLocaleString('sk') : '—';
    if (targetType === 'testers') return props.testers?.length ?? '—';
    if (targetType === 'tester')  return '1';
    return null;
}

// ── Confirmation dialog ───────────────────────────────────────────────────────

const confirmDialog = ref({ show: false, count: 0, onConfirm: null });

function showConfirmDialog(count, onConfirm) {
    confirmDialog.value = { show: true, count, onConfirm };
}

function confirmSend() {
    confirmDialog.value.show = false;
    confirmDialog.value.onConfirm?.();
}

// ── Templates ─────────────────────────────────────────────────────────────────

const templates        = ref([]);
const templatesLoading = ref(false);
const showSaveTemplate = ref(false);
const templateName     = ref('');
const savingTemplate   = ref(false);

async function loadTemplates() {
    templatesLoading.value = true;
    try {
        const res = await fetch(route('notification-templates.index'));
        templates.value = await res.json();
    } catch (_) {}
    templatesLoading.value = false;
}

async function deleteTemplate(id) {
    if (!confirm('Zmazať šablónu?')) return;
    await fetch(route('notification-templates.destroy', id), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': page.props.csrf_token ?? document.querySelector('meta[name=csrf-token]')?.content } });
    templates.value = templates.value.filter(t => t.id !== id);
    showToast('Šablóna bola zmazaná.');
}

function applyTemplate(t) {
    standaloneForm.title            = t.title            ?? '';
    standaloneForm.message          = t.message          ?? '';
    standaloneForm.subtitle         = t.subtitle         ?? '';
    standaloneForm.image            = t.image            ?? '';
    standaloneForm.target_type      = t.target_type      ?? 'all';
    standaloneForm.target_store_id  = t.target_store_id  ?? null;
    standaloneForm.target_tester_id = t.target_tester_id ?? null;
    standaloneForm.target_segment   = t.target_segment   ?? '';
    standaloneForm.ttl              = t.ttl              ?? '';
    standaloneForm.priority         = t.priority         ?? 10;
    standaloneForm.collapse_id      = t.collapse_id      ?? '';
    standaloneForm.ios_badge_type   = t.ios_badge_type   ?? '';
    standaloneForm.ios_badge_count  = t.ios_badge_count  ?? '';
    standaloneSegmentQuery.value    = t.target_segment   ?? '';
    standaloneFilters.value = t.target_filters?.length ? [...t.target_filters] : [newFilterRow()];
    standaloneUrlPath.value = t.push_url ? t.push_url.replace(SCHEME, '') : '';
    showToast('Šablóna bola načítaná.');
}

async function saveTemplate() {
    if (!templateName.value.trim()) return;
    savingTemplate.value = true;
    const body = {
        name:             templateName.value.trim(),
        title:            standaloneForm.title,
        message:          standaloneForm.message,
        subtitle:         standaloneForm.subtitle || null,
        image:            standaloneForm.image || null,
        push_url:         standaloneForm.push_url || null,
        target_type:      standaloneForm.target_type,
        target_store_id:  standaloneForm.target_store_id,
        target_tester_id: standaloneForm.target_tester_id,
        target_segment:   standaloneForm.target_segment || null,
        target_filters:   standaloneForm.target_type === 'filtered' ? standaloneFilters.value : null,
        ttl:              standaloneForm.ttl || null,
        priority:         standaloneForm.priority,
        collapse_id:      standaloneForm.collapse_id || null,
        ios_badge_type:   standaloneForm.ios_badge_type || null,
        ios_badge_count:  standaloneForm.ios_badge_count || null,
    };
    try {
        const res = await fetch(route('notification-templates.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
            },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (res.ok) {
            templates.value.push({ ...body, id: data.id });
            showToast('Šablóna bola uložená.');
            showSaveTemplate.value = false;
            templateName.value = '';
        } else {
            showToast('Chyba pri ukladaní šablóny.', 'error');
        }
    } catch (_) { showToast('Chyba pri ukladaní šablóny.', 'error'); }
    savingTemplate.value = false;
}

// ── Campaign push modal ───────────────────────────────────────────────────────

const showCampaignModal  = ref(false);
const campaignModalTitle = ref('Nový push');
const campaignFilters    = ref([newFilterRow()]);

const pushForm = useForm({
    target_type:      'all',
    target_store_id:  null,
    target_tester_id: null,
    target_segment:   '',
    target_filters:   null,
    condition:        'none',
    push_url:         '',
    send_mode:        'now',
    send_at:          '',
});

const pushUrlPath = ref('');
watch(pushUrlPath, v => { pushForm.push_url = v ? SCHEME + v : ''; });
watch(() => pushForm.target_type, () => { campaignSegmentQuery.value = ''; showCampaignSegmentDrop.value = false; });

function openCampaignModal() {
    if (!selectedCampaignId.value) return;
    campaignModalTitle.value = 'Nový push';
    pushForm.reset();
    pushUrlPath.value = `ucet/centrum-upozorneni/${selectedCampaignId.value}`;
    campaignSegmentQuery.value = '';
    campaignFilters.value = [newFilterRow()];
    showCampaignModal.value = true;
}

async function duplicateCampaignPush(push) {
    try {
        const res  = await fetch(route('campaign-pushes.duplicate', { campaignId: push.notification_id, pushId: push.id }));
        const data = await res.json();
        campaignModalTitle.value = 'Duplikát push';
        pushForm.reset();
        pushForm.target_type      = data.target_type      ?? 'all';
        pushForm.target_store_id  = data.target_store_id  ?? null;
        pushForm.target_tester_id = data.target_tester_id ?? null;
        pushForm.target_segment   = data.target_segment   ?? '';
        pushForm.condition        = data.condition        ?? 'none';
        pushForm.send_mode        = 'now';
        campaignSegmentQuery.value = data.target_segment ?? '';
        campaignFilters.value = data.target_filters?.length ? [...data.target_filters] : [newFilterRow()];
        pushUrlPath.value = data.push_url ? data.push_url.replace(SCHEME, '') : '';
        showCampaignModal.value = true;
    } catch (_) { showToast('Nepodarilo sa načítať duplikát.', 'error'); }
}

function selectCampaignSegment(seg) {
    pushForm.target_segment = seg;
    campaignSegmentQuery.value = seg;
    showCampaignSegmentDrop.value = false;
}

function doSubmitCampaignPush() {
    if (pushForm.target_type === 'filtered') pushForm.target_filters = campaignFilters.value;
    pushForm.post(route('campaign-pushes.store', selectedCampaignId.value), {
        onSuccess: () => { showCampaignModal.value = false; showToast(pushForm.send_mode === 'now' ? 'Notifikácia bola zaradená do fronty.' : 'Notifikácia bola naplánovaná.'); },
        onError: () => showToast('Skontrolujte chyby vo formulári.', 'error'),
    });
}

function submitCampaignPush() {
    if (pushForm.target_type === 'all' && pushForm.send_mode === 'now') {
        showConfirmDialog(appStats.value?.messageable ?? 0, doSubmitCampaignPush);
    } else {
        doSubmitCampaignPush();
    }
}

function cancelCampaignPush(push) {
    if (!confirm('Zrušiť túto push notifikáciu?')) return;
    router.delete(route('campaign-pushes.destroy', { campaignId: push.notification_id, pushId: push.id }), {
        onSuccess: () => showToast('Push notifikácia bola zrušená.'),
    });
}

function retryCampaignPush(push) {
    router.post(route('campaign-pushes.retry', { campaignId: push.notification_id, pushId: push.id }), {}, {
        onSuccess: () => showToast('Notifikácia bola znova zaradená do fronty.'),
        onError:   () => showToast('Chyba pri opakovaní odoslania.', 'error'),
    });
}

// ── Standalone push modal ─────────────────────────────────────────────────────

const showStandaloneModal  = ref(false);
const standaloneModalTitle = ref('Nová samostatná notifikácia');
const standaloneUrlPath    = ref('');
const standaloneFilters    = ref([newFilterRow()]);
const showTemplateDropdown = ref(false);

const standaloneForm = useForm({
    title:            '',
    message:          '',
    subtitle:         '',
    image:            '',
    push_url:         '',
    target_type:      'all',
    target_store_id:  null,
    target_tester_id: null,
    target_segment:   '',
    target_filters:   null,
    send_mode:        'now',
    send_at:          '',
    ttl:              '',
    priority:         10,
    collapse_id:      '',
    ios_badge_type:   '',
    ios_badge_count:  '',
});

watch(standaloneUrlPath, v => { standaloneForm.push_url = v ? SCHEME + v : ''; });
watch(() => standaloneForm.target_type, () => { standaloneSegmentQuery.value = ''; showStandaloneSegmentDrop.value = false; });

function openStandaloneModal() {
    standaloneModalTitle.value = 'Nová samostatná notifikácia';
    standaloneForm.reset();
    standaloneUrlPath.value = '';
    standaloneSegmentQuery.value = '';
    standaloneFilters.value = [newFilterRow()];
    showSaveTemplate.value = false;
    showTemplateDropdown.value = false;
    showStandaloneModal.value = true;
}

async function duplicateStandalonePush(push) {
    try {
        const res  = await fetch(route('standalone-pushes.duplicate', push.id));
        const data = await res.json();
        standaloneModalTitle.value = 'Duplikát notifikácie';
        standaloneForm.reset();
        standaloneForm.title            = data.title            ?? '';
        standaloneForm.message          = data.message          ?? '';
        standaloneForm.subtitle         = data.subtitle         ?? '';
        standaloneForm.image            = data.image            ?? '';
        standaloneForm.target_type      = data.target_type      ?? 'all';
        standaloneForm.target_store_id  = data.target_store_id  ?? null;
        standaloneForm.target_tester_id = data.target_tester_id ?? null;
        standaloneForm.target_segment   = data.target_segment   ?? '';
        standaloneForm.send_mode        = 'now';
        standaloneForm.ttl              = data.ttl              ?? '';
        standaloneForm.priority         = data.priority         ?? 10;
        standaloneForm.collapse_id      = data.collapse_id      ?? '';
        standaloneForm.ios_badge_type   = data.ios_badge_type   ?? '';
        standaloneForm.ios_badge_count  = data.ios_badge_count  ?? '';
        standaloneSegmentQuery.value    = data.target_segment   ?? '';
        standaloneFilters.value = data.target_filters?.length ? [...data.target_filters] : [newFilterRow()];
        standaloneUrlPath.value = data.push_url ? data.push_url.replace(SCHEME, '') : '';
        showStandaloneModal.value = true;
    } catch (_) { showToast('Nepodarilo sa načítať duplikát.', 'error'); }
}

function selectStandaloneSegment(seg) {
    standaloneForm.target_segment = seg;
    standaloneSegmentQuery.value = seg;
    showStandaloneSegmentDrop.value = false;
}

function doSubmitStandalone() {
    if (standaloneForm.target_type === 'filtered') standaloneForm.target_filters = standaloneFilters.value;
    standaloneForm.post(route('standalone-pushes.store'), {
        onSuccess: () => { showStandaloneModal.value = false; showToast(standaloneForm.send_mode === 'now' ? 'Notifikácia bola zaradená do fronty.' : 'Notifikácia bola naplánovaná.'); },
        onError: () => showToast('Skontrolujte chyby vo formulári.', 'error'),
    });
}

function submitStandalone() {
    if (standaloneForm.target_type === 'all' && standaloneForm.send_mode === 'now') {
        showConfirmDialog(appStats.value?.messageable ?? 0, doSubmitStandalone);
    } else {
        doSubmitStandalone();
    }
}

function cancelStandalonePush(push) {
    if (!confirm('Zrušiť túto push notifikáciu?')) return;
    router.delete(route('standalone-pushes.destroy', push.id), {
        onSuccess: () => showToast('Push notifikácia bola zrušená.'),
    });
}

function retryStandalonePush(push) {
    router.post(route('standalone-pushes.retry', push.id), {}, {
        onSuccess: () => showToast('Notifikácia bola znova zaradená do fronty.'),
        onError:   () => showToast('Chyba pri opakovaní odoslania.', 'error'),
    });
}

// ── Stats + auto-refresh ──────────────────────────────────────────────────────

const pushStats = ref({});

async function loadStats(push, isStandalone = false) {
    if (!push.onesignal_id) return;
    const key = isStandalone ? `s_${push.id}` : push.id;
    try {
        const url = isStandalone
            ? route('standalone-pushes.stats', push.id)
            : route('campaign-pushes.stats', { campaignId: push.notification_id, pushId: push.id });
        const res  = await fetch(url);
        pushStats.value[key] = await res.json();
    } catch (_) {}
}

let statsInterval = null;

function refreshPendingStats() {
    props.pushes?.forEach(p => { if (p.onesignal_id && p.status === 'pending') loadStats(p, false); });
    props.standalone?.forEach(p => { if (p.onesignal_id && p.status === 'pending') loadStats(p, true); });
}

// ── Scheduled timeline ────────────────────────────────────────────────────────

const scheduledPushes   = ref([]);
const scheduledLoading  = ref(false);

async function loadScheduled() {
    scheduledLoading.value = true;
    try {
        const res = await fetch(route('notifications.scheduled'));
        scheduledPushes.value = await res.json();
    } catch (_) {}
    scheduledLoading.value = false;
}

watch(activeTab, tab => {
    if (tab === 'history'   && !history.value.length)          loadHistory();
    if (tab === 'scheduled' && !scheduledPushes.value.length)  loadScheduled();
});

// ── OneSignal History ─────────────────────────────────────────────────────────

const history        = ref([]);
const historyLoading = ref(false);
const historyTotal   = ref(0);
const historyOffset  = ref(0);
const HISTORY_LIMIT  = 50;

async function loadHistory(append = false) {
    historyLoading.value = true;
    try {
        const res  = await fetch(`${route('notifications.history')}?offset=${historyOffset.value}`);
        const data = await res.json();
        const items = data.notifications ?? data ?? [];
        historyTotal.value = data.total ?? items.length;
        history.value = append ? [...history.value, ...items] : items;
    } catch (_) {}
    historyLoading.value = false;
}

function loadMoreHistory() { historyOffset.value += HISTORY_LIMIT; loadHistory(true); }
function refreshHistory()  { historyOffset.value = 0; history.value = []; loadHistory(); }

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    loadAppStats();
    loadSegments();
    loadTemplates();
    setTimeout(() => {
        props.pushes?.forEach(p => { if (p.onesignal_id && p.status === 'pending') loadStats(p, false); });
        props.standalone?.forEach(p => { if (p.onesignal_id && p.status === 'pending') loadStats(p, true); });
    }, 2000);
    statsInterval = setInterval(refreshPendingStats, 30000);
});

onUnmounted(() => { if (statsInterval) clearInterval(statsInterval); });
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Notifikácie</h2>
                <div class="flex gap-2">
                    <button v-if="activeTab === 'campaigns'"
                        @click="openCampaignModal" :disabled="!selectedCampaignId"
                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Nový push
                    </button>
                    <button v-else-if="activeTab === 'standalone'"
                        @click="openStandaloneModal"
                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Nová notifikácia
                    </button>
                    <button v-else-if="activeTab === 'scheduled'" @click="loadScheduled"
                        class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Obnoviť
                    </button>
                </div>
            </div>
        </template>

        <!-- Toast -->
        <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200" leave-to-class="opacity-0">
            <div v-if="toast.show" :class="toast.type === 'error' ? 'bg-red-600' : 'bg-green-600'"
                 class="fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                {{ toast.message }}
            </div>
        </Transition>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- ── Subscriber stats panel ── -->
                <div class="rounded-xl bg-white shadow p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">OneSignal – subscriberi</p>
                        <button @click="loadAppStats" class="text-gray-400 hover:text-indigo-600 transition-colors">
                            <svg class="h-4 w-4" :class="{'animate-spin': appStatsLoading}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </button>
                    </div>
                    <div v-if="appStatsLoading && !appStats" class="flex gap-6">
                        <div v-for="i in 3" :key="i" class="h-12 w-28 rounded-lg bg-gray-100 animate-pulse"/>
                    </div>
                    <div v-else-if="appStats" class="flex flex-wrap gap-6">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-900">{{ appStats.total?.toLocaleString('sk') ?? '—' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">Celkom zariadení</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-green-600">{{ appStats.messageable?.toLocaleString('sk') ?? '—' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">Aktívnych (messageable)</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-purple-600">{{ appStats.testers?.toLocaleString('sk') ?? '—' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">Testerov</p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">Nepodarilo sa načítať štatistiky.</p>
                </div>

                <!-- ── Tabs ── -->
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex gap-6">
                        <button v-for="tab in [
                            { id: 'campaigns',  label: 'Kampane' },
                            { id: 'standalone', label: 'Samostatné notifikácie' },
                            { id: 'scheduled',  label: 'Naplánované' },
                            { id: 'history',    label: 'História OneSignal' },
                        ]" :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="activeTab === tab.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap border-b-2 pb-3 text-sm font-medium transition-colors">
                            {{ tab.label }}
                            <span v-if="tab.id === 'scheduled' && scheduledPushes.length" class="ml-1.5 inline-flex items-center justify-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">{{ scheduledPushes.length }}</span>
                        </button>
                    </nav>
                </div>

                <!-- ══════════════════════════════════════════════════════════ -->
                <!-- TAB: Kampane -->
                <!-- ══════════════════════════════════════════════════════════ -->
                <div v-if="activeTab === 'campaigns'" class="flex gap-6 items-start">

                    <!-- Sidebar -->
                    <div class="w-72 shrink-0">
                        <div class="rounded-xl bg-white shadow overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Kampane</p>
                            </div>
                            <div v-if="!campaigns?.length" class="px-4 py-6 text-center text-sm text-gray-400">Žiadne kampane</div>
                            <ul v-else class="divide-y divide-gray-50">
                                <li v-for="c in campaigns" :key="c.id">
                                    <button @click="selectCampaign(c.id)"
                                        :class="selectedCampaignId === c.id ? 'bg-indigo-50 border-l-2 border-indigo-500' : 'hover:bg-gray-50 border-l-2 border-transparent'"
                                        class="w-full text-left px-4 py-3 transition-colors">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-sm font-medium text-gray-900 truncate">{{ c.name }}</span>
                                            <span v-if="c.pushes_count" class="shrink-0 inline-flex items-center justify-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ c.pushes_count }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span :class="campaignStatusClass[c.status]" class="rounded-full px-1.5 py-0.5 text-xs font-medium">{{ campaignStatusLabel[c.status] }}</span>
                                            <span class="text-xs text-gray-400 truncate">{{ c.title }}</span>
                                        </div>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <p class="mt-3 text-center"><a :href="route('campaigns.index')" class="text-xs text-gray-400 hover:text-indigo-600 transition-colors">Spravovať kampane →</a></p>
                    </div>

                    <!-- Main area -->
                    <div class="flex-1 min-w-0">
                        <div v-if="!selectedCampaignId" class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-white py-20 text-center">
                            <svg class="mb-4 h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <p class="text-sm font-medium text-gray-500">Vyberte kampaň</p>
                        </div>

                        <template v-else>
                            <div class="mb-4 flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">{{ selectedCampaign?.name }}</h3>
                                    <p class="text-sm text-gray-500">{{ selectedCampaign?.title }}</p>
                                    <!-- Aggregate stats -->
                                    <div v-if="campaignAggregate" class="mt-2 flex items-center gap-5">
                                        <span class="text-xs text-gray-500">Spolu: <b class="text-gray-800">{{ campaignAggregate.delivered }}</b> doručených · <b class="text-gray-800">{{ campaignAggregate.converted }}</b> kliknutí · <b class="text-gray-800">{{ campaignAggregate.failed }}</b> neúspešných</span>
                                    </div>
                                </div>
                                <a :href="route('campaigns.edit', selectedCampaignId)" class="shrink-0 text-xs text-gray-400 hover:text-indigo-600 transition-colors">Upraviť obsah →</a>
                            </div>

                            <div v-if="!filteredPushes.length" class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-white py-16 text-center">
                                <p class="text-sm font-medium text-gray-500 mb-4">Žiadne push notifikácie</p>
                                <button @click="openCampaignModal" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    Pridať prvý push
                                </button>
                            </div>

                            <div v-else class="space-y-3">
                                <div v-for="push in filteredPushes" :key="push.id" class="rounded-xl bg-white shadow p-5">
                                    <!-- Error banner -->
                                    <div v-if="push.status === 'error'" class="mb-3 flex items-start gap-2 rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                                        <svg class="h-4 w-4 text-red-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-semibold text-red-700">Chyba odoslania{{ push.retry_count ? ` (pokus ${push.retry_count})` : '' }}</p>
                                            <p v-if="push.send_error" class="text-xs text-red-600 mt-0.5 break-all">{{ push.send_error }}</p>
                                        </div>
                                        <button @click="retryCampaignPush(push)" class="shrink-0 rounded-md bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700 transition-colors">Skúsiť znova</button>
                                    </div>

                                    <div class="flex items-start justify-between gap-4">
                                        <div class="space-y-2 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-sm font-semibold text-gray-900">{{ targetLabel[push.target_type] }}</span>
                                                <span v-if="push.target_type === 'tester'" class="rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-700">{{ testerName(push.target_tester_id) }}</span>
                                                <span v-if="push.target_type === 'store'" class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">{{ storeName(push.target_store_id) }}</span>
                                                <span v-if="push.target_type === 'segment'" class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700">{{ push.target_segment }}</span>
                                                <template v-if="push.target_type === 'filtered' && push.target_filters">
                                                    <span v-for="f in push.target_filters" :key="f.key" class="rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-700 font-mono">{{ f.key }} {{ f.relation }} {{ f.value }}</span>
                                                </template>
                                                <span v-if="push.condition === 'unread_only'" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">len neprečítaní</span>
                                            </div>
                                            <p class="text-sm text-gray-500">
                                                <span v-if="push.send_at"><span class="font-medium text-gray-700">Naplánovaná</span> na {{ formatDate(push.send_at) }}</span>
                                                <span v-else><span class="font-medium text-gray-700">Odoslaná</span> {{ formatDate(push.created_at) }}</span>
                                            </p>
                                            <div v-if="pushStats[push.id]" class="flex items-center gap-6 pt-1">
                                                <div class="text-center"><p class="text-lg font-bold text-gray-900">{{ pushStats[push.id].delivered ?? '—' }}</p><p class="text-xs text-gray-400">doručených</p></div>
                                                <div class="text-center"><p class="text-lg font-bold text-gray-900">{{ pushStats[push.id].converted ?? '—' }}</p><p class="text-xs text-gray-400">kliknutí</p></div>
                                                <div class="text-center"><p class="text-lg font-bold text-gray-900">{{ pushStats[push.id].failed ?? '—' }}</p><p class="text-xs text-gray-400">neúspešných</p></div>
                                                <button @click="loadStats(push)" class="ml-2 inline-flex items-center gap-1 text-xs text-indigo-500 hover:text-indigo-700 transition-colors">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                    Obnoviť
                                                </button>
                                            </div>
                                            <div v-else-if="push.onesignal_id && push.status !== 'error'">
                                                <button @click="loadStats(push)" class="text-xs text-indigo-500 hover:text-indigo-700 transition-colors">Načítať štatistiky</button>
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-end gap-2 shrink-0">
                                            <span :class="pushStatusClass[push.status]" class="rounded-full px-3 py-1 text-xs font-medium">{{ pushStatusLabel[push.status] }}</span>
                                            <button @click="duplicateCampaignPush(push)" class="text-xs text-indigo-500 hover:text-indigo-700 transition-colors">Duplikovať</button>
                                            <button v-if="push.status === 'pending'" @click="cancelCampaignPush(push)" class="text-xs text-red-500 hover:text-red-700 transition-colors">Zrušiť</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════ -->
                <!-- TAB: Samostatné notifikácie -->
                <!-- ══════════════════════════════════════════════════════════ -->
                <div v-else-if="activeTab === 'standalone'">
                    <div v-if="!standalone?.length" class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-white py-20 text-center">
                        <svg class="mb-4 h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <p class="text-sm font-medium text-gray-500 mb-4">Žiadne samostatné notifikácie</p>
                        <button @click="openStandaloneModal" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Vytvoriť prvú notifikáciu
                        </button>
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="push in standalone" :key="push.id" class="rounded-xl bg-white shadow p-5">
                            <!-- Error banner -->
                            <div v-if="push.status === 'error'" class="mb-3 flex items-start gap-2 rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                                <svg class="h-4 w-4 text-red-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-red-700">Chyba odoslania{{ push.retry_count ? ` (pokus ${push.retry_count})` : '' }}</p>
                                    <p v-if="push.send_error" class="text-xs text-red-600 mt-0.5 break-all">{{ push.send_error }}</p>
                                </div>
                                <button @click="retryStandalonePush(push)" class="shrink-0 rounded-md bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700 transition-colors">Skúsiť znova</button>
                            </div>

                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-2 min-w-0 flex-1">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ push.title }}</p>
                                        <p v-if="push.subtitle" class="text-xs text-gray-500 italic">{{ push.subtitle }}</p>
                                        <p class="text-sm text-gray-600 mt-0.5">{{ push.message }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ targetLabel[push.target_type] }}</span>
                                        <span v-if="push.target_type === 'tester'" class="rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-700">{{ testerName(push.target_tester_id) }}</span>
                                        <span v-if="push.target_type === 'store'" class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">{{ storeName(push.target_store_id) }}</span>
                                        <span v-if="push.target_type === 'segment'" class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700">{{ push.target_segment }}</span>
                                        <template v-if="push.target_type === 'filtered' && push.target_filters">
                                            <span v-for="f in push.target_filters" :key="f.key" class="rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-700 font-mono">{{ f.key }} {{ f.relation }} {{ f.value }}</span>
                                        </template>
                                        <span v-if="push.priority === 5" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">priorita: normálna</span>
                                        <span v-if="push.collapse_id" class="rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-700">collapse: {{ push.collapse_id }}</span>
                                        <span v-if="push.ios_badge_type && push.ios_badge_type !== 'None'" class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700">badge {{ push.ios_badge_type }} {{ push.ios_badge_count }}</span>
                                        <a v-if="push.push_url" :href="push.push_url" target="_blank" class="rounded-full bg-teal-100 px-2 py-0.5 text-xs text-teal-700 font-mono truncate max-w-xs hover:bg-teal-200 transition-colors">{{ push.push_url }}</a>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        <span v-if="push.send_at"><span class="font-medium text-gray-700">Naplánovaná</span> na {{ formatDate(push.send_at) }}</span>
                                        <span v-else><span class="font-medium text-gray-700">Odoslaná</span> {{ formatDate(push.created_at) }}</span>
                                    </p>
                                    <div v-if="pushStats[`s_${push.id}`]" class="flex items-center gap-6 pt-1">
                                        <div class="text-center"><p class="text-lg font-bold text-gray-900">{{ pushStats[`s_${push.id}`].delivered ?? '—' }}</p><p class="text-xs text-gray-400">doručených</p></div>
                                        <div class="text-center"><p class="text-lg font-bold text-gray-900">{{ pushStats[`s_${push.id}`].converted ?? '—' }}</p><p class="text-xs text-gray-400">kliknutí</p></div>
                                        <div class="text-center"><p class="text-lg font-bold text-gray-900">{{ pushStats[`s_${push.id}`].failed ?? '—' }}</p><p class="text-xs text-gray-400">neúspešných</p></div>
                                        <button @click="loadStats(push, true)" class="ml-2 inline-flex items-center gap-1 text-xs text-indigo-500 hover:text-indigo-700 transition-colors">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            Obnoviť
                                        </button>
                                    </div>
                                    <div v-else-if="push.onesignal_id && push.status !== 'error'">
                                        <button @click="loadStats(push, true)" class="text-xs text-indigo-500 hover:text-indigo-700 transition-colors">Načítať štatistiky</button>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-2 shrink-0">
                                    <span :class="pushStatusClass[push.status]" class="rounded-full px-3 py-1 text-xs font-medium">{{ pushStatusLabel[push.status] }}</span>
                                    <button @click="duplicateStandalonePush(push)" class="text-xs text-indigo-500 hover:text-indigo-700 transition-colors">Duplikovať</button>
                                    <button v-if="push.status === 'pending'" @click="cancelStandalonePush(push)" class="text-xs text-red-500 hover:text-red-700 transition-colors">Zrušiť</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════ -->
                <!-- TAB: Naplánované -->
                <!-- ══════════════════════════════════════════════════════════ -->
                <div v-else-if="activeTab === 'scheduled'">
                    <div v-if="scheduledLoading" class="space-y-3">
                        <div v-for="i in 3" :key="i" class="h-20 rounded-xl bg-white shadow animate-pulse"/>
                    </div>
                    <div v-else-if="!scheduledPushes.length" class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-white py-20 text-center">
                        <svg class="mb-4 h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="text-sm font-medium text-gray-500">Žiadne naplánované notifikácie</p>
                        <p class="mt-1 text-xs text-gray-400">Všetky budúce push notifikácie sa zobrazia tu.</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="push in scheduledPushes" :key="`${push.type}_${push.id}`"
                             class="rounded-xl bg-white shadow p-5 flex items-center gap-5">
                            <!-- Time -->
                            <div class="shrink-0 text-center w-20">
                                <p class="text-sm font-bold text-gray-900">{{ new Date(push.send_at).toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' }) }}</p>
                                <p class="text-xs text-gray-400">{{ new Date(push.send_at).toLocaleDateString('sk-SK', { day: '2-digit', month: '2-digit' }) }}</p>
                            </div>
                            <div class="w-px h-10 bg-gray-100 shrink-0"/>
                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="push.type === 'campaign' ? 'bg-indigo-100 text-indigo-700' : 'bg-teal-100 text-teal-700'">
                                        {{ push.type === 'campaign' ? 'Kampaň' : 'Samostatná' }}
                                    </span>
                                    <span class="text-sm font-semibold text-gray-900 truncate">
                                        {{ push.type === 'campaign' ? push.campaign_name : push.title }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    <span class="text-xs text-gray-500">{{ targetLabel[push.target_type] }}</span>
                                    <span v-if="push.target_type === 'tester'" class="text-xs text-gray-400">– {{ testerName(push.target_tester_id) }}</span>
                                    <span v-if="push.target_type === 'store'" class="text-xs text-gray-400">– {{ storeName(push.target_store_id) }}</span>
                                    <span v-if="push.target_segment" class="text-xs text-gray-400">– {{ push.target_segment }}</span>
                                </div>
                            </div>
                            <!-- Countdown -->
                            <div class="shrink-0 text-right">
                                <p class="text-xs text-amber-600 font-medium">
                                    {{ (() => { const ms = new Date(push.send_at) - Date.now(); const h = Math.floor(ms/3600000); const m = Math.floor((ms%3600000)/60000); return ms > 0 ? (h > 0 ? `za ${h}h ${m}m` : `za ${m}m`) : 'čoskoro'; })() }}
                                </p>
                                <span class="text-xs text-blue-700 bg-blue-100 rounded-full px-2 py-0.5">Čaká</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════ -->
                <!-- TAB: História OneSignal -->
                <!-- ══════════════════════════════════════════════════════════ -->
                <div v-else-if="activeTab === 'history'">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-sm text-gray-500">Posledných {{ history.length }} z {{ historyTotal }} notifikácií z OneSignal</p>
                        <button @click="refreshHistory" class="inline-flex items-center gap-1 text-xs text-indigo-500 hover:text-indigo-700 transition-colors">
                            <svg class="h-3.5 w-3.5" :class="{'animate-spin': historyLoading}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Obnoviť
                        </button>
                    </div>
                    <div v-if="historyLoading && !history.length" class="space-y-3">
                        <div v-for="i in 5" :key="i" class="h-24 rounded-xl bg-white shadow animate-pulse"/>
                    </div>
                    <div v-else-if="!history.length && !historyLoading" class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-white py-20 text-center">
                        <p class="text-sm text-gray-500">Žiadna história</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="n in history" :key="n.id" class="rounded-xl bg-white shadow p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-900">{{ n.headings }}</p>
                                    <p class="text-sm text-gray-500 mt-0.5 line-clamp-2">{{ n.contents }}</p>
                                    <div class="flex items-center gap-6 mt-3">
                                        <div class="text-center"><p class="text-base font-bold text-gray-900">{{ n.delivered ?? '—' }}</p><p class="text-xs text-gray-400">doručených</p></div>
                                        <div class="text-center"><p class="text-base font-bold text-gray-900">{{ n.converted ?? '—' }}</p><p class="text-xs text-gray-400">kliknutí</p></div>
                                        <div class="text-center"><p class="text-base font-bold text-gray-900">{{ n.failed ?? '—' }}</p><p class="text-xs text-gray-400">neúspešných</p></div>
                                        <div class="text-center"><p class="text-base font-bold text-gray-900">{{ n.recipients ?? '—' }}</p><p class="text-xs text-gray-400">príjemcov</p></div>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right space-y-1">
                                    <span :class="n.status === 'sent' ? 'bg-green-100 text-green-700' : n.status === 'cancelled' ? 'bg-gray-100 text-gray-500' : 'bg-blue-100 text-blue-700'" class="rounded-full px-3 py-1 text-xs font-medium block">{{ n.status === 'sent' ? 'Odoslaná' : n.status === 'cancelled' ? 'Zrušená' : 'Čaká' }}</span>
                                    <p class="text-xs text-gray-400">{{ formatDate(n.completed_at ?? n.send_after) }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-if="history.length < historyTotal" class="text-center pt-2">
                            <button @click="loadMoreHistory" :disabled="historyLoading" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50 transition-colors">
                                {{ historyLoading ? 'Načítava…' : 'Načítať ďalšie' }}
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- Confirmation dialog -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-to-class="opacity-0">
                <div v-if="confirmDialog.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="confirmDialog.show = false">
                    <div class="w-full max-w-sm rounded-xl bg-white shadow-xl p-6 text-center">
                        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                            <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900 mb-1">Odoslať všetkým?</h3>
                        <p class="text-sm text-gray-500 mb-6">
                            Táto notifikácia bude doručená
                            <span class="font-semibold text-gray-800">{{ confirmDialog.count.toLocaleString('sk') }}</span>
                            aktívnym zariadeniam. Tento krok nie je možné vrátiť.
                        </p>
                        <div class="flex gap-3">
                            <button @click="confirmDialog.show = false" class="flex-1 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">Zrušiť</button>
                            <button @click="confirmSend" class="flex-1 rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 transition-colors">Odoslať</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- Campaign push modal -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-to-class="opacity-0">
                <div v-if="showCampaignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showCampaignModal = false">
                    <div class="w-full max-w-md rounded-xl bg-white shadow-xl flex flex-col max-h-[90vh]">
                        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 shrink-0">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">{{ campaignModalTitle }}</h3>
                                <p class="text-xs text-gray-400 mt-0.5">{{ selectedCampaign?.name }}</p>
                            </div>
                            <button @click="showCampaignModal = false" class="text-gray-400 hover:text-gray-600"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>

                        <div class="overflow-y-auto p-6 space-y-4">
                            <!-- Target type -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-700">Komu odoslať</label>
                                    <span v-if="recipientPreview(pushForm.target_type)" class="text-xs text-indigo-600 font-medium bg-indigo-50 px-2 py-0.5 rounded-full">~{{ recipientPreview(pushForm.target_type) }} príjemcov</span>
                                </div>
                                <div class="space-y-1.5">
                                    <label v-for="opt in [
                                        { value: 'all', label: 'Všetci používatelia' },
                                        { value: 'testers', label: 'Všetci testeri' },
                                        { value: 'tester', label: 'Konkrétny tester' },
                                        { value: 'store', label: 'Zákazníci predajne' },
                                        { value: 'segment', label: 'Segment OneSignal' },
                                        { value: 'filtered', label: 'Vlastné filtre (tagy)' },
                                    ]" :key="opt.value" class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="pushForm.target_type" type="radio" :value="opt.value" class="text-indigo-600"/>
                                        <span class="text-sm text-gray-700">{{ opt.label }}</span>
                                    </label>
                                </div>
                            </div>

                            <div v-if="pushForm.target_type === 'tester'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tester <span class="text-red-500">*</span></label>
                                <select v-model="pushForm.target_tester_id" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                    <option :value="null" disabled>Vybrať testera...</option>
                                    <option v-for="t in testers" :key="t.customer_id" :value="t.customer_id">{{ t.note || 'ID ' + t.customer_id }}</option>
                                </select>
                            </div>

                            <div v-if="pushForm.target_type === 'store'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Predajňa <span class="text-red-500">*</span></label>
                                <select v-model="pushForm.target_store_id" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                    <option :value="null" disabled>Vybrať predajňu...</option>
                                    <option v-for="s in stores" :key="s.store_id" :value="s.store_id">{{ s.name }}</option>
                                </select>
                            </div>

                            <div v-if="pushForm.target_type === 'segment'" class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Segment <span class="text-red-500">*</span></label>
                                <input v-model="campaignSegmentQuery" @focus="showCampaignSegmentDrop = true" @blur="setTimeout(() => showCampaignSegmentDrop = false, 150)"
                                    type="text" placeholder="Hľadať segment..."
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                <div v-if="showCampaignSegmentDrop && filteredSegmentsCampaign.length" class="absolute z-10 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg max-h-48 overflow-y-auto">
                                    <button v-for="seg in filteredSegmentsCampaign" :key="seg" @mousedown.prevent="selectCampaignSegment(seg)" class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 transition-colors">{{ seg }}</button>
                                </div>
                            </div>

                            <div v-if="pushForm.target_type === 'filtered'">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Filtre (tagy)</label>
                                <div class="space-y-2">
                                    <div v-for="(f, i) in campaignFilters" :key="i" class="flex items-center gap-2">
                                        <input v-model="f.key" type="text" placeholder="kľúč" class="flex-1 rounded-lg border border-gray-200 px-2 py-1.5 text-xs focus:border-indigo-400 focus:outline-none"/>
                                        <select v-model="f.relation" class="rounded-lg border border-gray-200 px-2 py-1.5 text-xs focus:border-indigo-400 focus:outline-none">
                                            <option v-for="r in relationOptions" :key="r" :value="r">{{ r }}</option>
                                        </select>
                                        <input v-model="f.value" type="text" placeholder="hodnota" :disabled="f.relation === 'exists' || f.relation === 'not_exists'" class="flex-1 rounded-lg border border-gray-200 px-2 py-1.5 text-xs disabled:bg-gray-50 focus:border-indigo-400 focus:outline-none"/>
                                        <button @click="campaignFilters.splice(i,1)" class="text-gray-400 hover:text-red-500 transition-colors"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                    </div>
                                </div>
                                <button @click="campaignFilters.push(newFilterRow())" class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 transition-colors">+ Pridať filter</button>
                            </div>

                            <div v-if="pushForm.target_type === 'all' || pushForm.target_type === 'store'">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="pushForm.condition" type="checkbox" true-value="unread_only" false-value="none" class="rounded text-indigo-600"/>
                                    <span class="text-sm text-gray-700">Poslať len tým, ktorí si kampaň ešte neprečítali</span>
                                </label>
                            </div>

                            <!-- Push URL -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-sm font-medium text-gray-700">Kam smeruje notifikácia</label>
                                    <button type="button" @click="useCampaignPushLink" class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 transition-colors">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                        Otvoriť túto kampaň
                                    </button>
                                </div>
                                <div class="flex rounded-lg border border-gray-200 overflow-hidden focus-within:border-indigo-400 focus-within:ring-1 focus-within:ring-indigo-400">
                                    <span class="flex items-center bg-gray-100 px-3 text-xs font-mono text-gray-500 border-r border-gray-200 whitespace-nowrap select-none">titiapp://</span>
                                    <input v-model="pushUrlPath" type="text" placeholder="titi-predajne" class="flex-1 px-3 py-2 text-sm font-mono focus:outline-none"/>
                                </div>
                            </div>

                            <!-- Send mode -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Kedy odoslať</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer"><input v-model="pushForm.send_mode" type="radio" value="now" class="text-indigo-600"/><span class="text-sm text-gray-700">Ihneď</span></label>
                                    <label class="flex items-center gap-2 cursor-pointer"><input v-model="pushForm.send_mode" type="radio" value="scheduled" class="text-indigo-600"/><span class="text-sm text-gray-700">Naplánovať</span></label>
                                </div>
                            </div>
                            <div v-if="pushForm.send_mode === 'scheduled'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Dátum a čas <span class="text-red-500">*</span></label>
                                <input v-model="pushForm.send_at" type="datetime-local" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                <p v-if="pushForm.errors.send_at" class="mt-1 text-xs text-red-500">{{ pushForm.errors.send_at }}</p>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4 shrink-0">
                            <button @click="showCampaignModal = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Zrušiť</button>
                            <button @click="submitCampaignPush" :disabled="pushForm.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                {{ pushForm.send_mode === 'now' ? 'Odoslať' : 'Naplánovať' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- Standalone push modal -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-to-class="opacity-0">
                <div v-if="showStandaloneModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showStandaloneModal = false">
                    <div class="w-full max-w-lg rounded-xl bg-white shadow-xl flex flex-col max-h-[90vh]">
                        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 shrink-0">
                            <h3 class="text-base font-semibold text-gray-900">{{ standaloneModalTitle }}</h3>
                            <button @click="showStandaloneModal = false" class="text-gray-400 hover:text-gray-600"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>

                        <div class="overflow-y-auto p-6 space-y-5">

                            <!-- Templates -->
                            <div class="rounded-lg border border-gray-200 p-3 bg-gray-50">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Šablóny</p>
                                    <button @click="showSaveTemplate = !showSaveTemplate" class="text-xs text-indigo-600 hover:text-indigo-800 transition-colors">
                                        {{ showSaveTemplate ? 'Zrušiť uloženie' : 'Uložiť aktuálne ako šablónu' }}
                                    </button>
                                </div>
                                <div v-if="showSaveTemplate" class="flex gap-2 mb-2">
                                    <input v-model="templateName" type="text" placeholder="Názov šablóny..." class="flex-1 rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                    <button @click="saveTemplate" :disabled="savingTemplate || !templateName.trim()" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors">Uložiť</button>
                                </div>
                                <div v-if="templatesLoading" class="h-6 bg-gray-200 rounded animate-pulse"/>
                                <div v-else-if="templates.length" class="flex flex-wrap gap-1.5">
                                    <div v-for="t in templates" :key="t.id" class="flex items-center gap-1 rounded-full bg-white border border-gray-200 px-2 py-0.5">
                                        <button @click="applyTemplate(t)" class="text-xs text-gray-700 hover:text-indigo-600 transition-colors">{{ t.name }}</button>
                                        <button @click="deleteTemplate(t.id)" class="text-gray-300 hover:text-red-500 transition-colors"><svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                    </div>
                                </div>
                                <p v-else class="text-xs text-gray-400">Žiadne uložené šablóny.</p>
                            </div>

                            <hr class="border-gray-100"/>

                            <!-- Obsah -->
                            <div class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Obsah notifikácie</p>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nadpis <span class="text-red-500">*</span></label>
                                    <input v-model="standaloneForm.title" type="text" placeholder="napr. Nová kolekcia je tu!" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                    <p v-if="standaloneForm.errors.title" class="mt-1 text-xs text-red-500">{{ standaloneForm.errors.title }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Správa <span class="text-red-500">*</span></label>
                                    <textarea v-model="standaloneForm.message" rows="3" placeholder="Text notifikácie..." class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-none"/>
                                    <p v-if="standaloneForm.errors.message" class="mt-1 text-xs text-red-500">{{ standaloneForm.errors.message }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Podnadpis <span class="text-xs text-gray-400">(iOS)</span></label>
                                    <input v-model="standaloneForm.subtitle" type="text" placeholder="Voliteľný podnadpis" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Obrázok <span class="text-xs text-gray-400">(URL)</span></label>
                                    <input v-model="standaloneForm.image" type="url" placeholder="https://..." class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                    <p v-if="standaloneForm.errors.image" class="mt-1 text-xs text-red-500">{{ standaloneForm.errors.image }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Kam smeruje notifikácia</label>
                                    <div class="flex rounded-lg border border-gray-200 overflow-hidden focus-within:border-indigo-400 focus-within:ring-1 focus-within:ring-indigo-400">
                                        <span class="flex items-center bg-gray-100 px-3 text-xs font-mono text-gray-500 border-r border-gray-200 whitespace-nowrap select-none">titiapp://</span>
                                        <input v-model="standaloneUrlPath" type="text" placeholder="titi-predajne" class="flex-1 px-3 py-2 text-sm font-mono focus:outline-none"/>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-400">Prázdne = domovská obrazovka aplikácie.</p>
                                </div>
                            </div>

                            <hr class="border-gray-100"/>

                            <!-- Cieľ -->
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Komu odoslať</p>
                                    <span v-if="recipientPreview(standaloneForm.target_type)" class="text-xs text-indigo-600 font-medium bg-indigo-50 px-2 py-0.5 rounded-full">~{{ recipientPreview(standaloneForm.target_type) }} príjemcov</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <label v-for="opt in [
                                        { value: 'all', label: 'Všetci používatelia' },
                                        { value: 'testers', label: 'Všetci testeri' },
                                        { value: 'tester', label: 'Konkrétny tester' },
                                        { value: 'store', label: 'Zákazníci predajne' },
                                        { value: 'segment', label: 'Segment OneSignal' },
                                        { value: 'filtered', label: 'Vlastné filtre' },
                                    ]" :key="opt.value"
                                        :class="standaloneForm.target_type === opt.value ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-700 hover:border-gray-300'"
                                        class="flex items-center gap-2 cursor-pointer rounded-lg border px-3 py-2 text-sm transition-colors">
                                        <input v-model="standaloneForm.target_type" type="radio" :value="opt.value" class="text-indigo-600 shrink-0"/>
                                        {{ opt.label }}
                                    </label>
                                </div>

                                <div v-if="standaloneForm.target_type === 'tester'">
                                    <select v-model="standaloneForm.target_tester_id" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                        <option :value="null" disabled>Vybrať testera...</option>
                                        <option v-for="t in testers" :key="t.customer_id" :value="t.customer_id">{{ t.note || 'ID ' + t.customer_id }}</option>
                                    </select>
                                </div>

                                <div v-if="standaloneForm.target_type === 'store'">
                                    <select v-model="standaloneForm.target_store_id" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                        <option :value="null" disabled>Vybrať predajňu...</option>
                                        <option v-for="s in stores" :key="s.store_id" :value="s.store_id">{{ s.name }}</option>
                                    </select>
                                </div>

                                <div v-if="standaloneForm.target_type === 'segment'" class="relative">
                                    <input v-model="standaloneSegmentQuery" @focus="showStandaloneSegmentDrop = true" @blur="setTimeout(() => showStandaloneSegmentDrop = false, 150)"
                                        type="text" placeholder="Hľadať segment..."
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                    <div v-if="showStandaloneSegmentDrop && filteredSegmentsStandalone.length" class="absolute z-10 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg max-h-48 overflow-y-auto">
                                        <button v-for="seg in filteredSegmentsStandalone" :key="seg" @mousedown.prevent="selectStandaloneSegment(seg)" class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 transition-colors">{{ seg }}</button>
                                    </div>
                                    <p v-if="standaloneForm.errors.target_segment" class="mt-1 text-xs text-red-500">{{ standaloneForm.errors.target_segment }}</p>
                                </div>

                                <div v-if="standaloneForm.target_type === 'filtered'">
                                    <div class="space-y-2">
                                        <div v-for="(f, i) in standaloneFilters" :key="i" class="flex items-center gap-2">
                                            <input v-model="f.key" type="text" placeholder="kľúč" class="flex-1 rounded-lg border border-gray-200 px-2 py-1.5 text-xs focus:border-indigo-400 focus:outline-none"/>
                                            <select v-model="f.relation" class="rounded-lg border border-gray-200 px-2 py-1.5 text-xs focus:border-indigo-400 focus:outline-none">
                                                <option v-for="r in relationOptions" :key="r" :value="r">{{ r }}</option>
                                            </select>
                                            <input v-model="f.value" type="text" placeholder="hodnota" :disabled="f.relation === 'exists' || f.relation === 'not_exists'" class="flex-1 rounded-lg border border-gray-200 px-2 py-1.5 text-xs disabled:bg-gray-50 focus:border-indigo-400 focus:outline-none"/>
                                            <button @click="standaloneFilters.splice(i,1)" class="text-gray-400 hover:text-red-500 transition-colors"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                        </div>
                                    </div>
                                    <button @click="standaloneFilters.push(newFilterRow())" class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 transition-colors">+ Pridať filter</button>
                                </div>
                            </div>

                            <hr class="border-gray-100"/>

                            <!-- Doručenie -->
                            <div class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Doručenie</p>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Kedy odoslať</label>
                                    <div class="flex gap-4">
                                        <label class="flex items-center gap-2 cursor-pointer"><input v-model="standaloneForm.send_mode" type="radio" value="now" class="text-indigo-600"/><span class="text-sm text-gray-700">Ihneď</span></label>
                                        <label class="flex items-center gap-2 cursor-pointer"><input v-model="standaloneForm.send_mode" type="radio" value="scheduled" class="text-indigo-600"/><span class="text-sm text-gray-700">Naplánovať</span></label>
                                    </div>
                                </div>
                                <div v-if="standaloneForm.send_mode === 'scheduled'">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Dátum a čas <span class="text-red-500">*</span></label>
                                    <input v-model="standaloneForm.send_at" type="datetime-local" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                    <p v-if="standaloneForm.errors.send_at" class="mt-1 text-xs text-red-500">{{ standaloneForm.errors.send_at }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Priorita</label>
                                    <select v-model="standaloneForm.priority" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                        <option :value="10">Vysoká – prebudí zariadenie (odporúčané)</option>
                                        <option :value="5">Normálna – neprebudí zariadenie</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Platnosť (TTL)</label>
                                    <select v-model="standaloneForm.ttl" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                        <option v-for="opt in ttlOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Collapse ID <span class="text-xs text-gray-400">(voliteľné)</span></label>
                                    <input v-model="standaloneForm.collapse_id" type="text" placeholder="napr. promo-2026" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                    <p class="mt-1 text-xs text-gray-400">Nahradí existujúcu notifikáciu s rovnakým ID na zariadení.</p>
                                </div>
                            </div>

                            <hr class="border-gray-100"/>

                            <!-- iOS badge -->
                            <div class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">iOS badge <span class="font-normal normal-case text-gray-400">(číslo na ikone)</span></p>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Typ</label>
                                    <select v-model="standaloneForm.ios_badge_type" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                        <option value="">Nezmeniť badge</option>
                                        <option value="None">None – odstrániť badge</option>
                                        <option value="SetTo">SetTo – nastaviť na číslo</option>
                                        <option value="Increase">Increase – zvýšiť o číslo</option>
                                    </select>
                                </div>
                                <div v-if="standaloneForm.ios_badge_type === 'SetTo' || standaloneForm.ios_badge_type === 'Increase'">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ standaloneForm.ios_badge_type === 'SetTo' ? 'Nastaviť na' : 'Zvýšiť o' }} <span class="text-red-500">*</span></label>
                                    <input v-model.number="standaloneForm.ios_badge_count" type="number" min="0" max="9999" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                    <p v-if="standaloneForm.errors.ios_badge_count" class="mt-1 text-xs text-red-500">{{ standaloneForm.errors.ios_badge_count }}</p>
                                </div>
                            </div>

                        </div>

                        <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4 shrink-0">
                            <button @click="showStandaloneModal = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Zrušiť</button>
                            <button @click="submitStandalone" :disabled="standaloneForm.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                {{ standaloneForm.send_mode === 'now' ? 'Odoslať' : 'Naplánovať' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AuthenticatedLayout>
</template>
