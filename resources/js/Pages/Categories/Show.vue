<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <Link :href="route('categories.index')" class="hover:text-indigo-600 transition-colors">Kategórie</Link>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-semibold text-gray-800">{{ category.name }}</span>
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

                <!-- Header info + batch button + search -->
                <div class="mb-5 flex flex-wrap items-center gap-3">

                    <!-- Left: count + batch button -->
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <p class="text-sm text-gray-500 whitespace-nowrap">
                            <span class="font-semibold text-gray-700">{{ products.length }}</span> produktov
                            <span v-if="unassignedCount > 0" class="text-amber-600">
                                · <span class="font-semibold">{{ unassignedCount }}</span> bez filtrov
                            </span>
                            <span v-else class="text-green-600"> · všetky majú filtre ✓</span>
                        </p>

                        <button
                            v-if="unassignedCount > 0 || batch.running"
                            @click="startBatchAssign"
                            :disabled="batch.running"
                            class="inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-60 transition-colors whitespace-nowrap"
                        >
                            <svg v-if="batch.running" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            <span v-if="batch.running">{{ batch.done }}&nbsp;/&nbsp;{{ batch.total }}</span>
                            <span v-else>AI: priradiť filtre ({{ unassignedCount }})</span>
                        </button>
                    </div>

                    <!-- Progress bar -->
                    <div v-if="batch.running" class="w-full order-last">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full bg-indigo-400 transition-all duration-300"
                                     :style="{ width: (batch.total ? (batch.done / batch.total * 100) : 0) + '%' }">
                                </div>
                            </div>
                            <span class="text-xs text-gray-400 whitespace-nowrap">{{ batch.assigned }} priradených</span>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="relative w-72 flex-shrink-0">
                        <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Hľadaj produkt..."
                            class="w-full rounded-md border border-gray-200 py-2 pl-9 pr-9 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                        />
                        <button v-if="searchQuery" @click="searchQuery = ''"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Product grid -->
                <div v-if="filteredProducts.length === 0"
                     class="flex flex-col items-center justify-center rounded-lg border border-gray-200 bg-white py-20 text-gray-300">
                    <svg class="h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                    </svg>
                    <p class="text-sm">{{ searchQuery ? 'Žiadne produkty nenájdené' : 'Žiadne produkty v tejto kategórii' }}</p>
                </div>

                <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.product_id"
                        class="group relative flex flex-col rounded-lg border border-gray-200 bg-white overflow-hidden shadow-sm hover:shadow-md hover:border-indigo-300 transition-all cursor-pointer"
                        @click="openModal(product)"
                    >
                        <!-- Filter status dot -->
                        <div class="absolute top-2 right-2 z-10">
                            <div class="h-2.5 w-2.5 rounded-full shadow-sm"
                                 :class="hasFilters(product.product_id) ? 'bg-green-400' : 'bg-gray-300'"
                                 :title="hasFilters(product.product_id) ? 'Má priradené filtre' : 'Bez filtrov'">
                            </div>
                        </div>

                        <!-- Image -->
                        <div class="overflow-hidden bg-white flex items-center justify-center" style="height: 180px">
                            <img v-if="product.image" :src="product.image" :alt="product.name"
                                 class="max-h-full max-w-full object-contain transition-transform group-hover:scale-105"
                                 @error="$event.target.style.display='none'" />
                            <div v-else class="flex h-full items-center justify-center w-full bg-gray-50">
                                <svg class="h-8 w-8 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="flex flex-col p-2 flex-1 border-t border-gray-100">
                            <p class="text-xs font-medium text-gray-800 leading-tight line-clamp-2"
                               v-html="highlight(product.name)"></p>
                        </div>

                        <!-- Hover overlay -->
                        <div class="absolute inset-0 flex items-center justify-center bg-indigo-600 bg-opacity-0 group-hover:bg-opacity-5 transition-all">
                            <span class="opacity-0 group-hover:opacity-100 transition-opacity rounded-full bg-indigo-600 px-3 py-1 text-xs font-medium text-white shadow">
                                Upraviť priradenia
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ Review modal (batch AI results) ══════════════════════════════ -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="review.open"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4"
                 @click.self="review.open = false">

                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="review.open"
                         class="relative w-full max-w-2xl rounded-xl bg-white shadow-2xl flex flex-col"
                         style="max-height: 88vh">

                        <!-- Header -->
                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4 flex-shrink-0">
                            <div>
                                <h2 class="text-base font-semibold text-gray-800">Skontroluj návrhy AI</h2>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    <span class="font-medium text-gray-600">{{ reviewActiveItems.length }}</span> produktov ·
                                    <span class="font-medium text-gray-600">{{ reviewTotalFilters }}</span> filtrov
                                    <span v-if="reviewLowConfidenceCount > 0" class="text-amber-600">
                                        · {{ reviewLowConfidenceCount }} s niz. istotou
                                    </span>
                                </p>
                            </div>
                            <button @click="review.open = false"
                                    class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition-colors mt-0.5">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Product list -->
                        <div class="flex-1 overflow-y-auto divide-y divide-gray-50">
                            <div v-for="(item, itemIdx) in review.items" :key="item.product.product_id"
                                 class="flex items-start gap-3 px-6 py-3 transition-opacity"
                                 :class="item.skip || item.filters.length === 0 ? 'opacity-30' : ''">

                                <!-- Thumbnail -->
                                <div class="h-11 w-11 flex-shrink-0 overflow-hidden rounded-md bg-gray-100 border border-gray-100">
                                    <img v-if="item.product.image"
                                         :src="item.product.image" :alt="item.product.name"
                                         class="h-full w-full object-contain"
                                         @error="$event.target.style.display='none'" />
                                </div>

                                <!-- Name + description + filters -->
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 leading-tight truncate mb-1">
                                        {{ item.product.name }}
                                    </p>
                                    <div v-if="item.product.description" class="mb-1.5">
                                        <p class="text-xs text-gray-400 leading-relaxed"
                                           :class="item.descExpanded ? '' : 'line-clamp-2'">
                                            {{ item.product.description }}
                                        </p>
                                        <button
                                            @click="item.descExpanded = !item.descExpanded"
                                            class="mt-0.5 text-xs text-indigo-400 hover:text-indigo-600 transition-colors"
                                        >
                                            {{ item.descExpanded ? 'Skryť ▲' : 'Zobraziť celý popis ▼' }}
                                        </button>
                                    </div>
                                    <div v-if="item.filters.length > 0" class="flex flex-wrap gap-1.5">
                                        <span
                                            v-for="(f, fi) in item.filters"
                                            :key="fi"
                                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="confidenceChipClass(f.confidence, f.is_new)"
                                        >
                                            <span class="opacity-60 text-xs">{{ f.group_name }}:</span>
                                            {{ f.name }}
                                            <span v-if="f.is_new" class="font-bold text-xs">+</span>
                                            <span v-if="f.confidence === 'low'" class="opacity-50 text-xs ml-0.5">AI</span>
                                            <button
                                                @click="removeReviewFilter(itemIdx, fi)"
                                                class="ml-0.5 rounded-full transition-colors hover:text-red-500 text-current opacity-40 hover:opacity-100"
                                                title="Odstrániť"
                                            >
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </div>
                                    <p v-else class="text-xs text-gray-300 italic">všetky filtre odstránené</p>
                                </div>

                                <!-- Skip entire product toggle -->
                                <button
                                    @click="item.skip = !item.skip"
                                    class="flex-shrink-0 mt-0.5 rounded-md px-2 py-1 text-xs font-medium transition-colors"
                                    :class="item.skip
                                        ? 'bg-gray-100 text-gray-400 hover:bg-gray-200'
                                        : 'text-gray-300 hover:text-red-400 hover:bg-red-50'"
                                    :title="item.skip ? 'Obnoviť' : 'Preskočiť produkt'"
                                >
                                    {{ item.skip ? 'Obnoviť' : 'Preskočiť' }}
                                </button>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 flex-shrink-0">
                            <button @click="review.open = false"
                                    class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                Zrušiť
                            </button>
                            <button
                                @click="confirmReview"
                                :disabled="review.saving || reviewActiveItems.length === 0"
                                class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                            >
                                <svg v-if="review.saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                                <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Potvrdiť a uložiť ({{ reviewActiveItems.length }} produktov)
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>

        <!-- ══ Modal ══════════════════════════════════════════════════════════ -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="modal.open"
                 class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-40 p-4"
                 @click.self="closeModal">

                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="modal.open"
                         class="relative w-full max-w-lg rounded-xl bg-white shadow-2xl flex flex-col"
                         style="max-height: 90vh">

                        <!-- Header -->
                        <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4 flex-shrink-0">
                            <div class="h-12 w-12 flex-shrink-0 overflow-hidden rounded-md bg-gray-100">
                                <img v-if="modal.product?.image"
                                     :src="modal.product.image"
                                     :alt="modal.product.name"
                                     class="h-full w-full object-cover"
                                     @error="$event.target.style.display='none'" />
                                <div v-else class="flex h-full items-center justify-center">
                                    <svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">{{ modal.product?.name }}</p>
                                <p class="text-xs text-gray-400">Kategórie &amp; filtre</p>
                            </div>
                            <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Loading -->
                        <div v-if="modal.loading" class="flex items-center justify-center py-12">
                            <svg class="h-6 w-6 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                        </div>

                        <template v-else>
                            <!-- Tabs -->
                            <div class="flex border-b border-gray-100 flex-shrink-0">
                                <button
                                    v-for="tab in tabs"
                                    :key="tab.key"
                                    @click="activeTab = tab.key"
                                    class="flex-1 px-4 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px"
                                    :class="activeTab === tab.key
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'"
                                >
                                    {{ tab.label }}
                                    <span v-if="tab.key !== 'description'"
                                          class="ml-1.5 rounded-full px-1.5 text-xs"
                                          :class="activeTab === tab.key ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-500'">
                                        {{ tab.key === 'categories' ? modal.assignedCategories.length : modal.assignedFilters.length }}
                                    </span>
                                </button>
                            </div>

                            <!-- ── Tab: Kategórie ── -->
                            <div v-show="activeTab === 'categories'" class="flex flex-col overflow-hidden flex-1">

                                <!-- Typeahead -->
                                <div class="flex-shrink-0 border-b border-gray-100 px-5 py-4" ref="catWrapRef">
                                    <label class="mb-1.5 block text-xs font-medium text-gray-500">Pridať kategóriu</label>
                                    <div class="relative">
                                        <div
                                            class="flex w-full cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors"
                                            :class="showCatMenu ? 'border-indigo-400 ring-1 ring-indigo-400' : 'border-gray-200 hover:border-gray-300'"
                                            @click="openCatMenu"
                                        >
                                            <svg class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            <input v-if="showCatMenu" ref="catInputRef" v-model="catQuery" type="text"
                                                   placeholder="Hľadaj kategóriu..."
                                                   class="flex-1 bg-transparent outline-none placeholder-gray-400 text-gray-700 text-sm"
                                                   @keydown.enter.prevent="addFirstCat" @click.stop />
                                            <span v-else class="flex-1 text-gray-400 text-sm">Vybrať kategóriu...</span>
                                            <svg class="h-4 w-4 flex-shrink-0 text-gray-400 transition-transform"
                                                 :class="{ 'rotate-180': showCatMenu }"
                                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                        <div v-if="showCatMenu"
                                             class="absolute left-0 right-0 top-full z-30 mt-1 max-h-52 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg">
                                            <div v-if="availableCats.length === 0" class="px-3 py-4 text-sm text-center text-gray-400 italic">
                                                {{ catQuery ? 'Žiadne zhody' : 'Všetky kategórie sú priradené' }}
                                            </div>
                                            <div v-for="cat in availableCats" :key="cat.category_id"
                                                 @click="addCat(cat)"
                                                 class="flex items-center cursor-pointer hover:bg-indigo-50 transition-colors"
                                                 :style="{ paddingLeft: (12 + cat.depth * 14) + 'px', paddingRight: '12px',
                                                           paddingTop: cat.depth === 0 ? '8px' : '6px', paddingBottom: cat.depth === 0 ? '8px' : '6px' }">
                                                <span v-if="cat.depth > 0" class="mr-1.5 text-gray-300 text-xs select-none">
                                                    {{ '└' + '─'.repeat(cat.depth - 1) }}
                                                </span>
                                                <span class="text-sm truncate"
                                                      :class="cat.depth === 0 ? 'font-semibold text-gray-700' : 'text-gray-600'"
                                                      v-html="hlCat(cat.name)"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Assigned categories -->
                                <div class="flex-1 overflow-y-auto px-5 py-3">
                                    <div v-if="modal.assignedCategories.length === 0"
                                         class="flex flex-col items-center justify-center py-8 text-gray-300">
                                        <svg class="h-8 w-8 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                        </svg>
                                        <p class="text-sm">Žiadne kategórie</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <span v-for="cat in modal.assignedCategories" :key="cat.category_id"
                                              class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">
                                            {{ cat.name }}
                                            <button @click="removeCat(cat.category_id)"
                                                    class="rounded-full text-indigo-400 hover:text-indigo-700 hover:bg-indigo-100 transition-colors p-0.5">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Tab: Filtre ── -->
                            <div v-show="activeTab === 'filters'" class="flex flex-col overflow-hidden flex-1">

                                <!-- Two cascading dropdowns: Group → Filter -->
                                <div class="flex-shrink-0 border-b border-gray-100 px-5 py-4 space-y-3" ref="filterWrapRef">

                                    <!-- 1. Skupina -->
                                    <div>
                                        <label class="mb-1.5 block text-xs font-medium text-gray-500">Skupina filtrov</label>
                                        <div class="relative">
                                            <div
                                                class="flex w-full cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors"
                                                :class="showGroupMenu ? 'border-indigo-400 ring-1 ring-indigo-400' : 'border-gray-200 hover:border-gray-300'"
                                                @click="openGroupMenu"
                                            >
                                                <svg class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                          d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                                </svg>
                                                <input v-if="showGroupMenu" ref="groupInputRef" v-model="groupQuery" type="text"
                                                       placeholder="Hľadaj skupinu..."
                                                       class="flex-1 bg-transparent outline-none placeholder-gray-400 text-gray-700 text-sm"
                                                       @click.stop />
                                                <span v-else class="flex-1 text-sm" :class="selectedGroup ? 'text-gray-800 font-medium' : 'text-gray-400'">
                                                    {{ selectedGroup ? selectedGroup : 'Vybrať skupinu...' }}
                                                </span>
                                                <button v-if="selectedGroup && !showGroupMenu"
                                                        @click.stop="clearGroup"
                                                        class="text-gray-300 hover:text-gray-500 transition-colors">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                                <svg v-else class="h-4 w-4 flex-shrink-0 text-gray-400 transition-transform"
                                                     :class="{ 'rotate-180': showGroupMenu }"
                                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </div>
                                            <div v-if="showGroupMenu"
                                                 class="absolute left-0 right-0 top-full z-30 mt-1 max-h-48 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg">
                                                <div v-if="availableGroups.length === 0" class="px-3 py-4 text-sm text-center text-gray-400 italic">Žiadne zhody</div>
                                                <div v-for="gName in availableGroups" :key="gName"
                                                     @click="selectGroup(gName)"
                                                     class="flex items-center gap-2 px-3 py-2.5 cursor-pointer hover:bg-indigo-50 transition-colors"
                                                     :class="selectedGroup === gName ? 'bg-indigo-50' : ''">
                                                    <span class="text-sm font-medium text-gray-700" v-html="hlGroup(gName)"></span>
                                                    <svg v-if="selectedGroup === gName" class="ml-auto h-4 w-4 text-indigo-500 flex-shrink-0"
                                                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 2. Filter (len ak je vybraná skupina) -->
                                    <div :class="selectedGroup ? '' : 'opacity-40 pointer-events-none'">
                                        <label class="mb-1.5 block text-xs font-medium text-gray-500">Filter</label>
                                        <div class="relative">
                                            <div
                                                class="flex w-full cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors"
                                                :class="showFilterMenu ? 'border-indigo-400 ring-1 ring-indigo-400' : 'border-gray-200 hover:border-gray-300'"
                                                @click="openFilterMenu"
                                            >
                                                <svg class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                          d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                                                </svg>
                                                <input v-if="showFilterMenu" ref="filterInputRef" v-model="filterQuery" type="text"
                                                       placeholder="Hľadaj filter..."
                                                       class="flex-1 bg-transparent outline-none placeholder-gray-400 text-gray-700 text-sm"
                                                       @keydown.enter.prevent="addFirstFilter" @click.stop />
                                                <span v-else class="flex-1 text-gray-400 text-sm">
                                                    {{ selectedGroup ? 'Vybrať filter...' : 'Najprv vyber skupinu' }}
                                                </span>
                                                <svg class="h-4 w-4 flex-shrink-0 text-gray-400 transition-transform"
                                                     :class="{ 'rotate-180': showFilterMenu }"
                                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </div>
                                            <div v-if="showFilterMenu"
                                                 class="absolute left-0 right-0 top-full z-30 mt-1 max-h-48 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg">
                                                <div v-if="availableFilters.length === 0" class="px-3 py-4 text-sm text-center text-gray-400 italic">
                                                    {{ filterQuery ? 'Žiadne zhody' : 'Všetky filtre z tejto skupiny sú priradené' }}
                                                </div>
                                                <div v-for="f in availableFilters" :key="f.filter_id"
                                                     @click="addFilter(f)"
                                                     class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-indigo-50 transition-colors">
                                                    <span class="text-sm text-gray-700 truncate" v-html="hlFilter(f.name)"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- AI suggest button -->
                                <div class="flex-shrink-0 px-5 py-2 border-b border-gray-100">
                                    <button
                                        @click="suggestFilters"
                                        :disabled="modal.suggesting"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50 transition-colors"
                                    >
                                        <svg v-if="modal.suggesting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                        </svg>
                                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                        </svg>
                                        {{ modal.suggesting ? 'AI analyzuje produkt...' : 'Navrhnúť filtre pomocou AI' }}
                                    </button>
                                </div>

                                <!-- AI suggestions: existing filters -->
                                <div v-if="aiSuggestions.length > 0"
                                     class="flex-shrink-0 border-b border-amber-100 bg-amber-50 px-5 py-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-xs font-semibold text-amber-700 flex items-center gap-1.5">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                            </svg>
                                            AI návrh ({{ aiSuggestions.length }})
                                        </p>
                                        <div class="flex items-center gap-2">
                                            <button @click="acceptAllSuggestions"
                                                    class="text-xs font-medium text-amber-700 hover:text-amber-900 underline transition-colors">
                                                Prijať všetky
                                            </button>
                                            <button @click="aiSuggestions = []"
                                                    class="text-amber-400 hover:text-amber-600 transition-colors">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span
                                            v-for="f in aiSuggestions"
                                            :key="f.filter_id"
                                            @click="acceptSuggestion(f)"
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium cursor-pointer hover:opacity-80 transition-all"
                                            :class="confidenceChipClass(f.confidence, false)"
                                            title="Klikni pre pridanie"
                                        >
                                            <span class="opacity-60 text-xs">{{ f.group_name }}:</span>
                                            {{ f.name }}
                                            <span v-if="f.confidence === 'low'" class="opacity-50 text-xs">AI</span>
                                            <svg class="h-3 w-3 opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>

                                <!-- AI suggestions: new filters to create -->
                                <div v-if="aiNewFilters.length > 0"
                                     class="flex-shrink-0 border-b border-emerald-100 bg-emerald-50 px-5 py-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-xs font-semibold text-emerald-700 flex items-center gap-1.5">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Nové filtre na vytvorenie ({{ aiNewFilters.length }})
                                        </p>
                                        <button @click="aiNewFilters = []"
                                                class="text-emerald-400 hover:text-emerald-600 transition-colors">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button
                                            v-for="(f, idx) in aiNewFilters"
                                            :key="idx"
                                            @click="createAndAssignFilter(f, idx)"
                                            :disabled="f.creating"
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium disabled:opacity-60 hover:opacity-80 transition-all"
                                            :class="confidenceChipClass(f.confidence, true)"
                                            title="Klikni pre vytvorenie a priradenie"
                                        >
                                            <svg v-if="f.creating" class="h-3 w-3 animate-spin opacity-50 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                            </svg>
                                            <span class="opacity-60 text-xs">{{ f.group_name }}:</span>
                                            {{ f.name }}
                                            <span v-if="f.confidence === 'low'" class="opacity-50 text-xs">AI</span>
                                            <span v-if="!f.creating" class="font-bold text-xs">+</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Assigned filters -->
                                <div class="flex-1 overflow-y-auto px-5 py-3">
                                    <div v-if="modal.assignedFilters.length === 0"
                                         class="flex flex-col items-center justify-center py-8 text-gray-300">
                                        <svg class="h-8 w-8 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                                        </svg>
                                        <p class="text-sm">Žiadne filtre</p>
                                    </div>

                                    <!-- Grouped assigned filters -->
                                    <template v-for="(groupFilters, groupName) in groupedAssignedFilters" :key="groupName">
                                        <p class="mb-1.5 mt-3 first:mt-0 text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ groupName }}</p>
                                        <div class="flex flex-wrap gap-2 mb-1">
                                            <span v-for="f in groupFilters" :key="f.filter_id"
                                                  class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-3 py-1 text-xs font-medium text-violet-700">
                                                {{ f.name }}
                                                <button @click="removeFilter(f.filter_id)"
                                                        class="rounded-full text-violet-400 hover:text-violet-700 hover:bg-violet-100 transition-colors p-0.5">
                                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- ── Tab: Popis ── -->
                            <div v-show="activeTab === 'description'" class="flex-1 overflow-y-auto px-5 py-4">
                                <!-- Image large -->
                                <div v-if="modal.product?.image" class="mb-4 flex justify-center">
                                    <img :src="modal.product.image" :alt="modal.product.name"
                                         class="max-h-64 max-w-full object-contain rounded-lg"
                                         @error="$event.target.style.display='none'" />
                                </div>
                                <!-- Description HTML -->
                                <div v-if="modal.product?.description_html"
                                     class="prose prose-sm max-w-none text-gray-700 text-sm leading-relaxed"
                                     v-html="modal.product.description_html">
                                </div>
                                <div v-else class="flex flex-col items-center justify-center py-10 text-gray-300">
                                    <svg class="h-8 w-8 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <p class="text-sm">Žiadny popis</p>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-5 py-4 flex-shrink-0">
                                <button @click="closeModal"
                                        class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                    {{ activeTab === 'description' ? 'Zavrieť' : 'Zrušiť' }}
                                </button>
                                <button v-if="activeTab !== 'description'"
                                        @click="saveAll"
                                        :disabled="modal.saving"
                                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                    <svg v-if="modal.saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    Uložiť
                                </button>
                            </div>
                        </template>
                    </div>
                </Transition>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    category:   Object,
    products:   Array,
    categories: Array,
    filters:    Array,
});

// ─── Filter status tracking ───────────────────────────────────────────────────
// Initialized from server data; updated locally after batch/manual assignment

const hasFiltersSet = ref(new Set(
    props.products.filter(p => p.has_filters).map(p => p.product_id)
));

function hasFilters(productId) {
    return hasFiltersSet.value.has(productId);
}

const unassignedCount = computed(
    () => props.products.filter(p => !hasFiltersSet.value.has(p.product_id)).length
);

// ─── Batch AI assignment ──────────────────────────────────────────────────────

const batch = ref({ running: false, total: 0, done: 0 });

// Review modal — shown after batch finishes, before anything is saved
const review = ref({ open: false, items: [], saving: false });

const reviewActiveItems = computed(() =>
    review.value.items.filter(item => !item.skip && item.filters.length > 0)
);
const reviewTotalFilters = computed(() =>
    reviewActiveItems.value.reduce((sum, item) => sum + item.filters.length, 0)
);
const reviewLowConfidenceCount = computed(() =>
    reviewActiveItems.value.reduce((sum, item) =>
        sum + item.filters.filter(f => f.confidence === 'low').length, 0)
);

function confidenceChipClass(confidence, isNew) {
    if (confidence === 'high') {
        return isNew
            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
            : 'bg-violet-50 text-violet-700 border border-violet-100';
    }
    if (confidence === 'medium') {
        return 'bg-amber-50 text-amber-700 border border-amber-200';
    }
    // low
    return 'bg-gray-50 text-gray-600 border border-dashed border-gray-300';
}

async function startBatchAssign() {
    const targets = props.products.filter(p => !hasFiltersSet.value.has(p.product_id));
    if (targets.length === 0) return;

    batch.value = { running: true, total: targets.length, done: 0 };
    const collected = [];

    for (const product of targets) {
        try {
            const res = await axios.post(route('products.suggest-filters', product.product_id));
            const filters = (res.data.suggestions ?? []).map(s => ({ ...s }));
            if (filters.length > 0) {
                collected.push({ product, filters, skip: false, descExpanded: false });
            }
        } catch { /* skip */ }

        batch.value.done++;
    }

    batch.value.running = false;

    if (collected.length === 0) {
        showToast('Nenašli sa žiadne filtre na priradenie');
        return;
    }

    review.value = { open: true, items: collected, saving: false };
}

function removeReviewFilter(itemIdx, filterIdx) {
    review.value.items[itemIdx].filters.splice(filterIdx, 1);
}

async function confirmReview() {
    review.value.saving = true;
    let saved = 0;

    for (const item of reviewActiveItems.value) {
        try {
            const filterIds = [];

            for (const f of item.filters) {
                if (f.is_new) {
                    // Create new filter value, assign to product, get its ID
                    const res = await axios.post(
                        route('products.create-filter', item.product.product_id),
                        { group_name: f.group_name, filter_name: f.name }
                    );
                    filterIds.push(res.data.filter_id);
                } else {
                    filterIds.push(f.filter_id);
                }
            }

            if (filterIds.length > 0) {
                await axios.post(
                    route('products.sync-filters', item.product.product_id),
                    { filter_ids: filterIds }
                );
                hasFiltersSet.value.add(item.product.product_id);
                saved++;
            }
        } catch { /* skip */ }
    }

    review.value.saving = false;
    review.value.open   = false;
    showToast(`Uložené — ${saved} produktom priradené filtre`);
}

// ─── Tabs ─────────────────────────────────────────────────────────────────────

const tabs = [
    { key: 'filters',     label: 'Filtre' },
    { key: 'categories',  label: 'Kategórie' },
    { key: 'description', label: 'Popis' },
];
const activeTab = ref('filters');

// ─── Product search ───────────────────────────────────────────────────────────

const searchQuery = ref('');

const filteredProducts = computed(() => {
    if (!searchQuery.value.trim()) return props.products;
    const q = searchQuery.value.toLowerCase();
    return props.products.filter(p =>
        p.name.toLowerCase().includes(q) ||
        (p.description && p.description.toLowerCase().includes(q))
    );
});

function highlight(text) {
    if (!searchQuery.value.trim() || !text) return text;
    const escaped = searchQuery.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="bg-yellow-100 text-yellow-800 rounded px-0.5">$1</mark>');
}

// ─── Toast ────────────────────────────────────────────────────────────────────

const toast = ref({ show: false, message: '', type: 'success' });
let toastTimer = null;

function showToast(message, type = 'success') {
    clearTimeout(toastTimer);
    toast.value = { show: true, message, type };
    toastTimer = setTimeout(() => { toast.value.show = false; }, 3000);
}

// ─── Lookup maps ──────────────────────────────────────────────────────────────

const categoryMap = computed(() => Object.fromEntries(props.categories.map(c => [c.category_id, c])));
const filterMap   = computed(() => Object.fromEntries(props.filters.map(f => [f.filter_id, f])));

// ─── Modal ────────────────────────────────────────────────────────────────────

const modal = ref({
    open: false,
    product: null,
    loading: false,
    saving: false,
    assignedCategories: [],
    assignedFilters: [],
});

async function openModal(product) {
    modal.value = { open: true, product, loading: true, saving: false, suggesting: false, assignedCategories: [], assignedFilters: [] };
    aiSuggestions.value = [];
    aiNewFilters.value  = [];
    activeTab.value = 'filters';
    catQuery.value = '';
    filterQuery.value = '';
    groupQuery.value = '';
    selectedGroup.value = null;
    showCatMenu.value = false;
    showFilterMenu.value = false;
    showGroupMenu.value = false;

    try {
        const [catRes, filterRes] = await Promise.all([
            axios.get(route('products.categories', product.product_id)),
            axios.get(route('products.filters', product.product_id)),
        ]);
        modal.value.assignedCategories = catRes.data.map(id => categoryMap.value[id]).filter(Boolean);
        modal.value.assignedFilters    = filterRes.data.map(id => filterMap.value[id]).filter(Boolean);
    } catch {
        showToast('Nepodarilo sa načítať dáta produktu', 'error');
    } finally {
        modal.value.loading = false;
    }
}

function closeModal() {
    modal.value.open = false;
    showCatMenu.value = false;
    showFilterMenu.value = false;
    showGroupMenu.value = false;
}

async function saveAll() {
    modal.value.saving = true;
    const catIds    = modal.value.assignedCategories.map(c => c.category_id);
    const filterIds = modal.value.assignedFilters.map(f => f.filter_id);

    try {
        await Promise.all([
            axios.post(route('products.sync-categories', modal.value.product.product_id), { category_ids: catIds }),
            axios.post(route('products.sync-filters', modal.value.product.product_id), { filter_ids: filterIds }),
        ]);
        // Update local filter status dot
        if (filterIds.length > 0) {
            hasFiltersSet.value.add(modal.value.product.product_id);
        }
        showToast(`Uložené — ${catIds.length} kategórií, ${filterIds.length} filtrov`);
        closeModal();
    } catch {
        showToast('Nepodarilo sa uložiť', 'error');
    } finally {
        modal.value.saving = false;
    }
}

// ─── Category typeahead ───────────────────────────────────────────────────────

const showCatMenu = ref(false);
const catQuery    = ref('');
const catInputRef = ref(null);
const catWrapRef  = ref(null);

const availableCats = computed(() => {
    const ids = new Set(modal.value.assignedCategories.map(c => c.category_id));
    const list = props.categories.filter(c => !ids.has(c.category_id));
    if (!catQuery.value.trim()) return list;
    const q = catQuery.value.toLowerCase();
    return list.filter(c => c.name.toLowerCase().includes(q));
});

function hlCat(text) {
    if (!catQuery.value.trim() || !text) return text;
    const escaped = catQuery.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="bg-yellow-100 text-yellow-800 rounded px-0.5">$1</mark>');
}

async function openCatMenu() {
    showCatMenu.value = true;
    await nextTick();
    catInputRef.value?.focus();
}

function addCat(cat) {
    modal.value.assignedCategories.push(cat);
    catQuery.value = '';
    showCatMenu.value = false;
}

function addFirstCat() {
    if (availableCats.value.length > 0) addCat(availableCats.value[0]);
}

function removeCat(id) {
    modal.value.assignedCategories = modal.value.assignedCategories.filter(c => c.category_id !== id);
}

// ─── Filter typeahead — Group ─────────────────────────────────────────────────

const showGroupMenu  = ref(false);
const groupQuery     = ref('');
const groupInputRef  = ref(null);
const selectedGroup  = ref(null);   // string: group_name

// All unique group names across all filters
const allGroupNames = computed(() => {
    const names = [...new Set(props.filters.map(f => f.group_name))].sort();
    return names;
});

const availableGroups = computed(() => {
    if (!groupQuery.value.trim()) return allGroupNames.value;
    const q = groupQuery.value.toLowerCase();
    return allGroupNames.value.filter(n => n.toLowerCase().includes(q));
});

function hlGroup(text) {
    if (!groupQuery.value.trim() || !text) return text;
    const escaped = groupQuery.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="bg-yellow-100 text-yellow-800 rounded px-0.5">$1</mark>');
}

async function openGroupMenu() {
    showGroupMenu.value = true;
    await nextTick();
    groupInputRef.value?.focus();
}

function selectGroup(name) {
    selectedGroup.value = name;
    groupQuery.value = '';
    showGroupMenu.value = false;
    // reset filter dropdown
    filterQuery.value = '';
    showFilterMenu.value = false;
}

function clearGroup() {
    selectedGroup.value = null;
    groupQuery.value = '';
    filterQuery.value = '';
    showFilterMenu.value = false;
}

// ─── Filter typeahead — Filter ────────────────────────────────────────────────

const showFilterMenu  = ref(false);
const filterQuery     = ref('');
const filterInputRef  = ref(null);
const filterWrapRef   = ref(null);

const availableFilters = computed(() => {
    const ids = new Set(modal.value.assignedFilters.map(f => f.filter_id));
    // Only filters from the selected group
    const list = props.filters.filter(f => !ids.has(f.filter_id) && f.group_name === selectedGroup.value);
    if (!filterQuery.value.trim()) return list;
    const q = filterQuery.value.toLowerCase();
    return list.filter(f => f.name.toLowerCase().includes(q));
});

const groupedAssignedFilters = computed(() => {
    const groups = {};
    for (const f of modal.value.assignedFilters) {
        if (!groups[f.group_name]) groups[f.group_name] = [];
        groups[f.group_name].push(f);
    }
    return groups;
});

function hlFilter(text) {
    if (!filterQuery.value.trim() || !text) return text;
    const escaped = filterQuery.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="bg-yellow-100 text-yellow-800 rounded px-0.5">$1</mark>');
}

async function openFilterMenu() {
    if (!selectedGroup.value) return;
    showFilterMenu.value = true;
    await nextTick();
    filterInputRef.value?.focus();
}

function addFilter(f) {
    modal.value.assignedFilters.push(f);
    filterQuery.value = '';
    showFilterMenu.value = false;
}

function addFirstFilter() {
    if (availableFilters.value.length > 0) addFilter(availableFilters.value[0]);
}

function removeFilter(id) {
    modal.value.assignedFilters = modal.value.assignedFilters.filter(f => f.filter_id !== id);
}

// ─── AI suggest ───────────────────────────────────────────────────────────────

const aiSuggestions = ref([]);
const aiNewFilters  = ref([]);

async function suggestFilters() {
    modal.value.suggesting = true;
    aiSuggestions.value = [];
    aiNewFilters.value  = [];
    try {
        const res = await axios.post(route('products.suggest-filters', modal.value.product.product_id));
        const suggestions = res.data.suggestions ?? [];
        const assignedIds = new Set(modal.value.assignedFilters.map(f => f.filter_id));

        // Existing filters (not yet assigned)
        aiSuggestions.value = suggestions
            .filter(s => !s.is_new && s.filter_id && !assignedIds.has(s.filter_id))
            .map(s => ({ ...filterMap.value[s.filter_id], confidence: s.confidence }))
            .filter(Boolean);

        // New filters to create
        aiNewFilters.value = suggestions
            .filter(s => s.is_new)
            .map(s => ({ ...s, creating: false }));

        if (aiSuggestions.value.length === 0 && aiNewFilters.value.length === 0) {
            showToast('Nenašli sa vhodné filtre');
        }
    } catch {
        showToast('Návrh filtrov zlyhal', 'error');
    } finally {
        modal.value.suggesting = false;
    }
}

async function createAndAssignFilter(f, idx) {
    aiNewFilters.value[idx].creating = true;
    try {
        const res = await axios.post(
            route('products.create-filter', modal.value.product.product_id),
            { group_name: f.group_name, filter_name: f.name }
        );
        // Add to assigned filters and update filterMap for future use
        const newFilter = {
            filter_id:  res.data.filter_id,
            group_id:   res.data.group_id,
            group_name: res.data.group_name,
            name:       res.data.name,
        };
        modal.value.assignedFilters.push(newFilter);
        // Remove from new suggestions
        aiNewFilters.value.splice(idx, 1);
        showToast(`Filter „${f.name}" bol vytvorený a priradený`);
    } catch {
        aiNewFilters.value[idx].creating = false;
        showToast(`Nepodarilo sa vytvoriť filter „${f.name}"`, 'error');
    }
}

function acceptSuggestion(f) {
    modal.value.assignedFilters.push(f);
    aiSuggestions.value = aiSuggestions.value.filter(s => s.filter_id !== f.filter_id);
}

function acceptAllSuggestions() {
    for (const f of aiSuggestions.value) {
        modal.value.assignedFilters.push(f);
    }
    aiSuggestions.value = [];
}

// ─── Outside click ────────────────────────────────────────────────────────────

function handleOutsideClick(e) {
    if (catWrapRef.value && !catWrapRef.value.contains(e.target)) showCatMenu.value = false;
    if (filterWrapRef.value && !filterWrapRef.value.contains(e.target)) {
        showFilterMenu.value = false;
        showGroupMenu.value = false;
    }
}
onMounted(() => document.addEventListener('mousedown', handleOutsideClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', handleOutsideClick));
</script>
