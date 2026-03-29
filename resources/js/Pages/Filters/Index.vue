<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Filtre</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-4">

                <!-- Search input -->
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Hľadaj filter skupinu alebo filter..."
                        class="w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-9 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Results summary when searching -->
                <p v-if="searchQuery" class="text-xs text-gray-400 -mt-1">
                    {{ totalMatchCount }} výsledkov
                    <span v-if="totalMatchCount === 0"> – skúste iné hľadanie</span>
                </p>

                <!-- Groups -->
                <div class="space-y-3">
                    <div
                        v-for="group in visibleGroups"
                        :key="group.filter_group_id"
                        class="overflow-hidden rounded-lg bg-white shadow"
                    >
                        <!-- Group header -->
                        <button
                            class="flex w-full items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition-colors"
                            @click="toggleGroup(group.filter_group_id)"
                        >
                            <span class="text-base font-semibold text-gray-900"
                                  v-html="highlight(group.description?.name ?? 'Skupina #' + group.filter_group_id)">
                            </span>
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-400">
                                    {{ searchQuery ? group.matchingFilters.length : group.filters.length }} filtrov
                                </span>
                                <svg
                                    class="h-5 w-5 text-gray-400 transition-transform"
                                    :class="{ 'rotate-180': openGroups.has(group.filter_group_id) }"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </button>

                        <!-- Filters list -->
                        <div v-show="openGroups.has(group.filter_group_id)" class="border-t border-gray-100">
                            <div
                                v-for="filter in (searchQuery ? group.matchingFilters : group.filters)"
                                :key="filter.filter_id"
                                class="flex items-center justify-between border-b border-gray-50 px-6 py-3 last:border-0 hover:bg-indigo-50 cursor-pointer transition-colors"
                                @click="goToFilter(filter.filter_id)"
                            >
                                <span class="text-sm text-gray-700"
                                      v-html="highlight(filter.description?.name ?? 'Filter #' + filter.filter_id)">
                                </span>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">
                                        detail →
                                    </span>
                                    <svg class="h-4 w-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </div>
                            <div v-if="(searchQuery ? group.matchingFilters : group.filters).length === 0"
                                 class="px-6 py-4 text-sm text-gray-400 italic">
                                Žiadne filtre v tejto skupine.
                            </div>
                        </div>
                    </div>

                    <div v-if="visibleGroups.length === 0" class="rounded-lg bg-white p-8 text-center text-gray-400 shadow">
                        <span v-if="searchQuery">Žiadne výsledky pre „{{ searchQuery }}".</span>
                        <span v-else>Žiadne filter skupiny.</span>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    filterGroups: Array,
});

// ─── Search ───────────────────────────────────────────────────────────────────

const searchQuery = ref('');
const openGroups  = reactive(new Set());

function normalize(str) {
    return (str ?? '').toLowerCase();
}

const visibleGroups = computed(() => {
    const q = normalize(searchQuery.value.trim());
    if (!q) return props.filterGroups;

    return props.filterGroups
        .map(group => {
            const groupName    = group.description?.name ?? '';
            const groupMatches = normalize(groupName).includes(q);

            const matchingFilters = group.filters.filter(f =>
                normalize(f.description?.name ?? '').includes(q)
            );

            if (!groupMatches && matchingFilters.length === 0) return null;

            return { ...group, matchingFilters };
        })
        .filter(Boolean);
});

// Total matching filters count
const totalMatchCount = computed(() => {
    const q = normalize(searchQuery.value.trim());
    if (!q) return 0;
    return visibleGroups.value.reduce((sum, g) => sum + g.matchingFilters.length, 0);
});

// Auto-open groups that have matches when searching
watch(visibleGroups, (groups) => {
    if (!searchQuery.value.trim()) return;
    groups.forEach(g => openGroups.add(g.filter_group_id));
});

// ─── Highlight ────────────────────────────────────────────────────────────────

function highlight(text) {
    const q = searchQuery.value.trim();
    if (!q || !text) return text;
    const re = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(re, '<mark class="bg-yellow-200 rounded px-0.5">$1</mark>');
}

// ─── Navigation ───────────────────────────────────────────────────────────────

function toggleGroup(id) {
    if (openGroups.has(id)) openGroups.delete(id);
    else                     openGroups.add(id);
}

function goToFilter(filterId) {
    router.visit(route('filters.show', filterId));
}
</script>
