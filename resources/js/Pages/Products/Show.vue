<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <Link :href="route('categories.index')" class="hover:text-indigo-600 transition-colors">Kategórie</Link>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <template v-if="product.categories.length > 0">
                    <Link :href="route('categories.show', product.categories[0].category_id)"
                          class="hover:text-indigo-600 transition-colors">
                        {{ product.categories[0].name }}
                    </Link>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </template>
                <span class="font-semibold text-gray-800 truncate max-w-xs">{{ product.name }}</span>
            </div>
        </template>

        <!-- Toast -->
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="toast.show"
                 class="fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg"
                 :class="toast.type === 'error' ? 'bg-red-600' : 'bg-green-600'">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path v-if="toast.type === 'error'" stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                {{ toast.message }}
            </div>
        </Transition>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <!-- Product header card -->
                <div class="mb-6 flex gap-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <!-- Image -->
                    <div class="h-28 w-28 flex-shrink-0 overflow-hidden rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center">
                        <img v-if="product.image" :src="product.image" :alt="product.name"
                             class="max-h-full max-w-full object-contain"
                             @error="$event.target.style.display='none'" />
                        <svg v-else class="h-10 w-10 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <h1 class="text-lg font-semibold text-gray-900 mb-2">{{ product.name }}</h1>

                        <!-- Categories -->
                        <div v-if="product.categories.length > 0" class="flex flex-wrap gap-1.5 mb-3">
                            <Link
                                v-for="cat in product.categories"
                                :key="cat.category_id"
                                :href="route('categories.show', cat.category_id)"
                                class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
                            >
                                {{ cat.name }}
                            </Link>
                        </div>

                        <!-- Filters grouped by group_name -->
                        <div v-if="product.filters.length > 0" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="f in product.filters"
                                :key="f.filter_id"
                                class="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-700"
                            >
                                <span class="opacity-60 mr-1">{{ f.group_name }}:</span>{{ f.name }}
                            </span>
                        </div>
                        <p v-else class="text-xs text-gray-300 italic">Žiadne filtre priradené</p>
                    </div>
                </div>

                <!-- Two-column panels -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                    <!-- ═══ VARIANTS PANEL ══════════════════════════════════ -->
                    <div class="flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-semibold text-gray-800">Varianty</h2>
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                    {{ localVariants.length }}
                                </span>
                            </div>
                            <button
                                @click="suggestVariants"
                                :disabled="variantState.loading"
                                class="inline-flex items-center gap-1.5 rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-60 transition-colors"
                            >
                                <svg v-if="variantState.loading" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                                <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                                {{ variantState.loading ? 'AI analyzuje...' : 'AI navrhnúť' }}
                            </button>
                        </div>

                        <!-- Variant suggestions -->
                        <div v-if="variantSuggestions.length > 0" class="border-b border-amber-100 bg-amber-50 px-5 py-3">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-semibold text-amber-700">
                                    AI navrhuje {{ variantSuggestions.length }} skupín
                                </p>
                                <button @click="variantSuggestions = []" class="text-amber-400 hover:text-amber-600 transition-colors">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <div v-for="(group, gi) in variantSuggestions" :key="gi" class="mb-2 last:mb-0">
                                <p class="text-xs text-amber-600 mb-1">Skupina {{ gi + 1 }}:</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <label
                                        v-for="item in group"
                                        :key="item.product_id"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-white px-2.5 py-1 text-xs font-medium text-amber-800 cursor-pointer hover:bg-amber-50 transition-colors"
                                    >
                                        <input type="checkbox" v-model="item.selected" class="h-3 w-3 rounded text-blue-600" />
                                        {{ item.name }}
                                    </label>
                                </div>
                            </div>

                            <div class="flex gap-2 mt-3">
                                <button
                                    @click="saveSelectedVariants"
                                    :disabled="variantState.saving"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                                >
                                    <svg v-if="variantState.saving" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    Uložiť vybrané
                                </button>
                                <button @click="variantSuggestions = []"
                                        class="rounded-md px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                    Zrušiť
                                </button>
                            </div>
                        </div>

                        <!-- Existing variants list -->
                        <div class="flex-1 divide-y divide-gray-50">
                            <div v-for="v in localVariants" :key="v.product_id"
                                 class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors">
                                <div class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-md bg-gray-100 border border-gray-100">
                                    <img v-if="v.image" :src="v.image" :alt="v.name"
                                         class="h-full w-full object-contain"
                                         @error="$event.target.style.display='none'" />
                                </div>
                                <Link :href="route('products.show', v.product_id)"
                                      class="flex-1 text-sm text-gray-700 hover:text-indigo-600 transition-colors truncate">
                                    {{ v.name }}
                                </Link>
                                <button @click="removeVariant(v.product_id)"
                                        class="flex-shrink-0 rounded-full p-1 text-gray-200 hover:text-red-500 hover:bg-red-50 transition-colors">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <div v-if="localVariants.length === 0" class="flex flex-col items-center justify-center px-5 py-10 text-gray-300">
                                <svg class="h-8 w-8 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                                <p class="text-sm">Žiadne varianty</p>
                                <p class="text-xs mt-1">Použite AI na nájdenie variantov</p>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ RELATED PANEL ════════════════════════════════════ -->
                    <div class="flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-semibold text-gray-800">Súvisiace produkty</h2>
                                <span class="inline-flex items-center rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-700">
                                    {{ localRelated.length }}
                                </span>
                            </div>
                            <button
                                @click="suggestRelated"
                                :disabled="relatedState.loading"
                                class="inline-flex items-center gap-1.5 rounded-md border border-teal-200 bg-teal-50 px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-100 disabled:opacity-60 transition-colors"
                            >
                                <svg v-if="relatedState.loading" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                                <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                </svg>
                                {{ relatedState.loading ? 'AI analyzuje...' : 'AI navrhnúť' }}
                            </button>
                        </div>

                        <!-- Related suggestions -->
                        <div v-if="relatedSuggestions.length > 0" class="border-b border-teal-100 bg-teal-50 px-5 py-3">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-semibold text-teal-700">
                                    AI navrhuje {{ relatedSuggestions.length }} produktov
                                </p>
                                <button @click="relatedSuggestions = []" class="text-teal-400 hover:text-teal-600 transition-colors">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <div class="flex flex-wrap gap-1.5 mb-3">
                                <label
                                    v-for="item in relatedSuggestions"
                                    :key="item.product_id"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-teal-200 bg-white px-2.5 py-1 text-xs font-medium text-teal-800 cursor-pointer hover:bg-teal-50 transition-colors"
                                >
                                    <input type="checkbox" v-model="item.selected" class="h-3 w-3 rounded text-teal-600" />
                                    {{ item.name }}
                                </label>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    @click="saveSelectedRelated"
                                    :disabled="relatedState.saving"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-teal-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-teal-700 disabled:opacity-50 transition-colors"
                                >
                                    <svg v-if="relatedState.saving" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    Uložiť vybrané
                                </button>
                                <button @click="relatedSuggestions = []"
                                        class="rounded-md px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                    Zrušiť
                                </button>
                            </div>
                        </div>

                        <!-- Existing related list -->
                        <div class="flex-1 divide-y divide-gray-50">
                            <div v-for="r in localRelated" :key="r.product_id"
                                 class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors">
                                <div class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-md bg-gray-100 border border-gray-100">
                                    <img v-if="r.image" :src="r.image" :alt="r.name"
                                         class="h-full w-full object-contain"
                                         @error="$event.target.style.display='none'" />
                                </div>
                                <Link :href="route('products.show', r.product_id)"
                                      class="flex-1 text-sm text-gray-700 hover:text-indigo-600 transition-colors truncate">
                                    {{ r.name }}
                                </Link>
                                <button @click="removeRelated(r.product_id)"
                                        class="flex-shrink-0 rounded-full p-1 text-gray-200 hover:text-red-500 hover:bg-red-50 transition-colors">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <div v-if="localRelated.length === 0" class="flex flex-col items-center justify-center px-5 py-10 text-gray-300">
                                <svg class="h-8 w-8 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                </svg>
                                <p class="text-sm">Žiadne súvisiace produkty</p>
                                <p class="text-xs mt-1">Použite AI na nájdenie súvisiacich produktov</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    product:  Object,
    variants: Array,
    related:  Array,
});

const localVariants = ref([...props.variants]);
const localRelated  = ref([...props.related]);

// ─── Toast ────────────────────────────────────────────────────────────────────

const toast = ref({ show: false, message: '', type: 'success' });
let toastTimer = null;

function showToast(message, type = 'success') {
    clearTimeout(toastTimer);
    toast.value = { show: true, message, type };
    toastTimer = setTimeout(() => { toast.value.show = false; }, 3500);
}

// ─── Variants ─────────────────────────────────────────────────────────────────

const variantState       = ref({ loading: false, saving: false });
const variantSuggestions = ref([]);

async function suggestVariants() {
    variantState.value.loading = true;
    variantSuggestions.value   = [];
    try {
        const res = await axios.post(route('products.suggest-variants', props.product.product_id));
        const groups = res.data.groups ?? [];
        variantSuggestions.value = groups.map(group =>
            group.filter(p => p.product_id !== props.product.product_id)
                 .map(p => ({ ...p, selected: true }))
        ).filter(g => g.length > 0);

        if (variantSuggestions.value.length === 0) {
            showToast('AI nenašla žiadne varianty', 'error');
        }
    } catch {
        showToast('Chyba pri AI návrhu variantov', 'error');
    } finally {
        variantState.value.loading = false;
    }
}

async function saveSelectedVariants() {
    const variantIds = variantSuggestions.value
        .flatMap(group => group.filter(p => p.selected).map(p => p.product_id));

    if (variantIds.length === 0) {
        showToast('Žiadne varianty na uloženie');
        return;
    }

    variantState.value.saving = true;
    try {
        await axios.post(route('products.save-variants', props.product.product_id), { variant_ids: variantIds });
        // Refresh variants list
        const res = await axios.get(route('products.variants', props.product.product_id));
        localVariants.value = res.data;
        variantSuggestions.value = [];
        showToast(`Uložené — ${variantIds.length} variantov`);
    } catch {
        showToast('Chyba pri ukladaní variantov', 'error');
    } finally {
        variantState.value.saving = false;
    }
}

async function removeVariant(variantId) {
    try {
        await axios.delete(route('products.remove-variant', { productId: props.product.product_id, variantId }));
        localVariants.value = localVariants.value.filter(v => v.product_id !== variantId);
        showToast('Variant odstránený');
    } catch {
        showToast('Chyba pri odstraňovaní variantu', 'error');
    }
}

// ─── Related ──────────────────────────────────────────────────────────────────

const relatedState       = ref({ loading: false, saving: false });
const relatedSuggestions = ref([]);

async function suggestRelated() {
    relatedState.value.loading = true;
    relatedSuggestions.value   = [];
    try {
        const res = await axios.post(route('products.suggest-related', props.product.product_id));
        const related = res.data.related ?? [];
        relatedSuggestions.value = related
            .filter(p => p.product_id !== props.product.product_id)
            .map(p => ({ ...p, selected: true }));

        if (relatedSuggestions.value.length === 0) {
            showToast('AI nenašla žiadne súvisiace produkty', 'error');
        }
    } catch {
        showToast('Chyba pri AI návrhu súvisiacich produktov', 'error');
    } finally {
        relatedState.value.loading = false;
    }
}

async function saveSelectedRelated() {
    const relatedIds = relatedSuggestions.value
        .filter(p => p.selected)
        .map(p => p.product_id);

    if (relatedIds.length === 0) {
        showToast('Žiadne produkty na uloženie');
        return;
    }

    relatedState.value.saving = true;
    try {
        await axios.post(route('products.save-related', props.product.product_id), { related_ids: relatedIds });
        const res = await axios.get(route('products.related', props.product.product_id));
        localRelated.value = res.data;
        relatedSuggestions.value = [];
        showToast(`Uložené — ${relatedIds.length} súvisiacich produktov`);
    } catch {
        showToast('Chyba pri ukladaní súvisiacich produktov', 'error');
    } finally {
        relatedState.value.saving = false;
    }
}

async function removeRelated(relatedId) {
    try {
        await axios.delete(route('products.remove-related', { productId: props.product.product_id, relatedId }));
        localRelated.value = localRelated.value.filter(r => r.product_id !== relatedId);
        showToast('Súvisiaci produkt odstránený');
    } catch {
        showToast('Chyba pri odstraňovaní súvisiaceho produktu', 'error');
    }
}
</script>
