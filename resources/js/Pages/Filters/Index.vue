<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Filtre</h2>
                <button
                    @click="openGroupModal()"
                    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nová skupina
                </button>
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
                 class="fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg bg-green-600 px-4 py-3 text-sm font-medium text-white shadow-lg">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                {{ toast.message }}
            </div>
        </Transition>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

                <!-- Search -->
                <div class="relative mb-4">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Hľadaj skupinu alebo filter..."
                        class="w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-4 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                    />
                    <button v-if="searchQuery" @click="searchQuery = ''"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <p v-if="searchQuery" class="mb-3 text-xs text-gray-400">
                    {{ visibleCount }} výsledkov
                </p>

                <div class="space-y-3">
                    <div
                        v-for="group in filteredGroups"
                        :key="group.filter_group_id"
                        class="overflow-hidden rounded-lg bg-white shadow"
                    >
                        <!-- Group header -->
                        <div class="flex items-center">
                            <button
                                class="flex flex-1 items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition-colors"
                                @click="toggleGroup(group.filter_group_id)"
                            >
                                <span class="text-base font-semibold text-gray-900"
                                      v-html="highlightGroup(group.description?.name ?? 'Skupina #' + group.filter_group_id)">
                                </span>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm text-gray-400">{{ group.matchedFilters.length }} filtrov</span>
                                    <svg
                                        class="h-5 w-5 text-gray-400 transition-transform"
                                        :class="{ 'rotate-180': openGroups.has(group.filter_group_id) }"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </button>
                            <!-- Add filter button -->
                            <button
                                @click.stop="openFilterModal(group)"
                                class="flex items-center gap-1.5 self-stretch border-l border-gray-100 px-4 text-sm text-gray-400 hover:bg-indigo-50 hover:text-indigo-600 transition-colors"
                                title="Pridať filter do skupiny"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span class="hidden sm:inline text-xs font-medium">Pridať filter</span>
                            </button>
                        </div>

                        <!-- Filters list -->
                        <div v-show="openGroups.has(group.filter_group_id)" class="border-t border-gray-100">
                            <div
                                v-for="filter in group.matchedFilters"
                                :key="filter.filter_id"
                                class="flex items-center justify-between border-b border-gray-50 px-6 py-3 last:border-0 hover:bg-indigo-50 cursor-pointer transition-colors"
                                @click="goToFilter(filter.filter_id)"
                            >
                                <span
                                    class="text-sm text-gray-700"
                                    v-html="highlightFilter(filter.description?.name ?? 'Filter #' + filter.filter_id)"
                                ></span>
                                <svg class="h-4 w-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                            <div v-if="group.matchedFilters.length === 0" class="px-6 py-4 text-sm text-gray-400 italic">
                                {{ searchQuery ? 'Žiadne zhody v tejto skupine.' : 'Žiadne filtre v tejto skupine.' }}
                            </div>
                        </div>
                    </div>

                    <div v-if="filteredGroups.length === 0" class="rounded-lg bg-white p-8 text-center text-gray-400 shadow">
                        Žiadne výsledky pre „{{ searchQuery }}"
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ Modal: Nová filter skupina ══════════════════════════════════════ -->
        <Teleport to="body">
            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0"
                        enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showGroupModal"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
                     @click.self="closeGroupModal">
                    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl mx-4">
                        <h3 class="mb-1 text-lg font-semibold text-gray-900">Nová filter skupina</h3>
                        <p class="mb-4 text-sm text-gray-500">Vytvorí novú prázdnu skupinu filtrov.</p>
                        <form @submit.prevent="submitGroup">
                            <div class="mb-4">
                                <label class="mb-1.5 block text-sm font-medium text-gray-700">Názov skupiny</label>
                                <input
                                    ref="groupNameRef"
                                    v-model="groupForm.name"
                                    type="text"
                                    placeholder="napr. Formát, Farba, Gramáž..."
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                    :class="{ 'border-red-400 ring-1 ring-red-400': groupForm.errors.name }"
                                    maxlength="64"
                                />
                                <p v-if="groupForm.errors.name" class="mt-1 text-xs text-red-500">{{ groupForm.errors.name }}</p>
                            </div>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="closeGroupModal"
                                        class="rounded-md px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">
                                    Zrušiť
                                </button>
                                <button type="submit"
                                        :disabled="groupForm.processing || !groupForm.name.trim()"
                                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                    <svg v-if="groupForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    Vytvoriť skupinu
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ══ Modal: Nový filter ═══════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0"
                        enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showFilterModal"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
                     @click.self="closeFilterModal">
                    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl mx-4">
                        <h3 class="mb-1 text-lg font-semibold text-gray-900">Nový filter</h3>
                        <p class="mb-4 text-sm text-gray-500">
                            Skupina:
                            <span class="font-medium text-gray-700">
                                {{ selectedGroup?.description?.name ?? 'Skupina #' + selectedGroup?.filter_group_id }}
                            </span>
                        </p>
                        <form @submit.prevent="submitFilter">
                            <div class="mb-3">
                                <label class="mb-1.5 block text-sm font-medium text-gray-700">Názov filtra</label>
                                <input
                                    ref="filterNameRef"
                                    v-model="filterForm.name"
                                    type="text"
                                    placeholder="napr. A4, Červená, 80g/m²..."
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                    :class="{ 'border-red-400 ring-1 ring-red-400': filterForm.errors.name }"
                                    maxlength="64"
                                />
                                <p v-if="filterForm.errors.name" class="mt-1 text-xs text-red-500">{{ filterForm.errors.name }}</p>
                            </div>
                            <p class="mb-4 text-xs text-gray-400">
                                Po vytvorení budeš presmerovaný na detail filtra, kde môžeš priradiť produkty a kategórie.
                            </p>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="closeFilterModal"
                                        class="rounded-md px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">
                                    Zrušiť
                                </button>
                                <button type="submit"
                                        :disabled="filterForm.processing || !filterForm.name.trim()"
                                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                    <svg v-if="filterForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    Vytvoriť a otvoriť →
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    filterGroups: Array,
});

const page = usePage();

// ─── Toast ────────────────────────────────────────────────────────────────────
const toast = reactive({ show: false, message: '' });
function showToast(msg) {
    Object.assign(toast, { show: true, message: msg });
    setTimeout(() => { toast.show = false; }, 3500);
}
// Flash správa po Inertia redirecte
watch(() => page.props.flash?.success, (msg) => { if (msg) showToast(msg); }, { immediate: true });

// ─── Vyhľadávanie ─────────────────────────────────────────────────────────────
const searchQuery = ref('');

const filteredGroups = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.filterGroups.map(g => ({ ...g, matchedFilters: g.filters }));

    const result = [];
    for (const group of props.filterGroups) {
        const groupMatch = (group.description?.name ?? '').toLowerCase().includes(q);
        const matchedFilters = group.filters.filter(f =>
            (f.description?.name ?? '').toLowerCase().includes(q)
        );
        if (groupMatch || matchedFilters.length > 0) {
            result.push({ ...group, matchedFilters: groupMatch ? group.filters : matchedFilters });
        }
    }
    return result;
});

const visibleCount = computed(() =>
    filteredGroups.value.reduce((sum, g) => sum + g.matchedFilters.length, 0)
);

function highlight(text, q) {
    if (!q || !text) return text;
    const re = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return String(text).replace(re, '<mark class="bg-yellow-200 rounded">$1</mark>');
}
function highlightGroup(text)  { return highlight(text, searchQuery.value.trim()); }
function highlightFilter(text) { return highlight(text, searchQuery.value.trim()); }

// ─── Accordion ────────────────────────────────────────────────────────────────
const openGroups = reactive(new Set());

function toggleGroup(id) {
    if (openGroups.has(id)) openGroups.delete(id);
    else openGroups.add(id);
}

// Auto-otvoriť skupiny s výsledkami pri hľadaní
watch(searchQuery, (q) => {
    if (q.trim()) filteredGroups.value.forEach(g => openGroups.add(g.filter_group_id));
});

function goToFilter(filterId) {
    router.visit(route('filters.show', filterId));
}

// ─── Modal: Nová filter skupina ───────────────────────────────────────────────
const showGroupModal = ref(false);
const groupNameRef   = ref(null);
const groupForm      = useForm({ name: '' });

function openGroupModal() {
    groupForm.reset();
    showGroupModal.value = true;
    nextTick(() => groupNameRef.value?.focus());
}
function closeGroupModal() {
    showGroupModal.value = false;
    groupForm.reset();
}
function submitGroup() {
    groupForm.post(route('filter-groups.store'), {
        onSuccess: () => closeGroupModal(),
    });
}

// ─── Modal: Nový filter ───────────────────────────────────────────────────────
const showFilterModal = ref(false);
const filterNameRef   = ref(null);
const selectedGroup   = ref(null);
const filterForm      = useForm({ name: '', filter_group_id: null });

function openFilterModal(group) {
    selectedGroup.value        = group;
    filterForm.reset();
    filterForm.filter_group_id = group.filter_group_id;
    showFilterModal.value      = true;
    nextTick(() => filterNameRef.value?.focus());
}
function closeFilterModal() {
    showFilterModal.value = false;
    filterForm.reset();
    selectedGroup.value = null;
}
function submitFilter() {
    // Server presmeruje priamo na filters.show nového filtra
    filterForm.post(route('filters.store'));
}

// ─── Escape ───────────────────────────────────────────────────────────────────
function onKeydown(e) {
    if (e.key === 'Escape') {
        if (showFilterModal.value) { closeFilterModal(); return; }
        if (showGroupModal.value)  { closeGroupModal(); }
    }
}
onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>
