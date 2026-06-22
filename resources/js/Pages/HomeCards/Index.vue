<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HomeCardTile from '@/Components/HomeCardTile.vue';
import { gridMetrics, packCards, H_PADDING } from '@/homeCardGrid';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ cards: Array });
const page = usePage();

// ── Toast ────────────────────────────────────────────────────────────────────
const toast = ref({ show: false, message: '', type: 'success' });
function showToast(message, type = 'success') {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3500);
}
if (page.props.flash?.success) showToast(page.props.flash.success);

// ── Lokálna kópia (kvôli drag & drop poradiu) ────────────────────────────────
const localCards = ref([...props.cards]);

// ── Náhľad telefónu ──────────────────────────────────────────────────────────
const PHONE_WIDTH = 390; // referenčná šírka obrazovky (iPhone), rovná sa appke
const metrics = gridMetrics(PHONE_WIDTH);

// Skyline packing 1:1 s appkou (rovnaké rozloženie aj poradie).
const packed = computed(() => packCards(localCards.value, metrics));

// ── Simulácia kontextu (ako to uvidí konkrétny používateľ) ───────────────────
const sim = ref({ audience: 'all', platform: 'all', loyalty: 'off' });

function matchReason(card) {
    if (!card.active) return 'Neaktívna';
    if (sim.value.audience !== 'all' && card.audience !== 'all' && card.audience !== sim.value.audience)
        return card.audience === 'auth' ? 'Len pre prihlásených' : 'Len pre neprihlásených';
    if (sim.value.platform !== 'all' && card.platform !== 'all' && card.platform !== sim.value.platform)
        return 'Iná platforma (' + card.platform + ')';
    if (card.loyalty_visibility !== 'any' && card.loyalty_visibility !== sim.value.loyalty)
        return card.loyalty_visibility === 'on' ? 'Len s vernostným prog.' : 'Len bez vernostného prog.';
    const now = new Date();
    if (card.valid_from && new Date(card.valid_from) > now) return 'Ešte nezačala';
    if (card.valid_to && new Date(card.valid_to) < now) return 'Platnosť vypršala';
    return null; // zobrazí sa
}
function isVisible(card) { return matchReason(card) === null; }

const visibleCount = computed(() => localCards.value.filter(isVisible).length);

// ── Drag & drop preusporiadanie ──────────────────────────────────────────────
const dragIndex = ref(null);
const overIndex = ref(null);

function onDragStart(i) { dragIndex.value = i; }
function onDragOver(i) { overIndex.value = i; }
function onDrop(i) {
    const from = dragIndex.value;
    if (from === null || from === i) { reset(); return; }
    const arr = localCards.value;
    const [moved] = arr.splice(from, 1);
    arr.splice(i, 0, moved);
    reset();
    persistOrder();
}
function reset() { dragIndex.value = null; overIndex.value = null; }

function persistOrder() {
    router.post(route('home-cards.reorder'),
        { ids: localCards.value.map(c => c.id) },
        { preserveScroll: true, onSuccess: () => showToast('Poradie uložené.') }
    );
}

function editCard(card) { router.get(route('home-cards.edit', card.id)); }

function deleteCard(card) {
    if (!confirm(`Zmazať kartu „${card.title.replace(/\n/g, ' ')}"?`)) return;
    router.delete(route('home-cards.destroy', card.id), {
        preserveScroll: true,
        onSuccess: () => { localCards.value = localCards.value.filter(c => c.id !== card.id); },
    });
}

const audienceLabel = { all: 'Všetci', guest: 'Neprihlásení', auth: 'Prihlásení' };
const platformLabel = { all: 'Všetky', ios: 'iOS', android: 'Android' };
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Domovské karty</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Karty na úvodnej obrazovke aplikácie (novinky, futbalová zóna…)</p>
                </div>
                <a :href="route('home-cards.create')"
                   class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nová karta
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
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 sm:px-6 lg:grid-cols-[auto_1fr] lg:px-8">

                <!-- ── Náhľad v telefóne ──────────────────────────────────── -->
                <div class="flex flex-col items-center">
                    <div class="rounded-[44px] border-[10px] border-gray-900 bg-gray-900 shadow-2xl">
                        <div class="overflow-hidden rounded-[34px]" :style="{ width: PHONE_WIDTH + 'px', backgroundColor: '#f3f0e8' }">
                            <!-- notch -->
                            <div class="flex justify-center pt-2.5 pb-1">
                                <div class="h-1.5 w-24 rounded-full bg-gray-300"></div>
                            </div>
                            <div :style="{ padding: H_PADDING + 'px' }">
                                <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-gray-400">Úvod — domovské karty</p>

                                <div v-if="localCards.length === 0" class="rounded-2xl border-2 border-dashed border-gray-300 py-12 text-center text-sm text-gray-400">
                                    Zatiaľ žiadne karty
                                </div>

                                <div v-else class="relative" :style="{ height: packed.height + 'px' }">
                                    <div
                                        v-for="(p, i) in packed.placements" :key="p.card.id"
                                        draggable="true"
                                        @dragstart="onDragStart(i)"
                                        @dragover.prevent="onDragOver(i)"
                                        @drop.prevent="onDrop(i)"
                                        @dragend="reset"
                                        class="group absolute cursor-grab active:cursor-grabbing transition-opacity"
                                        :class="[
                                            !isVisible(p.card) ? 'opacity-30' : '',
                                            overIndex === i && dragIndex !== i ? 'ring-2 ring-indigo-500 ring-offset-2 rounded-[24px]' : '',
                                        ]"
                                        :style="{ left: p.left + 'px', top: p.top + 'px', width: p.width + 'px' }"
                                    >
                                        <HomeCardTile :card="p.card" :width="p.width" :height="p.height" />

                                        <!-- overlay actions -->
                                        <div class="pointer-events-none absolute inset-0 flex items-start justify-end gap-1 p-1.5 opacity-0 transition-opacity group-hover:opacity-100">
                                            <button @click.stop="editCard(p.card)"
                                                    class="pointer-events-auto rounded-md bg-white/90 p-1.5 text-gray-700 shadow hover:bg-white" title="Upraviť">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button @click.stop="deleteCard(p.card)"
                                                    class="pointer-events-auto rounded-md bg-white/90 p-1.5 text-red-600 shadow hover:bg-white" title="Zmazať">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- hidden badge -->
                                        <div v-if="!isVisible(p.card)" class="pointer-events-none absolute bottom-1.5 left-1.5">
                                            <span class="rounded bg-gray-900/80 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-white">
                                                {{ matchReason(p.card) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 max-w-[390px] text-center text-xs text-gray-400">
                        Karty preusporiadaj ťahaním myšou. Šírka = stĺpce, výška = riadky. Sivé karty sa pri zvolenom kontexte v appke nezobrazia.
                    </p>
                </div>

                <!-- ── Bočný panel ────────────────────────────────────────── -->
                <div class="space-y-6">
                    <!-- Simulácia -->
                    <div class="rounded-xl bg-white p-5 shadow">
                        <h3 class="mb-1 text-base font-semibold text-gray-900">Simulácia zobrazenia</h3>
                        <p class="mb-4 text-xs text-gray-500">Vyber, akému používateľovi chceš zobraziť náhľad. Karty, ktoré by daný používateľ nevidel, stmavnú.</p>

                        <div class="space-y-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Používateľ</label>
                                <div class="flex gap-1.5">
                                    <button v-for="(lbl, val) in audienceLabel" :key="val" @click="sim.audience = val"
                                            :class="sim.audience === val ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                            class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors">{{ lbl }}</button>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Platforma</label>
                                <div class="flex gap-1.5">
                                    <button v-for="(lbl, val) in platformLabel" :key="val" @click="sim.platform = val"
                                            :class="sim.platform === val ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                            class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors">{{ lbl }}</button>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Vernostný program</label>
                                <div class="flex gap-1.5">
                                    <button @click="sim.loyalty = 'off'"
                                            :class="sim.loyalty === 'off' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                            class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors">Bez</button>
                                    <button @click="sim.loyalty = 'on'"
                                            :class="sim.loyalty === 'on' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                            class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors">Zapnutý</button>
                                </div>
                            </div>
                        </div>

                        <p class="mt-4 text-xs text-gray-500">
                            Zobrazí sa <span class="font-semibold text-gray-800">{{ visibleCount }}</span> z {{ localCards.length }} kariet.
                        </p>
                    </div>

                    <!-- Zoznam -->
                    <div class="overflow-hidden rounded-xl bg-white shadow">
                        <div class="border-b border-gray-100 px-5 py-3">
                            <h3 class="text-base font-semibold text-gray-900">Všetky karty</h3>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            <li v-for="card in localCards" :key="card.id"
                                class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 cursor-pointer"
                                @click="editCard(card)">
                                <span class="h-7 w-7 flex-shrink-0 rounded-md border border-gray-200" :style="{ backgroundColor: card.bg_color }"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ (card.title || '').replace(/\n/g, ' ') || '—' }}</p>
                                    <p class="truncate text-xs text-gray-400">
                                        {{ card.col_span }}×{{ card.row_span }} ·
                                        {{ audienceLabel[card.audience] }} ·
                                        {{ platformLabel[card.platform] }}
                                    </p>
                                </div>
                                <span v-if="!card.active" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Neaktívna</span>
                                <span v-else class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Aktívna</span>
                            </li>
                            <li v-if="localCards.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">Žiadne karty</li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
