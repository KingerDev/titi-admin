<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Kategórie</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

                <!-- Search -->
                <div class="relative mb-4">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Hľadaj kategóriu..."
                        class="w-full rounded-md border border-gray-200 py-2 pl-9 pr-9 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                    />
                    <button v-if="searchQuery" @click="searchQuery = ''"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Category list -->
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div v-if="filteredCategories.length === 0"
                         class="flex flex-col items-center justify-center py-16 text-gray-300">
                        <svg class="h-10 w-10 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        <p class="text-sm">Žiadne kategórie</p>
                    </div>

                    <Link
                        v-for="cat in filteredCategories"
                        :key="cat.category_id"
                        :href="route('categories.show', cat.category_id)"
                        class="flex items-center gap-3 border-b border-gray-50 last:border-0 hover:bg-indigo-50 transition-colors group"
                        :style="{ paddingLeft: (16 + cat.depth * 16) + 'px', paddingRight: '16px', paddingTop: cat.depth === 0 ? '12px' : '8px', paddingBottom: cat.depth === 0 ? '12px' : '8px' }"
                    >
                        <!-- Depth indicator -->
                        <span v-if="cat.depth > 0" class="flex-shrink-0 text-gray-300 text-xs select-none">
                            {{ '└' + '─'.repeat(cat.depth - 1) }}
                        </span>

                        <!-- Category icon -->
                        <svg class="h-4 w-4 flex-shrink-0 text-gray-300 group-hover:text-indigo-400 transition-colors"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>

                        <!-- Name -->
                        <span class="flex-1 min-w-0 truncate text-sm"
                              :class="cat.depth === 0 ? 'font-semibold text-gray-800' : 'text-gray-600'"
                              v-html="highlight(cat.name)">
                        </span>

                        <!-- Product count -->
                        <span v-if="cat.product_count > 0"
                              class="flex-shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 group-hover:bg-indigo-100 group-hover:text-indigo-600 transition-colors">
                            {{ cat.product_count }}
                        </span>
                        <span v-else class="flex-shrink-0 text-xs text-gray-300">0</span>

                        <!-- Arrow -->
                        <svg class="h-4 w-4 flex-shrink-0 text-gray-300 group-hover:text-indigo-400 transition-colors"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </Link>
                </div>

                <p class="mt-3 text-xs text-gray-400 text-right">{{ filteredCategories.length }} kategórií</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    categories: Array,
});

const searchQuery = ref('');

const filteredCategories = computed(() => {
    if (!searchQuery.value.trim()) return props.categories;
    const q = searchQuery.value.toLowerCase();
    return props.categories.filter(cat => cat.name.toLowerCase().includes(q));
});

function highlight(text) {
    if (!searchQuery.value.trim()) return text;
    const escaped = searchQuery.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="bg-yellow-100 text-yellow-800 rounded px-0.5">$1</mark>');
}
</script>
