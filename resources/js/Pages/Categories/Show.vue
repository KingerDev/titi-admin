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
                            :disabled="batch.running || batchVariants.running || batchRelated.running"
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

                        <!-- Batch: varianty -->
                        <button
                            @click="startBatchVariants"
                            :disabled="batch.running || batchVariants.running || batchRelated.running"
                            class="inline-flex items-center gap-1.5 rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-60 transition-colors whitespace-nowrap"
                        >
                            <svg v-if="batchVariants.running" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                            <span v-if="batchVariants.running">{{ batchVariants.done }}&nbsp;/&nbsp;{{ batchVariants.total }}</span>
                            <span v-else>AI: varianty</span>
                        </button>

                        <!-- Batch: súvisiace -->
                        <button
                            @click="startBatchRelated"
                            :disabled="batch.running || batchVariants.running || batchRelated.running"
                            class="inline-flex items-center gap-1.5 rounded-md border border-teal-200 bg-teal-50 px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-100 disabled:opacity-60 transition-colors whitespace-nowrap"
                        >
                            <svg v-if="batchRelated.running" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                            <span v-if="batchRelated.running">{{ batchRelated.done }}&nbsp;/&nbsp;{{ batchRelated.total }}</span>
                            <span v-else>AI: súvisiace</span>
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

                <!-- Filter bar -->
                <div class="mb-4 flex flex-wrap items-end gap-3">

                    <!-- Filter by group (searchable) -->
                    <div class="relative flex-1 min-w-[200px] max-w-xs" ref="barGroupWrapRef">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Skupina filtrov</label>
                        <div class="flex w-full items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm cursor-pointer transition-colors"
                             :class="showBarGroupMenu ? 'border-indigo-400 ring-1 ring-indigo-400' : 'border-gray-200 hover:border-gray-300'"
                             @click="openBarGroupMenu">
                            <svg class="h-3.5 w-3.5 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                            </svg>
                            <input v-if="showBarGroupMenu" ref="barGroupInputRef" v-model="barGroupQuery" type="text"
                                   placeholder="Hľadaj skupinu..."
                                   class="flex-1 bg-transparent outline-none placeholder-gray-400 text-gray-700 text-sm min-w-0"
                                   @click.stop />
                            <span v-else class="flex-1 truncate text-sm" :class="filterByGroup ? 'text-gray-800 font-medium' : 'text-gray-400'">
                                {{ filterByGroup || 'Vybrať skupinu...' }}
                            </span>
                            <button v-if="filterByGroup && !showBarGroupMenu" @click.stop="filterByGroup = ''; filterByValue = ''; filterByMode = 'has'"
                                    class="text-gray-300 hover:text-gray-500 transition-colors flex-shrink-0">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div v-if="showBarGroupMenu"
                             class="absolute left-0 right-0 top-full z-30 mt-1 max-h-56 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg">
                            <div v-if="barGroupFiltered.length === 0" class="px-3 py-3 text-sm text-center text-gray-400 italic">Žiadne zhody</div>
                            <div v-for="gn in barGroupFiltered" :key="gn"
                                 @click="selectBarGroup(gn)"
                                 class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-indigo-50 transition-colors text-sm"
                                 :class="filterByGroup === gn ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700'">
                                {{ gn }}
                                <svg v-if="filterByGroup === gn" class="ml-auto h-3.5 w-3.5 text-indigo-500 flex-shrink-0"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Filter by specific value (searchable, only when group selected) -->
                    <div v-if="filterByGroup" class="relative flex-1 min-w-[200px] max-w-xs" ref="barValueWrapRef">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Hodnota filtra</label>
                        <div class="flex w-full items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm cursor-pointer transition-colors"
                             :class="showBarValueMenu ? 'border-indigo-400 ring-1 ring-indigo-400' : 'border-gray-200 hover:border-gray-300'"
                             @click="openBarValueMenu">
                            <svg class="h-3.5 w-3.5 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                            </svg>
                            <input v-if="showBarValueMenu" ref="barValueInputRef" v-model="barValueQuery" type="text"
                                   placeholder="Hľadaj filter..."
                                   class="flex-1 bg-transparent outline-none placeholder-gray-400 text-gray-700 text-sm min-w-0"
                                   @click.stop />
                            <span v-else class="flex-1 truncate text-sm" :class="barValueLabel ? 'text-gray-800 font-medium' : 'text-gray-400'">
                                {{ barValueLabel || 'Všetky hodnoty...' }}
                            </span>
                            <button v-if="filterByValue && !showBarValueMenu" @click.stop="filterByValue = ''"
                                    class="text-gray-300 hover:text-gray-500 transition-colors flex-shrink-0">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div v-if="showBarValueMenu"
                             class="absolute left-0 right-0 top-full z-30 mt-1 max-h-56 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg">
                            <div v-if="barValueFiltered.length === 0" class="px-3 py-3 text-sm text-center text-gray-400 italic">Žiadne zhody</div>
                            <div v-for="f in barValueFiltered" :key="f.filter_id"
                                 @click="selectBarValue(f)"
                                 class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-indigo-50 transition-colors text-sm"
                                 :class="Number(filterByValue) === f.filter_id ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700'">
                                {{ f.name }}
                                <svg v-if="Number(filterByValue) === f.filter_id" class="ml-auto h-3.5 w-3.5 text-indigo-500 flex-shrink-0"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Has / Has not toggle -->
                    <div v-if="filterByGroup" class="flex items-center gap-2 pb-0.5">
                        <label class="flex items-center gap-1.5 cursor-pointer text-sm"
                               :class="filterByMode === 'has' ? 'text-green-700 font-medium' : 'text-gray-500'">
                            <input type="radio" v-model="filterByMode" value="has"
                                   class="text-green-600 focus:ring-green-500 h-3.5 w-3.5" />
                            Má
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer text-sm"
                               :class="filterByMode === 'has_not' ? 'text-red-700 font-medium' : 'text-gray-500'">
                            <input type="radio" v-model="filterByMode" value="has_not"
                                   class="text-red-600 focus:ring-red-500 h-3.5 w-3.5" />
                            Nemá
                        </label>
                    </div>

                    <!-- Active filter badge -->
                    <div v-if="filterByGroup" class="flex items-center pb-0.5">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                              :class="filterByMode === 'has' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                            {{ filteredProducts.length }} / {{ products.length }}
                        </span>
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
                        <!-- Filter status dot + variant/related badges -->
                        <div class="absolute top-2 right-2 z-10 flex flex-col items-end gap-1">
                            <div class="h-2.5 w-2.5 rounded-full shadow-sm"
                                 :class="hasFilters(product.product_id) ? 'bg-green-400' : 'bg-gray-300'"
                                 :title="hasFilters(product.product_id) ? 'Má priradené filtre' : 'Bez filtrov'">
                            </div>
                            <span v-if="(variantCounts[product.product_id] ?? 0) > 0"
                                  class="inline-flex items-center rounded-full bg-blue-100 px-1.5 py-0.5 text-xs font-semibold text-blue-700 leading-none"
                                  :title="`${variantCounts[product.product_id]} variantov`">
                                V{{ variantCounts[product.product_id] }}
                            </span>
                            <span v-if="(relatedCounts[product.product_id] ?? 0) > 0"
                                  class="inline-flex items-center rounded-full bg-teal-100 px-1.5 py-0.5 text-xs font-semibold text-teal-700 leading-none"
                                  :title="`${relatedCounts[product.product_id]} súvisiacich`">
                                S{{ relatedCounts[product.product_id] }}
                            </span>
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
                        <div class="absolute inset-0 flex items-center justify-center gap-2 bg-indigo-600 bg-opacity-0 group-hover:bg-opacity-5 transition-all">
                            <span class="opacity-0 group-hover:opacity-100 transition-opacity rounded-full bg-indigo-600 px-3 py-1 text-xs font-medium text-white shadow">
                                Filtre &amp; Kategórie
                            </span>
                            <button
                                @click.stop="openVariantsModal(product, $event)"
                                class="opacity-0 group-hover:opacity-100 transition-opacity rounded-full bg-blue-600 px-3 py-1 text-xs font-medium text-white shadow"
                            >
                                Varianty &amp; Súvisiace
                            </button>
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

        <!-- ══ Review modal — Varianty ══════════════════════════════════════ -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="reviewVariants.open"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4"
                 @click.self="reviewVariants.open = false">
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="reviewVariants.open"
                         class="relative w-full max-w-2xl rounded-xl bg-white shadow-2xl flex flex-col"
                         style="max-height: 88vh">
                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4 flex-shrink-0">
                            <div>
                                <h2 class="text-base font-semibold text-gray-800">Navrhnuté varianty</h2>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Skontroluj skupiny variantov a ulož tie, ktoré sú správne
                                </p>
                            </div>
                            <button @click="reviewVariants.open = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition-colors mt-0.5">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto divide-y divide-gray-50 px-6 py-2">
                            <div v-for="(item, itemIdx) in reviewVariants.items" :key="itemIdx"
                                 class="py-3 transition-opacity"
                                 :class="item.skip ? 'opacity-30' : ''">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Skupina</p>
                                    <button @click="item.skip = !item.skip"
                                            class="text-xs font-medium rounded-md px-2 py-0.5 transition-colors"
                                            :class="item.skip ? 'bg-gray-100 text-gray-400 hover:bg-gray-200' : 'text-gray-300 hover:text-red-400 hover:bg-red-50'">
                                        {{ item.skip ? 'Obnoviť' : 'Preskočiť' }}
                                    </button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <div v-for="(product, pIdx) in item.group" :key="product.product_id"
                                         class="flex items-center gap-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-1.5">
                                        <img v-if="product.image" :src="product.image" class="h-8 w-8 object-contain rounded" @error="$event.target.style.display='none'" />
                                        <span class="text-xs font-medium text-blue-800">{{ product.name }}</span>
                                        <button @click="item.group.splice(pIdx, 1)"
                                                class="text-blue-300 hover:text-red-400 transition-colors">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="reviewVariants.items.length === 0" class="py-12 text-center text-gray-300 text-sm">
                                Nenašli sa žiadne skupiny variantov
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 flex-shrink-0">
                            <button @click="reviewVariants.open = false"
                                    class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                Zrušiť
                            </button>
                            <button @click="confirmVariants"
                                    :disabled="reviewVariants.saving"
                                    class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                <svg v-if="reviewVariants.saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                                <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Uložiť varianty ({{ reviewVariants.items.filter(i => !i.skip && i.group.length >= 2).length }} skupín)
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>

        <!-- ══ Review modal — Súvisiace ══════════════════════════════════════ -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="reviewRelated.open"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4"
                 @click.self="reviewRelated.open = false">
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="reviewRelated.open"
                         class="relative w-full max-w-2xl rounded-xl bg-white shadow-2xl flex flex-col"
                         style="max-height: 88vh">
                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4 flex-shrink-0">
                            <div>
                                <h2 class="text-base font-semibold text-gray-800">Navrhnuté súvisiace produkty</h2>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Skontroluj návrhy a ulož len relevantné
                                </p>
                            </div>
                            <button @click="reviewRelated.open = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition-colors mt-0.5">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto divide-y divide-gray-50 px-6 py-2">
                            <div v-for="(item, itemIdx) in reviewRelated.items" :key="item.product.product_id"
                                 class="py-3 transition-opacity"
                                 :class="item.skip || item.related.length === 0 ? 'opacity-30' : ''">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <img v-if="item.product.image" :src="item.product.image" class="h-7 w-7 object-contain rounded" @error="$event.target.style.display='none'" />
                                        <p class="text-xs font-semibold text-gray-700 truncate">{{ item.product.name }}</p>
                                    </div>
                                    <button @click="item.skip = !item.skip"
                                            class="text-xs font-medium rounded-md px-2 py-0.5 transition-colors flex-shrink-0"
                                            :class="item.skip ? 'bg-gray-100 text-gray-400 hover:bg-gray-200' : 'text-gray-300 hover:text-red-400 hover:bg-red-50'">
                                        {{ item.skip ? 'Obnoviť' : 'Preskočiť' }}
                                    </button>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <span v-for="(rel, rIdx) in item.related" :key="rel.product_id"
                                          class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700 border border-teal-100">
                                        {{ rel.name }}
                                        <button @click="item.related.splice(rIdx, 1)"
                                                class="text-teal-300 hover:text-red-400 transition-colors">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </span>
                                    <p v-if="item.related.length === 0" class="text-xs text-gray-300 italic">všetky odstránené</p>
                                </div>
                            </div>
                            <div v-if="reviewRelated.items.length === 0" class="py-12 text-center text-gray-300 text-sm">
                                Nenašli sa žiadne súvisiace produkty
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 flex-shrink-0">
                            <button @click="reviewRelated.open = false"
                                    class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                Zrušiť
                            </button>
                            <button @click="confirmRelated"
                                    :disabled="reviewRelated.saving"
                                    class="inline-flex items-center gap-2 rounded-md bg-teal-600 px-5 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-50 transition-colors">
                                <svg v-if="reviewRelated.saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                                <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Uložiť ({{ reviewRelated.items.filter(i => !i.skip && i.related.length > 0).length }} produktov)
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
                         class="relative w-full max-w-xl rounded-xl bg-white shadow-2xl flex flex-col"
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
                            <!-- Product description (always visible, collapsible) -->
                            <div v-if="modal.product?.description_html" class="border-b border-gray-100 flex-shrink-0">
                                <button
                                    @click="descExpanded = !descExpanded"
                                    class="flex items-center gap-2 w-full px-5 py-2.5 text-xs font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors"
                                >
                                    <svg class="h-3.5 w-3.5 transition-transform" :class="descExpanded ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                    Popis produktu
                                </button>
                                <div v-show="descExpanded" class="px-5 pb-3 max-h-52 overflow-y-auto">
                                    <div class="prose prose-sm max-w-none text-gray-600 text-xs leading-relaxed"
                                         v-html="modal.product.description_html">
                                    </div>
                                </div>
                            </div>

                            <!-- Tabs -->
                            <div class="flex border-b border-gray-100 flex-shrink-0 overflow-x-auto">
                                <button
                                    v-for="tab in tabs"
                                    :key="tab.key"
                                    @click="activeTab = tab.key"
                                    class="flex-1 min-w-0 px-3 py-2.5 text-xs font-medium transition-colors border-b-2 -mb-px whitespace-nowrap"
                                    :class="activeTab === tab.key
                                        ? (tab.key === 'variants' ? 'border-blue-500 text-blue-600' : tab.key === 'related' ? 'border-teal-500 text-teal-600' : 'border-indigo-500 text-indigo-600')
                                        : 'border-transparent text-gray-500 hover:text-gray-700'"
                                >
                                    {{ tab.label }}
                                    <span class="ml-1 rounded-full px-1.5 text-xs"
                                          :class="activeTab === tab.key
                                            ? (tab.key === 'variants' ? 'bg-blue-100 text-blue-600' : tab.key === 'related' ? 'bg-teal-100 text-teal-600' : 'bg-indigo-100 text-indigo-600')
                                            : 'bg-gray-100 text-gray-500'">
                                        {{ tabCount(tab.key) }}
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
                                                <div v-if="availableFilters.length === 0 && !canCreateFilter" class="px-3 py-4 text-sm text-center text-gray-400 italic">
                                                    {{ filterQuery ? 'Žiadne zhody' : 'Všetky filtre z tejto skupiny sú priradené' }}
                                                </div>
                                                <div v-for="f in availableFilters" :key="f.filter_id"
                                                     @click="addFilter(f)"
                                                     class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-indigo-50 transition-colors">
                                                    <span class="text-sm text-gray-700 truncate" v-html="hlFilter(f.name)"></span>
                                                </div>
                                                <!-- Create new filter option -->
                                                <div v-if="canCreateFilter"
                                                     @click="createNewFilterInline"
                                                     class="flex items-center gap-2 px-3 py-2.5 cursor-pointer hover:bg-emerald-50 transition-colors border-t border-gray-100">
                                                    <svg v-if="creatingInlineFilter" class="h-4 w-4 animate-spin text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                                    </svg>
                                                    <svg v-else class="h-4 w-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                    <span class="text-sm font-medium text-emerald-700">
                                                        Vytvoriť „<span class="font-semibold">{{ filterQuery.trim() }}</span>"
                                                    </span>
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


                            <!-- ── Tab: Varianty ── -->
                            <div v-show="activeTab === 'variants'" class="flex flex-col overflow-hidden flex-1">

                                <!-- AI suggest row -->
                                <div class="flex-shrink-0 border-b border-gray-100 px-5 py-3 flex items-center justify-between gap-3">
                                    <span class="text-xs text-gray-400">Rovnaký produkt, iná farba / veľkosť</span>
                                    <button @click="suggestModalVariants" :disabled="modal.suggestingVariants"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-60 transition-colors whitespace-nowrap flex-shrink-0">
                                        <svg v-if="modal.suggestingVariants" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                        </svg>
                                        <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                        </svg>
                                        {{ modal.suggestingVariants ? 'AI analyzuje...' : 'AI navrhnúť' }}
                                    </button>
                                </div>

                                <!-- AI suggestions -->
                                <div v-if="modal.variantSuggestions.length > 0" class="flex-shrink-0 border-b border-amber-100 bg-amber-50 px-5 py-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-xs font-semibold text-amber-700">AI navrhuje ({{ modal.variantSuggestions.filter(p => p.selected).length }} zaškrtnutých)</p>
                                        <div class="flex items-center gap-2">
                                            <button @click="saveModalVariantSuggestions" :disabled="modal.savingVariants"
                                                    class="text-xs font-medium text-amber-700 hover:text-amber-900 underline disabled:opacity-50 transition-colors">
                                                Uložiť zaškrtnuté
                                            </button>
                                            <button @click="modal.variantSuggestions = []" class="text-amber-400 hover:text-amber-600 transition-colors">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <label v-for="p in modal.variantSuggestions" :key="p.product_id"
                                               class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-white px-2.5 py-1 text-xs font-medium text-amber-800 cursor-pointer hover:bg-amber-50 transition-colors">
                                            <input type="checkbox" v-model="p.selected" class="h-3 w-3 rounded text-blue-600 flex-shrink-0" />
                                            {{ p.name }}
                                        </label>
                                    </div>
                                </div>

                                <!-- Manual picker -->
                                <div class="flex-shrink-0 border-b border-gray-100 px-5 pt-3 pb-2">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="relative flex-1">
                                            <svg class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                                            </svg>
                                            <input v-model="modal.variantSearch" type="text" placeholder="Hľadaj v kategórii..."
                                                   class="w-full rounded-md border border-gray-200 py-1.5 pl-8 pr-3 text-xs focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                        </div>
                                        <button @click="addManualVariants"
                                                :disabled="modal.selectedVariantIds.length === 0 || modal.savingVariants"
                                                class="inline-flex items-center gap-1 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-40 transition-colors whitespace-nowrap flex-shrink-0">
                                            <svg v-if="modal.savingVariants" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                                            Pridať ({{ modal.selectedVariantIds.length }})
                                        </button>
                                    </div>
                                    <div class="max-h-36 overflow-y-auto -mx-1 px-1 space-y-0.5">
                                        <label v-for="p in variantPickerProducts" :key="p.product_id"
                                               class="flex items-center gap-2.5 rounded-md px-2 py-1.5 hover:bg-gray-50 cursor-pointer transition-colors">
                                            <input type="checkbox"
                                                   :checked="modal.selectedVariantIds.includes(p.product_id)"
                                                   @change="toggleVariantSelection(p.product_id)"
                                                   class="h-3.5 w-3.5 rounded text-blue-600 flex-shrink-0" />
                                            <img v-if="p.image" :src="p.image" class="h-7 w-7 object-contain rounded flex-shrink-0" @error="$event.target.style.display='none'" />
                                            <span class="text-xs text-gray-700 truncate">{{ p.name }}</span>
                                        </label>
                                        <p v-if="variantPickerProducts.length === 0" class="text-xs text-gray-300 italic text-center py-3">
                                            {{ modal.variantSearch ? 'Žiadne zhody' : 'Žiadne ďalšie produkty v kategórii' }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Assigned variants -->
                                <div class="flex-1 overflow-y-auto px-5 py-3">
                                    <div v-if="modal.assignedVariants.length === 0" class="flex flex-col items-center justify-center py-8 text-gray-300">
                                        <svg class="h-8 w-8 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                        <p class="text-sm">Žiadne varianty</p>
                                    </div>
                                    <div class="space-y-1">
                                        <div v-for="v in modal.assignedVariants" :key="v.product_id"
                                             class="flex items-center gap-2.5 rounded-md px-2 py-1.5 group hover:bg-gray-50 transition-colors">
                                            <img v-if="v.image" :src="v.image" class="h-8 w-8 object-contain rounded flex-shrink-0" @error="$event.target.style.display='none'" />
                                            <span class="flex-1 text-xs text-gray-700 truncate">{{ v.name }}</span>
                                            <button @click="removeModalVariant(v.product_id)"
                                                    class="flex-shrink-0 opacity-0 group-hover:opacity-100 rounded-full p-0.5 text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Tab: Súvisiace ── -->
                            <div v-show="activeTab === 'related'" class="flex flex-col overflow-hidden flex-1">

                                <!-- AI suggest row -->
                                <div class="flex-shrink-0 border-b border-gray-100 px-5 py-3 flex items-center justify-between gap-3">
                                    <span class="text-xs text-gray-400">Komplementárne produkty (príslušenstvo, doplnky)</span>
                                    <button @click="suggestModalRelated" :disabled="modal.suggestingRelated"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-teal-200 bg-teal-50 px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-100 disabled:opacity-60 transition-colors whitespace-nowrap flex-shrink-0">
                                        <svg v-if="modal.suggestingRelated" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                        </svg>
                                        <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                        </svg>
                                        {{ modal.suggestingRelated ? 'AI analyzuje...' : 'AI navrhnúť' }}
                                    </button>
                                </div>

                                <!-- AI suggestions -->
                                <div v-if="modal.relatedSuggestions.length > 0" class="flex-shrink-0 border-b border-teal-100 bg-teal-50 px-5 py-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-xs font-semibold text-teal-700">AI navrhuje ({{ modal.relatedSuggestions.filter(p => p.selected).length }} zaškrtnutých)</p>
                                        <div class="flex items-center gap-2">
                                            <button @click="saveModalRelatedSuggestions" :disabled="modal.savingRelated"
                                                    class="text-xs font-medium text-teal-700 hover:text-teal-900 underline disabled:opacity-50 transition-colors">
                                                Uložiť zaškrtnuté
                                            </button>
                                            <button @click="modal.relatedSuggestions = []" class="text-teal-400 hover:text-teal-600 transition-colors">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <label v-for="p in modal.relatedSuggestions" :key="p.product_id"
                                               class="inline-flex items-center gap-1.5 rounded-full border border-teal-200 bg-white px-2.5 py-1 text-xs font-medium text-teal-800 cursor-pointer hover:bg-teal-50 transition-colors">
                                            <input type="checkbox" v-model="p.selected" class="h-3 w-3 rounded text-teal-600 flex-shrink-0" />
                                            {{ p.name }}
                                        </label>
                                    </div>
                                </div>

                                <!-- Manual picker -->
                                <div class="flex-shrink-0 border-b border-gray-100 px-5 pt-3 pb-2">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="relative flex-1">
                                            <svg class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                                            </svg>
                                            <input v-model="modal.relatedSearch" type="text" placeholder="Hľadaj v kategórii..."
                                                   class="w-full rounded-md border border-gray-200 py-1.5 pl-8 pr-3 text-xs focus:border-teal-400 focus:outline-none focus:ring-1 focus:ring-teal-400" />
                                        </div>
                                        <button @click="addManualRelated"
                                                :disabled="modal.selectedRelatedIds.length === 0 || modal.savingRelated"
                                                class="inline-flex items-center gap-1 rounded-md bg-teal-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-teal-700 disabled:opacity-40 transition-colors whitespace-nowrap flex-shrink-0">
                                            <svg v-if="modal.savingRelated" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                                            Pridať ({{ modal.selectedRelatedIds.length }})
                                        </button>
                                    </div>
                                    <div class="max-h-36 overflow-y-auto -mx-1 px-1 space-y-0.5">
                                        <label v-for="p in relatedPickerProducts" :key="p.product_id"
                                               class="flex items-center gap-2.5 rounded-md px-2 py-1.5 hover:bg-gray-50 cursor-pointer transition-colors">
                                            <input type="checkbox"
                                                   :checked="modal.selectedRelatedIds.includes(p.product_id)"
                                                   @change="toggleRelatedSelection(p.product_id)"
                                                   class="h-3.5 w-3.5 rounded text-teal-600 flex-shrink-0" />
                                            <img v-if="p.image" :src="p.image" class="h-7 w-7 object-contain rounded flex-shrink-0" @error="$event.target.style.display='none'" />
                                            <span class="text-xs text-gray-700 truncate">{{ p.name }}</span>
                                        </label>
                                        <p v-if="relatedPickerProducts.length === 0" class="text-xs text-gray-300 italic text-center py-3">
                                            {{ modal.relatedSearch ? 'Žiadne zhody' : 'Žiadne ďalšie produkty v kategórii' }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Assigned related -->
                                <div class="flex-1 overflow-y-auto px-5 py-3">
                                    <div v-if="modal.assignedRelated.length === 0" class="flex flex-col items-center justify-center py-8 text-gray-300">
                                        <svg class="h-8 w-8 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                        <p class="text-sm">Žiadne súvisiace produkty</p>
                                    </div>
                                    <div class="space-y-1">
                                        <div v-for="r in modal.assignedRelated" :key="r.product_id"
                                             class="flex items-center gap-2.5 rounded-md px-2 py-1.5 group hover:bg-gray-50 transition-colors">
                                            <img v-if="r.image" :src="r.image" class="h-8 w-8 object-contain rounded flex-shrink-0" @error="$event.target.style.display='none'" />
                                            <span class="flex-1 text-xs text-gray-700 truncate">{{ r.name }}</span>
                                            <button @click="removeModalRelated(r.product_id)"
                                                    class="flex-shrink-0 opacity-0 group-hover:opacity-100 rounded-full p-0.5 text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-5 py-4 flex-shrink-0">
                                <button @click="closeModal"
                                        class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                    Zavrieť
                                </button>
                                <button v-if="activeTab === 'filters' || activeTab === 'categories'"
                                        @click="saveAll"
                                        :disabled="modal.saving"
                                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                    <svg v-if="modal.saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    Uložiť filtre &amp; kategórie
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
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
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

const batch         = ref({ running: false, total: 0, done: 0 });
const batchVariants = ref({ running: false, total: 0, done: 0 });
const batchRelated  = ref({ running: false, total: 0, done: 0 });

// Review modals for variants and related
const reviewVariants = ref({ open: false, items: [], saving: false });
const reviewRelated  = ref({ open: false, items: [], saving: false });

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

// ─── Batch: varianty ─────────────────────────────────────────────────────────

async function startBatchVariants() {
    const targets = props.products;
    if (targets.length === 0) return;

    batchVariants.value = { running: true, total: targets.length, done: 0 };
    // key = JSON string of sorted product IDs to deduplicate groups across products
    const seenGroupKeys = new Set();
    const collected = [];

    for (const product of targets) {
        try {
            const res = await axios.post(route('products.batch-suggest-variants'), { product_id: product.product_id });
            const groups = res.data.groups ?? [];
            for (const group of groups) {
                if (group.length < 2) continue;
                const key = [...group.map(p => p.product_id)].sort((a, b) => a - b).join(',');
                if (!seenGroupKeys.has(key)) {
                    seenGroupKeys.add(key);
                    collected.push({ group: [...group], skip: false });
                }
            }
        } catch { /* skip */ }
        batchVariants.value.done++;
    }

    batchVariants.value.running = false;

    if (collected.length === 0) {
        showToast('Nenašli sa žiadne skupiny variantov');
        return;
    }

    reviewVariants.value = { open: true, items: collected, saving: false };
}

async function confirmVariants() {
    reviewVariants.value.saving = true;
    let saved = 0;

    for (const item of reviewVariants.value.items) {
        if (item.skip || item.group.length < 2) continue;
        try {
            const masterProduct = item.group[0];
            const variantIds = item.group.slice(1).map(p => p.product_id);
            await axios.post(route('products.save-variants', masterProduct.product_id), { variant_ids: variantIds });
            saved++;
        } catch { /* skip */ }
    }

    reviewVariants.value.saving = false;
    reviewVariants.value.open   = false;
    showToast(`Uložené — ${saved} skupín variantov`);
}

// ─── Batch: súvisiace ─────────────────────────────────────────────────────────

async function startBatchRelated() {
    const targets = props.products;
    if (targets.length === 0) return;

    batchRelated.value = { running: true, total: targets.length, done: 0 };
    const collected = [];

    for (const product of targets) {
        try {
            const res = await axios.post(route('products.batch-suggest-related'), { product_id: product.product_id });
            const related = res.data.related ?? [];
            if (related.length > 0) {
                collected.push({ product, related: [...related], skip: false });
            }
        } catch { /* skip */ }
        batchRelated.value.done++;
    }

    batchRelated.value.running = false;

    if (collected.length === 0) {
        showToast('Nenašli sa žiadne súvisiace produkty');
        return;
    }

    reviewRelated.value = { open: true, items: collected, saving: false };
}

async function confirmRelated() {
    reviewRelated.value.saving = true;
    let saved = 0;

    for (const item of reviewRelated.value.items) {
        if (item.skip || item.related.length === 0) continue;
        try {
            await axios.post(route('products.save-related', item.product.product_id), {
                related_ids: item.related.map(r => r.product_id),
            });
            saved++;
        } catch { /* skip */ }
    }

    reviewRelated.value.saving = false;
    reviewRelated.value.open   = false;
    showToast(`Uložené — ${saved} produktom priradené súvisiace produkty`);
}

// ─── Tabs ─────────────────────────────────────────────────────────────────────

const tabs = [
    { key: 'filters',    label: 'Filtre' },
    { key: 'categories', label: 'Kategórie' },
    { key: 'variants',   label: 'Varianty' },
    { key: 'related',    label: 'Súvisiace' },
];
const activeTab = ref('filters');
const descExpanded = ref(true);

function tabCount(key) {
    if (key === 'filters')    return modal.value.assignedFilters.length;
    if (key === 'categories') return modal.value.assignedCategories.length;
    if (key === 'variants')   return modal.value.assignedVariants.length;
    if (key === 'related')    return modal.value.assignedRelated.length;
    return 0;
}

// ─── Product search & filter bar ─────────────────────────────────────────────

const searchQuery  = ref('');
const filterByGroup = ref('');
const filterByValue = ref('');
const filterByMode  = ref('has'); // 'has' or 'has_not'

// Reset value when group changes
watch(filterByGroup, () => { filterByValue.value = ''; });

// All unique group names from filters prop (sorted)
const uniqueGroupNames = computed(() => {
    const names = new Set(props.filters.map(f => f.group_name));
    return [...names].sort((a, b) => a.localeCompare(b, 'sk'));
});

// Filters within selected group
const filtersInSelectedGroup = computed(() => {
    if (!filterByGroup.value) return [];
    return props.filters
        .filter(f => f.group_name === filterByGroup.value)
        .sort((a, b) => a.name.localeCompare(b.name, 'sk'));
});

// ─── Filter bar searchable dropdowns ─────────────────────────────────────────

const barGroupWrapRef  = ref(null);
const barGroupInputRef = ref(null);
const showBarGroupMenu = ref(false);
const barGroupQuery    = ref('');

const barValueWrapRef  = ref(null);
const barValueInputRef = ref(null);
const showBarValueMenu = ref(false);
const barValueQuery    = ref('');

const barGroupFiltered = computed(() => {
    if (!barGroupQuery.value.trim()) return uniqueGroupNames.value;
    const q = barGroupQuery.value.toLowerCase();
    return uniqueGroupNames.value.filter(n => n.toLowerCase().includes(q));
});

const barValueFiltered = computed(() => {
    if (!barValueQuery.value.trim()) return filtersInSelectedGroup.value;
    const q = barValueQuery.value.toLowerCase();
    return filtersInSelectedGroup.value.filter(f => f.name.toLowerCase().includes(q));
});

const barValueLabel = computed(() => {
    if (!filterByValue.value) return '';
    const f = props.filters.find(f => f.filter_id === Number(filterByValue.value));
    return f ? f.name : '';
});

async function openBarGroupMenu() {
    showBarGroupMenu.value = true;
    barGroupQuery.value = '';
    await nextTick();
    barGroupInputRef.value?.focus();
}

function selectBarGroup(gn) {
    filterByGroup.value = gn;
    barGroupQuery.value = '';
    showBarGroupMenu.value = false;
}

async function openBarValueMenu() {
    showBarValueMenu.value = true;
    barValueQuery.value = '';
    await nextTick();
    barValueInputRef.value?.focus();
}

function selectBarValue(f) {
    filterByValue.value = String(f.filter_id);
    barValueQuery.value = '';
    showBarValueMenu.value = false;
}

// Build a product→filter_ids index (fetched lazily per category)
const productFilterIndex = ref({}); // { productId: Set<filterId> }
const productFilterIndexLoaded = ref(false);

async function loadProductFilterIndex() {
    if (productFilterIndexLoaded.value) return;
    try {
        const res = await axios.get(route('categories.product-filters', props.category.category_id));
        const idx = {};
        for (const [pid, fids] of Object.entries(res.data)) {
            idx[pid] = new Set(fids);
        }
        productFilterIndex.value = idx;
        productFilterIndexLoaded.value = true;
    } catch {
        showToast('Nepodarilo sa načítať index filtrov', 'error');
    }
}

// Load index when filter bar is first used
watch(filterByGroup, (val) => {
    if (val && !productFilterIndexLoaded.value) {
        loadProductFilterIndex();
    }
});

const filteredProducts = computed(() => {
    let list = props.products;

    // Text search
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(p =>
            p.name.toLowerCase().includes(q) ||
            (p.description && p.description.toLowerCase().includes(q))
        );
    }

    // Filter by group / specific filter value
    if (filterByGroup.value && productFilterIndexLoaded.value) {
        const idx = productFilterIndex.value;
        const wantHas = filterByMode.value === 'has';

        if (filterByValue.value) {
            // Filter by specific filter_id
            const fid = Number(filterByValue.value);
            list = list.filter(p => {
                const has = idx[p.product_id]?.has(fid) ?? false;
                return wantHas ? has : !has;
            });
        } else {
            // Filter by group — product has ANY filter from this group
            const groupFilterIds = new Set(
                props.filters.filter(f => f.group_name === filterByGroup.value).map(f => f.filter_id)
            );
            list = list.filter(p => {
                const pFilters = idx[p.product_id];
                const has = pFilters ? [...pFilters].some(fid => groupFilterIds.has(fid)) : false;
                return wantHas ? has : !has;
            });
        }
    }

    return list;
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
    // Variants
    assignedVariants: [],
    suggestingVariants: false,
    variantSuggestions: [],   // [{product_id, name, image, selected}]
    savingVariants: false,
    variantSearch: '',
    selectedVariantIds: [],   // manually selected from category
    // Related
    assignedRelated: [],
    suggestingRelated: false,
    relatedSuggestions: [],
    savingRelated: false,
    relatedSearch: '',
    selectedRelatedIds: [],
});

// Reactive badge counts (updated when user adds/removes from modal)
const variantCounts = ref(Object.fromEntries(props.products.map(p => [p.product_id, p.variant_count ?? 0])));
const relatedCounts  = ref(Object.fromEntries(props.products.map(p => [p.product_id, p.related_count ?? 0])));

async function openModal(product, initialTab = 'filters') {
    modal.value = {
        open: true, product, loading: true, saving: false, suggesting: false,
        assignedCategories: [], assignedFilters: [],
        assignedVariants: [], suggestingVariants: false, variantSuggestions: [], savingVariants: false, variantSearch: '', selectedVariantIds: [],
        assignedRelated: [],  suggestingRelated: false,  relatedSuggestions: [],  savingRelated: false,  relatedSearch: '',  selectedRelatedIds: [],
    };
    aiSuggestions.value = [];
    aiNewFilters.value  = [];
    activeTab.value = initialTab;
    catQuery.value = '';
    filterQuery.value = '';
    groupQuery.value = '';
    selectedGroup.value = null;
    showCatMenu.value = false;
    showFilterMenu.value = false;
    showGroupMenu.value = false;

    try {
        const [catRes, filterRes, variantRes, relatedRes] = await Promise.all([
            axios.get(route('products.categories', product.product_id)),
            axios.get(route('products.filters', product.product_id)),
            axios.get(route('products.variants', product.product_id)),
            axios.get(route('products.related', product.product_id)),
        ]);
        modal.value.assignedCategories = catRes.data.map(id => categoryMap.value[id]).filter(Boolean);
        modal.value.assignedFilters    = filterRes.data.map(id => filterMap.value[id]).filter(Boolean);
        modal.value.assignedVariants   = variantRes.data;
        modal.value.assignedRelated    = relatedRes.data;
    } catch {
        showToast('Nepodarilo sa načítať dáta produktu', 'error');
    } finally {
        modal.value.loading = false;
    }
}

function openVariantsModal(product, event) {
    event?.stopPropagation();
    openModal(product, 'variants');
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

// ─── Computed: manual pickers ─────────────────────────────────────────────────

const variantPickerProducts = computed(() => {
    if (!modal.value.product) return [];
    const excludeIds = new Set([
        modal.value.product.product_id,
        ...modal.value.assignedVariants.map(v => v.product_id),
    ]);
    let list = props.products.filter(p => !excludeIds.has(p.product_id));
    const q = modal.value.variantSearch.trim().toLowerCase();
    if (q) list = list.filter(p => p.name.toLowerCase().includes(q));
    return list;
});

const relatedPickerProducts = computed(() => {
    if (!modal.value.product) return [];
    const excludeIds = new Set([
        modal.value.product.product_id,
        ...modal.value.assignedRelated.map(r => r.product_id),
    ]);
    let list = props.products.filter(p => !excludeIds.has(p.product_id));
    const q = modal.value.relatedSearch.trim().toLowerCase();
    if (q) list = list.filter(p => p.name.toLowerCase().includes(q));
    return list;
});

// ─── Modal: Varianty functions ────────────────────────────────────────────────

function toggleVariantSelection(productId) {
    const idx = modal.value.selectedVariantIds.indexOf(productId);
    if (idx === -1) modal.value.selectedVariantIds.push(productId);
    else modal.value.selectedVariantIds.splice(idx, 1);
}

function toggleRelatedSelection(productId) {
    const idx = modal.value.selectedRelatedIds.indexOf(productId);
    if (idx === -1) modal.value.selectedRelatedIds.push(productId);
    else modal.value.selectedRelatedIds.splice(idx, 1);
}

async function suggestModalVariants() {
    modal.value.suggestingVariants = true;
    modal.value.variantSuggestions = [];
    try {
        const res = await axios.post(route('products.suggest-variants', modal.value.product.product_id));
        const groups = res.data.groups ?? [];
        const seen = new Set([
            modal.value.product.product_id,
            ...modal.value.assignedVariants.map(v => v.product_id),
        ]);
        const suggestions = [];
        for (const group of groups) {
            for (const p of group) {
                if (!seen.has(p.product_id)) {
                    seen.add(p.product_id);
                    suggestions.push({ ...p, selected: true });
                }
            }
        }
        modal.value.variantSuggestions = suggestions;
        if (suggestions.length === 0) showToast('AI nenašla žiadne nové varianty', 'error');
    } catch {
        showToast('Chyba pri AI návrhu variantov', 'error');
    } finally {
        modal.value.suggestingVariants = false;
    }
}

async function saveModalVariantSuggestions() {
    const selected = modal.value.variantSuggestions.filter(p => p.selected);
    if (!selected.length) return;
    modal.value.savingVariants = true;
    try {
        await axios.post(route('products.save-variants', modal.value.product.product_id), {
            variant_ids: selected.map(p => p.product_id),
        });
        modal.value.assignedVariants.push(...selected.map(({ selected: _, ...p }) => p));
        modal.value.variantSuggestions = [];
        variantCounts.value[modal.value.product.product_id] = modal.value.assignedVariants.length;
        showToast(`${selected.length} variantov uložených`);
    } catch {
        showToast('Chyba pri ukladaní variantov', 'error');
    } finally {
        modal.value.savingVariants = false;
    }
}

async function addManualVariants() {
    const ids = [...modal.value.selectedVariantIds];
    if (!ids.length) return;
    modal.value.savingVariants = true;
    try {
        await axios.post(route('products.save-variants', modal.value.product.product_id), { variant_ids: ids });
        const newItems = props.products
            .filter(p => ids.includes(p.product_id))
            .map(p => ({ product_id: p.product_id, name: p.name, image: p.image }));
        modal.value.assignedVariants.push(...newItems);
        modal.value.selectedVariantIds = [];
        variantCounts.value[modal.value.product.product_id] = modal.value.assignedVariants.length;
        showToast(`${ids.length} variantov pridaných`);
    } catch {
        showToast('Chyba pri ukladaní', 'error');
    } finally {
        modal.value.savingVariants = false;
    }
}

async function removeModalVariant(variantId) {
    try {
        await axios.delete(route('products.remove-variant', {
            productId: modal.value.product.product_id,
            variantId,
        }));
        modal.value.assignedVariants = modal.value.assignedVariants.filter(v => v.product_id !== variantId);
        variantCounts.value[modal.value.product.product_id] = modal.value.assignedVariants.length;
    } catch {
        showToast('Chyba pri odstraňovaní variantu', 'error');
    }
}

// ─── Modal: Súvisiace functions ───────────────────────────────────────────────

async function suggestModalRelated() {
    modal.value.suggestingRelated = true;
    modal.value.relatedSuggestions = [];
    try {
        const res = await axios.post(route('products.suggest-related', modal.value.product.product_id));
        const related = res.data.related ?? [];
        const seen = new Set([
            modal.value.product.product_id,
            ...modal.value.assignedRelated.map(r => r.product_id),
        ]);
        modal.value.relatedSuggestions = related
            .filter(p => !seen.has(p.product_id))
            .map(p => ({ ...p, selected: true }));
        if (!modal.value.relatedSuggestions.length) showToast('AI nenašla žiadne nové súvisiace produkty', 'error');
    } catch {
        showToast('Chyba pri AI návrhu súvisiacich', 'error');
    } finally {
        modal.value.suggestingRelated = false;
    }
}

async function saveModalRelatedSuggestions() {
    const selected = modal.value.relatedSuggestions.filter(p => p.selected);
    if (!selected.length) return;
    modal.value.savingRelated = true;
    try {
        await axios.post(route('products.save-related', modal.value.product.product_id), {
            related_ids: selected.map(p => p.product_id),
        });
        modal.value.assignedRelated.push(...selected.map(({ selected: _, ...p }) => p));
        modal.value.relatedSuggestions = [];
        relatedCounts.value[modal.value.product.product_id] = modal.value.assignedRelated.length;
        showToast(`${selected.length} súvisiacich produktov uložených`);
    } catch {
        showToast('Chyba pri ukladaní súvisiacich', 'error');
    } finally {
        modal.value.savingRelated = false;
    }
}

async function addManualRelated() {
    const ids = [...modal.value.selectedRelatedIds];
    if (!ids.length) return;
    modal.value.savingRelated = true;
    try {
        await axios.post(route('products.save-related', modal.value.product.product_id), { related_ids: ids });
        const newItems = props.products
            .filter(p => ids.includes(p.product_id))
            .map(p => ({ product_id: p.product_id, name: p.name, image: p.image }));
        modal.value.assignedRelated.push(...newItems);
        modal.value.selectedRelatedIds = [];
        relatedCounts.value[modal.value.product.product_id] = modal.value.assignedRelated.length;
        showToast(`${ids.length} produktov pridaných`);
    } catch {
        showToast('Chyba pri ukladaní', 'error');
    } finally {
        modal.value.savingRelated = false;
    }
}

async function removeModalRelated(relatedId) {
    try {
        await axios.delete(route('products.remove-related', {
            productId: modal.value.product.product_id,
            relatedId,
        }));
        modal.value.assignedRelated = modal.value.assignedRelated.filter(r => r.product_id !== relatedId);
        relatedCounts.value[modal.value.product.product_id] = modal.value.assignedRelated.length;
    } catch {
        showToast('Chyba pri odstraňovaní súvisiaceho produktu', 'error');
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
    if (availableFilters.value.length > 0) {
        addFilter(availableFilters.value[0]);
    } else if (canCreateFilter.value) {
        createNewFilterInline();
    }
}

function removeFilter(id) {
    modal.value.assignedFilters = modal.value.assignedFilters.filter(f => f.filter_id !== id);
}

// ─── Create new filter inline ────────────────────────────────────────────────

const creatingInlineFilter = ref(false);

const canCreateFilter = computed(() => {
    if (!filterQuery.value.trim() || !selectedGroup.value) return false;
    const q = filterQuery.value.trim().toLowerCase();
    // Don't show "create" if an exact match already exists (assigned or not)
    const allInGroup = props.filters.filter(f => f.group_name === selectedGroup.value);
    return !allInGroup.some(f => f.name.toLowerCase() === q);
});

async function createNewFilterInline() {
    if (!canCreateFilter.value || creatingInlineFilter.value) return;
    const name = filterQuery.value.trim();
    const groupName = selectedGroup.value;

    creatingInlineFilter.value = true;
    try {
        const res = await axios.post(
            route('products.create-filter', modal.value.product.product_id),
            { group_name: groupName, filter_name: name }
        );
        const newFilter = {
            filter_id:  res.data.filter_id,
            group_id:   res.data.group_id,
            group_name: res.data.group_name,
            name:       res.data.name,
        };
        modal.value.assignedFilters.push(newFilter);
        filterQuery.value = '';
        showFilterMenu.value = false;
        showToast(`Filter „${name}" bol vytvorený a priradený`);
    } catch {
        showToast(`Nepodarilo sa vytvoriť filter „${name}"`, 'error');
    } finally {
        creatingInlineFilter.value = false;
    }
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
    if (barGroupWrapRef.value && !barGroupWrapRef.value.contains(e.target)) showBarGroupMenu.value = false;
    if (barValueWrapRef.value && !barValueWrapRef.value.contains(e.target)) showBarValueMenu.value = false;
}
onMounted(() => document.addEventListener('mousedown', handleOutsideClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', handleOutsideClick));
</script>
