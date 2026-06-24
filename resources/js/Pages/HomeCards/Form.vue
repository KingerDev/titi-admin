<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HomeCardTile from '@/Components/HomeCardTile.vue';
import { gridMetrics, cardRenderHeight, packCards } from '@/homeCardGrid';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch, watchEffect } from 'vue';

const props = defineProps({ card: Object, cards: { type: Array, default: () => [] } });
const isEdit = computed(() => !!props.card);
const page = usePage();

const PHONE_WIDTH = 390;
const metrics = gridMetrics(PHONE_WIDTH);

// ── Toast ────────────────────────────────────────────────────────────────────
const toast = ref({ show: false, message: '', type: 'success' });
function showToast(message, type = 'success') {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 4000);
}
if (page.props.flash?.success) showToast(page.props.flash.success);

// ── Formulár ─────────────────────────────────────────────────────────────────
const form = useForm({
    top_text:           props.card?.top_text           ?? '',
    title:              props.card?.title              ?? '',
    subtitle:           props.card?.subtitle           ?? '',
    bg_color:           props.card?.bg_color           ?? '#2BC4DE',
    text_color:         props.card?.text_color         ?? '#1B2233',
    top_text_color:     props.card?.top_text_color     ?? null,
    col_span:           props.card?.col_span           ?? 6,
    row_span:           props.card?.row_span           ?? 2,
    app_route:          props.card?.app_route          ?? '',
    external_url:       props.card?.external_url        ?? '',
    pattern:            props.card?.pattern            ?? 'dots',
    decor:              props.card?.decor             ?? 'bubbles',
    show_arrow:         props.card?.show_arrow         ?? true,
    audience:           props.card?.audience           ?? 'all',
    platform:           props.card?.platform           ?? 'all',
    active:             props.card?.active             ?? true,
    valid_from:         props.card?.valid_from         ?? '',
    valid_to:           props.card?.valid_to           ?? '',
});

// ── Farba top textu: „rovnaká ako text" ──────────────────────────────────────
const sameTopColor = ref(!form.top_text_color);
watch(sameTopColor, (v) => {
    form.top_text_color = v ? null : (form.text_color || '#1B2233');
});

// ── Farebné témy a paletky ────────────────────────────────────────────────────
// Téma = harmonická dvojica pozadie + text (jeden klik nastaví oboje).
const colorThemes = [
    { name: 'Tyrkysová',  bg: '#2BC4DE', text: '#1B2233' },
    { name: 'Limetka',    bg: '#C8FF00', text: '#1B2233' },
    { name: 'Žltá',       bg: '#F9C84A', text: '#1B2233' },
    { name: 'Ružová',     bg: '#EC2F86', text: '#FFFFFF' },
    { name: 'Tmavá',      bg: '#1B2233', text: '#FFFFFF' },
    { name: 'Fialová',    bg: '#8B5CF6', text: '#FFFFFF' },
    { name: 'Koralová',   bg: '#FF6B5E', text: '#FFFFFF' },
    { name: 'Biela',      bg: '#FFFFFF', text: '#1B2233' },
    { name: 'Mätová',     bg: '#34D399', text: '#1B2233' },
    { name: 'Nebeská',    bg: '#60A5FA', text: '#1B2233' },
    { name: 'Oranžová',   bg: '#FB923C', text: '#1B2233' },
    { name: 'Červená',    bg: '#EF4444', text: '#FFFFFF' },
    { name: 'Indigo',     bg: '#4F46E5', text: '#FFFFFF' },
    { name: 'Smaragd',    bg: '#10B981', text: '#FFFFFF' },
    { name: 'Broskyňová', bg: '#FDBA74', text: '#1B2233' },
    { name: 'Levanduľa',  bg: '#C4B5FD', text: '#1B2233' },
    { name: 'Petrolejová', bg: '#0E7490', text: '#FFFFFF' },
    { name: 'Sivá',       bg: '#E5E7EB', text: '#1B2233' },
    { name: 'Jantárová',  bg: '#F59E0B', text: '#1B2233' },
    { name: 'Rubínová',   bg: '#BE123C', text: '#FFFFFF' },
    { name: 'Fuchsia',    bg: '#D946EF', text: '#FFFFFF' },
    { name: 'Kráľ. modrá', bg: '#1D4ED8', text: '#FFFFFF' },
    { name: 'Tyrkys tm.', bg: '#0891B2', text: '#FFFFFF' },
    { name: 'Olivová',    bg: '#4D7C0F', text: '#FFFFFF' },
    { name: 'Hnedá',      bg: '#92400E', text: '#FFFFFF' },
    { name: 'Tmavosivá',  bg: '#374151', text: '#FFFFFF' },
    { name: 'Mätový pastel', bg: '#A7F3D0', text: '#1B2233' },
    { name: 'Nebeský pastel', bg: '#BAE6FD', text: '#1B2233' },
    { name: 'Žltý pastel', bg: '#FEF08A', text: '#1B2233' },
    { name: 'Ružový pastel', bg: '#FBCFE8', text: '#1B2233' },
    { name: 'Lila pastel', bg: '#E9D5FF', text: '#1B2233' },
    { name: 'Slezová',    bg: '#FB7185', text: '#1B2233' },
];
const bgSwatches = ['#2BC4DE', '#C8FF00', '#F9C84A', '#EC2F86', '#8B5CF6', '#FF6B5E', '#34D399', '#60A5FA', '#1B2233', '#FFFFFF'];
const textSwatches = ['#1B2233', '#FFFFFF', '#EC2F86', '#2BC4DE', '#F9C84A'];

const eq = (a, b) => (a || '').toLowerCase() === (b || '').toLowerCase();
function applyTheme(t) {
    form.bg_color = t.bg;
    form.text_color = t.text;
    if (sameTopColor.value) form.top_text_color = null;
}
function isActiveTheme(t) {
    return eq(form.bg_color, t.bg) && eq(form.text_color, t.text);
}

// ── Sprievodca odkazom ───────────────────────────────────────────────────────
const linkType = ref('none'); // none | category | collection | sale | custom | external
const link = reactive({ catId: '', catTitle: '', colSlug: '', colTitle: '', externalUrl: '', customRoute: '' });

// ── Našepkávač kategórií ──────────────────────────────────────────────────────
const categoryQuery = ref('');
const categoryResults = ref([]);
const categoryLoading = ref(false);
let categoryTimer = null;

function onCategorySearch() {
    clearTimeout(categoryTimer);
    const q = categoryQuery.value.trim();
    if (q.length < 2) { categoryResults.value = []; categoryLoading.value = false; return; }
    categoryLoading.value = true;
    categoryTimer = setTimeout(async () => {
        try {
            const res = await fetch(route('home-cards.search-categories', { q }), { headers: { Accept: 'application/json' } });
            categoryResults.value = res.ok ? await res.json() : [];
        } catch {
            categoryResults.value = [];
        } finally {
            categoryLoading.value = false;
        }
    }, 250);
}
function selectCategory(r) {
    link.catId = String(r.category_id);
    link.catTitle = r.name;
    categoryQuery.value = '';
    categoryResults.value = [];
}
function clearCategory() {
    link.catId = '';
    link.catTitle = '';
    categoryQuery.value = '';
    categoryResults.value = [];
}

function parseQuery(routeStr) {
    const i = routeStr.indexOf('?');
    if (i < 0) return {};
    const sp = new URLSearchParams(routeStr.slice(i + 1));
    const o = {};
    sp.forEach((v, k) => { o[k] = v; });
    return o;
}

(function initLink() {
    if (props.card?.external_url) { linkType.value = 'external'; link.externalUrl = props.card.external_url; return; }
    const r = props.card?.app_route;
    if (!r) { linkType.value = 'none'; return; }
    if (r.startsWith('/category-products')) { linkType.value = 'category'; const q = parseQuery(r); link.catId = q.categoryId ?? ''; link.catTitle = q.title ?? ''; }
    else if (r.startsWith('/collection-products')) { linkType.value = 'collection'; const q = parseQuery(r); link.colSlug = q.collection ?? ''; link.colTitle = q.title ?? ''; }
    else if (r.startsWith('/products-in-sale')) { linkType.value = 'sale'; }
    else { linkType.value = 'custom'; link.customRoute = r; }
})();

watchEffect(() => {
    let appRoute = '';
    let ext = '';
    switch (linkType.value) {
        case 'category':
            if (link.catId) {
                appRoute = `/category-products?categoryId=${encodeURIComponent(link.catId)}`;
                if (link.catTitle) appRoute += `&title=${encodeURIComponent(link.catTitle)}`;
            }
            break;
        case 'collection':
            if (link.colSlug) {
                appRoute = `/collection-products?collection=${encodeURIComponent(link.colSlug)}`;
                if (link.colTitle) appRoute += `&title=${encodeURIComponent(link.colTitle)}`;
            }
            break;
        case 'sale':   appRoute = '/products-in-sale'; break;
        case 'custom': appRoute = link.customRoute || ''; break;
        case 'external': ext = link.externalUrl || ''; break;
    }
    form.app_route = appRoute;
    form.external_url = ext;
});

const linkTypes = [
    { value: 'none',       label: 'Bez odkazu',     desc: 'Karta nie je klikateľná' },
    { value: 'category',   label: 'Kategória',      desc: 'Otvorí produkty kategórie' },
    { value: 'collection', label: 'Kolekcia',       desc: 'Napr. novinky, výpredaj' },
    { value: 'sale',       label: 'Akcie',          desc: 'Produkty v akcii' },
    { value: 'external',   label: 'Externý odkaz',  desc: 'Otvorí webovú stránku' },
    { value: 'custom',     label: 'Vlastná cesta',  desc: 'Ručne zadaná app cesta' },
];

const finalTarget = computed(() => form.external_url || form.app_route || '');

// ── Náhľad ───────────────────────────────────────────────────────────────────
const previewCard = computed(() => ({
    top_text:       form.top_text,
    title:          form.title || 'Nadpis karty',
    subtitle:       form.subtitle,
    bg_color:       form.bg_color || '#2BC4DE',
    text_color:     form.text_color || '#1B2233',
    top_text_color: form.top_text_color,
    col_span:       form.col_span,
    row_span:       form.row_span,
    pattern:        form.pattern,
    decor:          form.decor,
    show_arrow:     form.show_arrow,
    app_route:      form.app_route,
    external_url:   form.external_url,
}));
const previewWidth  = computed(() => metrics.cardWidth(form.col_span));
const previewHeight = computed(() => cardRenderHeight(previewCard.value, metrics));

// Celý grid v náhľade: ostatné karty z DB + práve upravovaná karta naživo z formulára.
const editingId = computed(() => (props.card ? props.card.id : '__new__'));
const previewCards = computed(() => {
    const live = { ...previewCard.value, id: editingId.value };
    if (props.card) {
        return props.cards.map((c) => (c.id === props.card.id ? { ...c, ...live } : c));
    }
    return [...props.cards, live];
});
const packed = computed(() => packCards(previewCards.value, metrics));

// ── Uloženie ─────────────────────────────────────────────────────────────────
function save() {
    if (isEdit.value) {
        form.put(route('home-cards.update', props.card.id), {
            onSuccess: () => showToast('Karta bola uložená.'),
            onError:   () => showToast('Skontrolujte chyby vo formulári.', 'error'),
        });
    } else {
        form.post(route('home-cards.store'), {
            onError: () => showToast('Skontrolujte chyby vo formulári.', 'error'),
        });
    }
}
function deleteCard() {
    if (!confirm('Zmazať túto kartu?')) return;
    router.delete(route('home-cards.destroy', props.card.id));
}

const audienceOpts = [
    { value: 'all',   label: 'Všetci' },
    { value: 'guest', label: 'Neprihlásení' },
    { value: 'auth',  label: 'Prihlásení' },
];
const platformOpts = [
    { value: 'all',     label: 'Všetky' },
    { value: 'ios',     label: 'iOS' },
    { value: 'android', label: 'Android' },
];
const patternOpts = [
    { value: 'none',     label: 'Žiadny' },
    { value: 'dots',     label: 'Bodky' },
    { value: 'grid',     label: 'Mriežka' },
    { value: 'diagonal', label: 'Šikmé linky' },
    { value: 'cross',    label: 'Krížiky' },
    { value: 'zigzag',   label: 'Cik-cak' },
    { value: 'wave',     label: 'Vlnky' },
];
const decorOpts = [
    { value: 'none',     label: 'Žiadna' },
    { value: 'bubbles',  label: 'Bubliny' },
    { value: 'blob',     label: 'Veľká škvrna' },
    { value: 'rings',    label: 'Kruhy (obrys)' },
    { value: 'corner',   label: 'Rohový kruh' },
    { value: 'confetti', label: 'Konfety' },
    { value: 'waves',    label: 'Vlny dole' },
];
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a :href="route('home-cards.index')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </a>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ isEdit ? 'Upraviť kartu' : 'Nová karta' }}
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="isEdit" @click="deleteCard"
                            class="rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
                        Zmazať
                    </button>
                    <button @click="save" :disabled="form.processing"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors disabled:opacity-50">
                        Uložiť
                    </button>
                </div>
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
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 sm:px-6 lg:grid-cols-[1fr_minmax(360px,420px)] lg:px-8">

                <!-- ── Formulár ───────────────────────────────────────────── -->
                <div class="space-y-6">

                    <!-- Obsah -->
                    <div class="rounded-xl bg-white p-6 shadow space-y-4">
                        <h3 class="text-base font-semibold text-gray-900">Obsah</h3>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Malý text nad nadpisom</label>
                            <input v-model="form.top_text" type="text" maxlength="60" placeholder="napr. ČERSTVÉ"
                                   class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                            <p class="mt-1 text-xs text-gray-400">V appke sa zobrazuje VEĽKÝMI písmenami.</p>
                            <p v-if="form.errors.top_text" class="mt-1 text-xs text-red-500">{{ form.errors.top_text }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Nadpis <span class="text-red-500">*</span></label>
                            <textarea v-model="form.title" rows="2" maxlength="150" placeholder="napr. Futbalová zóna"
                                      class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                            <p class="mt-1 text-xs text-gray-400">Enter = nový riadok. Max 2 riadky sa zobrazia.</p>
                            <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Podnadpis</label>
                            <input v-model="form.subtitle" type="text" maxlength="150" placeholder="napr. Objav najnovšie produkty"
                                   class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                            <p v-if="form.errors.subtitle" class="mt-1 text-xs text-red-500">{{ form.errors.subtitle }}</p>
                        </div>
                    </div>

                    <!-- Vzhľad -->
                    <div class="rounded-xl bg-white p-6 shadow space-y-4">
                        <h3 class="text-base font-semibold text-gray-900">Farby</h3>

                        <!-- Hotové témy (jeden klik = pozadie + text) -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Téma</label>
                            <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                                <button v-for="t in colorThemes" :key="t.name" type="button" @click="applyTheme(t)" :title="t.name"
                                        :style="{ backgroundColor: t.bg }"
                                        :class="isActiveTheme(t) ? 'ring-2 ring-indigo-500 ring-offset-1' : 'ring-1 ring-gray-200 hover:ring-gray-300'"
                                        class="flex h-10 items-center justify-center rounded-lg">
                                    <span :style="{ color: t.text }" class="text-sm font-extrabold">Aa</span>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Vyber tému alebo si nižšie nastav vlastné farby.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Pozadie <span class="text-red-500">*</span></label>
                                <div class="flex items-center gap-2">
                                    <input v-model="form.bg_color" type="color" class="h-10 w-12 cursor-pointer rounded-lg border border-gray-200"/>
                                    <input v-model="form.bg_color" type="text" class="flex-1 rounded-lg border border-gray-200 px-3 py-2 font-mono text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <button v-for="c in bgSwatches" :key="c" type="button" @click="form.bg_color = c" :title="c"
                                            :style="{ backgroundColor: c }"
                                            :class="eq(form.bg_color, c) ? 'ring-2 ring-indigo-500' : 'ring-1 ring-gray-200 hover:ring-gray-300'"
                                            class="h-6 w-6 rounded-md"/>
                                </div>
                                <p v-if="form.errors.bg_color" class="mt-1 text-xs text-red-500">{{ form.errors.bg_color }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Text <span class="text-red-500">*</span></label>
                                <div class="flex items-center gap-2">
                                    <input v-model="form.text_color" type="color" class="h-10 w-12 cursor-pointer rounded-lg border border-gray-200"/>
                                    <input v-model="form.text_color" type="text" class="flex-1 rounded-lg border border-gray-200 px-3 py-2 font-mono text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <button v-for="c in textSwatches" :key="c" type="button" @click="form.text_color = c" :title="c"
                                            :style="{ backgroundColor: c }"
                                            :class="eq(form.text_color, c) ? 'ring-2 ring-indigo-500' : 'ring-1 ring-gray-200 hover:ring-gray-300'"
                                            class="h-6 w-6 rounded-md"/>
                                </div>
                                <p v-if="form.errors.text_color" class="mt-1 text-xs text-red-500">{{ form.errors.text_color }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="sameTopColor" type="checkbox" class="rounded border-gray-300"/>
                                Farba malého textu rovnaká ako text
                            </label>
                            <div v-if="!sameTopColor" class="mt-2 flex items-center gap-2">
                                <input v-model="form.top_text_color" type="color" class="h-10 w-12 cursor-pointer rounded-lg border border-gray-200"/>
                                <input v-model="form.top_text_color" type="text" placeholder="#EC2F86" class="flex-1 rounded-lg border border-gray-200 px-3 py-2 font-mono text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                            </div>
                            <p v-if="form.errors.top_text_color" class="mt-1 text-xs text-red-500">{{ form.errors.top_text_color }}</p>
                        </div>
                    </div>

                    <!-- Dekorácie -->
                    <div class="rounded-xl bg-white p-6 shadow space-y-4">
                        <h3 class="text-base font-semibold text-gray-900">Dekorácie</h3>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Vzor na pozadí</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="o in patternOpts" :key="o.value" type="button" @click="form.pattern = o.value"
                                        :class="form.pattern === o.value ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors">{{ o.label }}</button>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Jemný vzor vo farbe textu. Ladí na každej farbe pozadia.</p>
                            <p v-if="form.errors.pattern" class="mt-1 text-xs text-red-500">{{ form.errors.pattern }}</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Ozdobné tvary</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="o in decorOpts" :key="o.value" type="button" @click="form.decor = o.value"
                                        :class="form.decor === o.value ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors">{{ o.label }}</button>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Svetlé polopriehľadné tvary cez pozadie karty.</p>
                            <p v-if="form.errors.decor" class="mt-1 text-xs text-red-500">{{ form.errors.decor }}</p>
                        </div>

                        <div>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.show_arrow" type="checkbox" class="rounded border-gray-300"/>
                                Šípka v rohu
                            </label>
                            <p class="mt-1 text-xs text-gray-400">
                                Zobrazí sa len ak je karta dosť vysoká (<strong>výška ≥ 2 riadky</strong>).
                            </p>
                        </div>
                    </div>

                    <!-- Rozloženie -->
                    <div class="rounded-xl bg-white p-6 shadow space-y-4">
                        <h3 class="text-base font-semibold text-gray-900">Rozloženie v gride</h3>

                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <label class="text-sm font-medium text-gray-700">Šírka (stĺpce)</label>
                                <span class="text-sm font-semibold text-indigo-600">{{ form.col_span }} / 12</span>
                            </div>
                            <input v-model.number="form.col_span" type="range" min="1" max="12" step="1" class="w-full accent-indigo-600"/>
                            <p v-if="form.errors.col_span" class="mt-1 text-xs text-red-500">{{ form.errors.col_span }}</p>
                        </div>
                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <label class="text-sm font-medium text-gray-700">Výška (riadky)</label>
                                <span class="text-sm font-semibold text-indigo-600">{{ form.row_span }}</span>
                            </div>
                            <input v-model.number="form.row_span" type="range" min="1" max="6" step="1" class="w-full accent-indigo-600"/>
                            <p class="mt-1 text-xs text-gray-400">2 karty so šírkou napr. 5 + 7 vyplnia celý riadok (spolu 12).</p>
                            <p v-if="form.errors.row_span" class="mt-1 text-xs text-red-500">{{ form.errors.row_span }}</p>
                        </div>
                    </div>

                    <!-- Odkaz -->
                    <div class="rounded-xl bg-white p-6 shadow space-y-4">
                        <h3 class="text-base font-semibold text-gray-900">Kam vedie kliknutie</h3>

                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <label v-for="opt in linkTypes" :key="opt.value"
                                   :class="linkType === opt.value ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                                   class="cursor-pointer rounded-lg border p-3 transition-colors">
                                <input v-model="linkType" type="radio" :value="opt.value" class="sr-only"/>
                                <span class="block text-sm font-semibold" :class="linkType === opt.value ? 'text-indigo-700' : 'text-gray-700'">{{ opt.label }}</span>
                                <span class="block text-xs" :class="linkType === opt.value ? 'text-indigo-500' : 'text-gray-400'">{{ opt.desc }}</span>
                            </label>
                        </div>

                        <!-- Kategória -->
                        <div v-if="linkType === 'category'" class="space-y-3">
                            <!-- Vybraná kategória -->
                            <div v-if="link.catId" class="flex items-center justify-between rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-indigo-800">{{ link.catTitle || 'Kategória' }}</p>
                                    <p class="text-xs text-indigo-500">ID kategórie: {{ link.catId }}</p>
                                </div>
                                <button type="button" @click="clearCategory" class="ml-3 shrink-0 text-xs font-medium text-indigo-600 hover:text-indigo-800">Zmeniť</button>
                            </div>

                            <!-- Vyhľadávanie -->
                            <div v-else class="relative">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Kategória <span class="text-red-500">*</span></label>
                                <input v-model="categoryQuery" @input="onCategorySearch" type="text" placeholder="Začni písať názov kategórie…"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                <p v-if="categoryLoading" class="mt-1 text-xs text-gray-400">Hľadám…</p>
                                <p v-else-if="categoryQuery.trim().length >= 2 && !categoryResults.length" class="mt-1 text-xs text-gray-400">Nič sa nenašlo.</p>
                                <ul v-if="categoryResults.length" class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                                    <li v-for="r in categoryResults" :key="r.category_id" @click="selectCategory(r)"
                                        class="cursor-pointer border-b border-gray-50 px-3 py-2 last:border-0 hover:bg-indigo-50">
                                        <p class="text-sm font-medium text-gray-800">{{ r.name }}</p>
                                        <p class="truncate text-xs text-gray-400">{{ r.path }} · ID {{ r.category_id }}</p>
                                    </li>
                                </ul>
                            </div>

                            <!-- Nadpis stránky (predvyplnený, voliteľne upraviteľný) -->
                            <div v-if="link.catId">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Nadpis stránky</label>
                                <input v-model="link.catTitle" type="text" placeholder="napr. Futbalová zóna"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                                <p class="mt-1 text-xs text-gray-400">Predvyplnené názvom kategórie — môžeš zmeniť.</p>
                            </div>
                        </div>

                        <!-- Kolekcia -->
                        <div v-else-if="linkType === 'collection'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Kód kolekcie <span class="text-red-500">*</span></label>
                                <input v-model="link.colSlug" type="text" placeholder="napr. novinky"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Nadpis stránky</label>
                                <input v-model="link.colTitle" type="text" placeholder="napr. Novinky"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                            </div>
                        </div>

                        <!-- Externý -->
                        <div v-else-if="linkType === 'external'">
                            <label class="mb-1 block text-sm font-medium text-gray-700">URL adresa <span class="text-red-500">*</span></label>
                            <input v-model="link.externalUrl" type="url" placeholder="https://..."
                                   class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                        </div>

                        <!-- Vlastná cesta -->
                        <div v-else-if="linkType === 'custom'">
                            <label class="mb-1 block text-sm font-medium text-gray-700">App cesta <span class="text-red-500">*</span></label>
                            <input v-model="link.customRoute" type="text" placeholder="/category-products?categoryId=123"
                                   class="w-full rounded-lg border border-gray-200 px-3 py-2 font-mono text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                        </div>

                        <div v-if="finalTarget" class="rounded-lg bg-gray-50 px-3 py-2">
                            <p class="text-xs text-gray-400">Výsledný odkaz:</p>
                            <p class="break-all font-mono text-xs text-gray-700">{{ finalTarget }}</p>
                        </div>
                        <p v-if="form.errors.app_route" class="text-xs text-red-500">{{ form.errors.app_route }}</p>
                        <p v-if="form.errors.external_url" class="text-xs text-red-500">{{ form.errors.external_url }}</p>
                    </div>

                    <!-- Cielenie -->
                    <div class="rounded-xl bg-white p-6 shadow space-y-4">
                        <h3 class="text-base font-semibold text-gray-900">Komu a kedy sa zobrazí</h3>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Používateľ</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="o in audienceOpts" :key="o.value" type="button" @click="form.audience = o.value"
                                        :class="form.audience === o.value ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors">{{ o.label }}</button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Platforma</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="o in platformOpts" :key="o.value" type="button" @click="form.platform = o.value"
                                        :class="form.platform === o.value ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors">{{ o.label }}</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Platná od</label>
                                <input v-model="form.valid_from" type="datetime-local"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Platná do</label>
                                <input v-model="form.valid_to" type="datetime-local"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 pt-1 text-sm font-medium text-gray-700">
                            <input v-model="form.active" type="checkbox" class="rounded border-gray-300"/>
                            Karta je aktívna (zobrazuje sa v aplikácii)
                        </label>
                    </div>

                    <div class="flex justify-end pb-8">
                        <button @click="save" :disabled="form.processing"
                                class="rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition-colors disabled:opacity-50">
                            Uložiť
                        </button>
                    </div>
                </div>

                <!-- ── Živý náhľad ────────────────────────────────────────── -->
                <div>
                    <div class="sticky top-6">
                        <div class="rounded-xl bg-white p-5 shadow">
                            <h3 class="mb-3 text-base font-semibold text-gray-900">Živý náhľad</h3>
                            <div class="rounded-2xl p-4" style="background-color:#f3f0e8;">
                                <div class="relative mx-auto" :style="{ width: metrics.containerWidth + 'px', height: packed.height + 'px' }">
                                    <div
                                        v-for="p in packed.placements" :key="p.card.id"
                                        class="absolute transition-opacity"
                                        :class="p.card.id === editingId ? 'ring-2 ring-indigo-500 ring-offset-2 rounded-[24px]' : 'opacity-60'"
                                        :style="{ left: p.left + 'px', top: p.top + 'px', width: p.width + 'px' }"
                                    >
                                        <HomeCardTile :card="p.card" :width="p.width" :height="p.height"/>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-3 text-center text-xs text-gray-400">
                                Upravovaná karta je zvýraznená · {{ previewWidth }}×{{ previewHeight }} px
                            </p>
                        </div>
                        <p class="mt-3 px-1 text-xs text-gray-400">
                            Takto presne bude karta vyzerať v aplikácii. Výsledné poradie nastavíš ťahaním na prehľade kariet.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
