<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <button @click="router.visit(route('filters.index'))"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <span class="text-sm text-gray-400">{{ filter.group_name }}</span>
                <svg class="h-4 w-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ filter.name }}</h2>
            </div>
        </template>

        <!-- Toast -->
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div v-if="toast.show"
                 class="fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium shadow-lg"
                 :class="toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'">
                <svg v-if="toast.type === 'success'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <svg v-else class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                {{ toast.message }}
            </div>
        </Transition>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6 h-[calc(100vh-180px)]">

                    <!-- ═══ LEFT: Priradené produkty ═══════════════════════════ -->
                    <div class="w-72 flex-shrink-0 flex flex-col rounded-lg bg-white shadow overflow-hidden">
                        <div class="border-b border-gray-100 px-4 py-3">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-gray-800 text-sm">Priradené produkty</h3>
                                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">
                                    {{ visibleAssignedCount }}
                                </span>
                            </div>
                            <!-- Category badge in left panel -->
                            <div v-if="selectedCategoryId > 0"
                                 class="mt-1.5 flex items-center gap-1 text-xs text-indigo-600">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                                </svg>
                                <span class="truncate">{{ selectedCategoryName }}</span>
                                <span v-if="hiddenAssignedCount > 0" class="text-gray-400 ml-auto flex-shrink-0">
                                    +{{ hiddenAssignedCount }}
                                </span>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto">
                            <div v-if="assignedInSearch.length === 0 && assignedNotInSearch.length === 0"
                                 class="p-4 text-sm text-gray-400 italic">
                                <span v-if="selectedCategoryId > 0">
                                    Žiadne priradené produkty v tejto kategórii.
                                </span>
                                <span v-else>Žiadne priradené produkty.</span>
                            </div>

                            <div v-for="product in assignedInSearch" :key="'as-' + product.product_id"
                                 class="flex items-center gap-2 px-3 py-2 border-b border-gray-50 hover:bg-red-50 transition-colors group">
                                <img v-if="product.image" :src="product.image" :alt="product.name"
                                     class="h-8 w-8 flex-shrink-0 rounded object-cover cursor-pointer"
                                     @click="openModal(product)"
                                     @error="$event.target.style.display='none'" />
                                <div v-else class="h-8 w-8 flex-shrink-0 rounded bg-gray-100 cursor-pointer" @click="openModal(product)"></div>
                                <span class="flex-1 text-xs text-gray-700 leading-tight line-clamp-2 cursor-pointer" @click="openModal(product)">{{ product.name }}</span>
                                <button class="flex-shrink-0 text-gray-300 hover:text-red-400 transition-colors" @click="toggleProduct(product.product_id)" title="Odpriradiť">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <div v-for="product in assignedNotInSearch" :key="'an-' + product.product_id"
                                 class="flex items-center gap-2 px-3 py-2 border-b border-gray-50 hover:bg-red-50 transition-colors group">
                                <img v-if="product.image" :src="product.image" :alt="product.name"
                                     class="h-8 w-8 flex-shrink-0 rounded object-cover cursor-pointer"
                                     @click="openModal(product)"
                                     @error="$event.target.style.display='none'" />
                                <div v-else class="h-8 w-8 flex-shrink-0 rounded bg-gray-100 cursor-pointer" @click="openModal(product)"></div>
                                <span class="flex-1 text-xs text-gray-700 leading-tight line-clamp-2 cursor-pointer" @click="openModal(product)">{{ product.name }}</span>
                                <button class="flex-shrink-0 text-gray-300 hover:text-red-400 transition-colors" @click="toggleProduct(product.product_id)" title="Odpriradiť">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Hidden count notice -->
                            <div v-if="hiddenAssignedCount > 0 && selectedCategoryId > 0"
                                 class="px-4 py-3 text-xs text-gray-400 border-t border-gray-50 italic">
                                {{ hiddenAssignedCount }} priradených produktov v iných kategóriách je skrytých.
                            </div>
                        </div>
                    </div>

                    <!-- ═══ RIGHT: Tabs ═══════════════════════════════════════ -->
                    <div class="flex-1 flex flex-col rounded-lg bg-white shadow overflow-hidden">

                        <!-- Tab bar -->
                        <div class="flex flex-shrink-0 border-b border-gray-100">
                            <button
                                @click="activeTab = 'products'"
                                class="flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 transition-colors"
                                :class="activeTab === 'products'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                </svg>
                                Produkty
                            </button>
                            <button
                                @click="activeTab = 'categories'"
                                class="flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 transition-colors"
                                :class="activeTab === 'categories'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                Kategórie
                                <span class="rounded-full px-1.5 py-0.5 text-xs"
                                      :class="categoryHasChanges ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'">
                                    {{ assignedCategoryIds.size }}
                                </span>
                            </button>
                        </div>

                        <!-- ══ Tab: Produkty ══════════════════════════════════════ -->
                        <div v-show="activeTab === 'products'" class="flex-1 flex flex-col overflow-hidden">

                        <!-- Filters bar -->
                        <div class="border-b border-gray-100 px-4 py-3 space-y-2">

                            <!-- ── Category typeahead ── -->
                            <div class="relative" ref="categoryWrapperRef">
                                <!-- Trigger / selected display -->
                                <div
                                    class="flex w-full cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors"
                                    :class="showCategoryMenu
                                        ? 'border-indigo-400 ring-1 ring-indigo-400 bg-white'
                                        : 'border-gray-200 bg-white hover:border-gray-300'"
                                    @click="openCategoryMenu"
                                >
                                    <svg class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                                    </svg>

                                    <!-- When dropdown open: show text input -->
                                    <input
                                        v-if="showCategoryMenu"
                                        ref="categoryInputRef"
                                        v-model="categoryQuery"
                                        type="text"
                                        placeholder="Hľadaj kategóriu..."
                                        class="flex-1 bg-transparent outline-none placeholder-gray-400 text-gray-700"
                                        @keydown.enter.prevent="selectFirstFiltered"
                                        @click.stop
                                    />
                                    <!-- When closed: show selected name -->
                                    <span v-else class="flex-1 truncate"
                                          :class="selectedCategoryId > 0 ? 'text-gray-800 font-medium' : 'text-gray-400'">
                                        {{ selectedCategoryId > 0 ? selectedCategoryName : 'Filtrovať podľa kategórie...' }}
                                    </span>

                                    <!-- Clear button -->
                                    <button v-if="selectedCategoryId > 0 && !showCategoryMenu"
                                            class="flex-shrink-0 text-gray-300 hover:text-gray-500"
                                            @click.stop="clearCategory">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                    <!-- Chevron -->
                                    <svg v-else class="h-4 w-4 flex-shrink-0 text-gray-400 transition-transform"
                                         :class="{ 'rotate-180': showCategoryMenu }"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>

                                <!-- Dropdown -->
                                <div v-if="showCategoryMenu"
                                     class="absolute left-0 right-0 top-full z-30 mt-1 max-h-64 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg">

                                    <!-- All categories option -->
                                    <div @click="selectCategory(null)"
                                         class="flex items-center gap-2 px-3 py-2.5 cursor-pointer hover:bg-gray-50 border-b border-gray-100"
                                         :class="selectedCategoryId === 0 ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-500'">
                                        <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                        </svg>
                                        <span class="text-sm">Všetky kategórie</span>
                                    </div>

                                    <!-- Filtered categories -->
                                    <div v-if="filteredCategories.length === 0"
                                         class="px-3 py-4 text-sm text-center text-gray-400 italic">
                                        Žiadne zhody pre „{{ categoryQuery }}"
                                    </div>

                                    <div v-for="cat in filteredCategories"
                                         :key="cat.category_id"
                                         @click="selectCategory(cat)"
                                         class="flex items-center cursor-pointer hover:bg-indigo-50 transition-colors"
                                         :class="[
                                             selectedCategoryId === cat.category_id ? 'bg-indigo-50' : '',
                                             cat.depth === 0 ? 'border-t border-gray-100 mt-0.5' : '',
                                         ]"
                                         :style="{ paddingLeft: (12 + cat.depth * 14) + 'px',
                                                   paddingRight: '12px',
                                                   paddingTop: cat.depth === 0 ? '8px' : '6px',
                                                   paddingBottom: cat.depth === 0 ? '8px' : '6px' }"
                                    >
                                        <!-- Depth indicator -->
                                        <span v-if="cat.depth > 0" class="mr-1.5 flex-shrink-0 text-gray-300 text-xs select-none">
                                            {{ '└' + '─'.repeat(cat.depth - 1) }}
                                        </span>
                                        <span class="text-sm truncate"
                                              :class="[
                                                  cat.depth === 0 ? 'font-semibold text-gray-700' : 'text-gray-600',
                                                  selectedCategoryId === cat.category_id ? 'text-indigo-700' : '',
                                              ]"
                                              v-html="highlightCat(cat.name)">
                                        </span>
                                        <!-- Checkmark -->
                                        <svg v-if="selectedCategoryId === cat.category_id"
                                             class="ml-auto h-4 w-4 flex-shrink-0 text-indigo-500"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Show all in category button ── -->
                            <div v-if="selectedCategoryId > 0" class="flex items-center gap-2">
                                <button
                                    @click="showAllInCategory"
                                    class="flex items-center gap-1.5 rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-100 transition-colors"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                    </svg>
                                    Zobraziť všetky produkty z kategórie
                                    <span v-if="categoryProductIds" class="rounded-full bg-indigo-100 px-1.5 text-indigo-500">
                                        {{ categoryProductIds.size }}
                                    </span>
                                </button>
                                <span v-if="showingAllInCategory && !isSearching" class="text-xs text-gray-400">
                                    (max. 120)
                                </span>
                            </div>

                            <!-- ── Product search input ── -->
                            <div class="relative">
                                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                                </svg>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Hľadaj produkt podľa názvu alebo popisu..."
                                    class="w-full rounded-md border border-gray-200 py-2 pl-9 pr-9 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                    @input="onSearchInput"
                                />
                                <button v-if="searchQuery" @click="clearSearch"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- ── Assigned filter toggle ── -->
                            <div v-if="searchResults.length > 0" class="flex items-center gap-1">
                                <span class="text-xs text-gray-400 mr-1">Zobraziť:</span>
                                <button
                                    v-for="opt in assignedFilterOptions" :key="opt.value"
                                    @click="assignedFilter = opt.value"
                                    class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                                    :class="assignedFilter === opt.value
                                        ? 'bg-indigo-100 text-indigo-700'
                                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Results area -->
                        <div class="flex-1 overflow-y-auto" @click="closeCategoryMenu">
                            <!-- Loading -->
                            <div v-if="isSearching" class="flex items-center justify-center py-12">
                                <svg class="h-6 w-6 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                            </div>

                            <!-- Placeholder -->
                            <div v-else-if="!searchQuery && !showingAllInCategory"
                                 class="flex flex-col items-center justify-center py-16 text-gray-300">
                                <svg class="h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                                </svg>
                                <p class="text-sm">Zadaj názov alebo popis produktu</p>
                            </div>

                            <!-- No results -->
                            <div v-else-if="displayedResults.length === 0 && !isSearching"
                                 class="flex flex-col items-center justify-center py-16 text-gray-300">
                                <p v-if="searchResults.length === 0" class="text-sm">
                                    <template v-if="showingAllInCategory">Žiadne produkty v tejto kategórii.</template>
                                    <template v-else>Žiadne produkty nenájdené pre „{{ searchQuery }}"</template>
                                </p>
                                <p v-else class="text-sm">Žiadne {{ assignedFilter === 'assigned' ? 'priradené' : 'nepriradené' }} produkty v týchto výsledkoch</p>
                            </div>

                            <!-- Results grid -->
                            <div v-else class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 lg:grid-cols-4">
                                <div
                                    v-for="product in displayedResults"
                                    :key="product.product_id"
                                    class="group relative flex flex-col rounded-lg border-2 transition-all overflow-hidden"
                                    :class="selectedIds.has(product.product_id)
                                        ? 'border-indigo-500 bg-indigo-50 shadow-md'
                                        : 'border-gray-100 bg-white hover:border-gray-300 hover:shadow-sm'"
                                >
                                    <!-- Checkbox (klik = priradiť) -->
                                    <div
                                        class="absolute top-2 right-2 z-10 cursor-pointer"
                                        @click="toggleProduct(product.product_id)"
                                        title="Priradiť / odpriradiť"
                                    >
                                        <div class="flex h-5 w-5 items-center justify-center rounded-full border-2 transition-colors"
                                             :class="selectedIds.has(product.product_id)
                                                 ? 'border-indigo-500 bg-indigo-500'
                                                 : 'border-gray-300 bg-white'">
                                            <svg v-if="selectedIds.has(product.product_id)"
                                                 class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24"
                                                 stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- Obrázok (klik = modal) -->
                                    <div class="aspect-square overflow-hidden bg-gray-50 cursor-pointer" @click="openModal(product)">
                                        <img v-if="product.image" :src="product.image" :alt="product.name"
                                             class="h-full w-full object-cover transition-transform group-hover:scale-105"
                                             @error="$event.target.style.display='none'" />
                                        <div v-else class="flex h-full items-center justify-center">
                                            <svg class="h-8 w-8 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- Info + text -->
                                    <div class="flex flex-col p-2 flex-1">
                                        <p class="text-xs font-medium text-gray-800 leading-tight line-clamp-2 cursor-pointer"
                                           v-html="highlight(product.name)"
                                           @click="openModal(product)"></p>
                                        <p v-if="product.description"
                                           class="mt-1 text-xs text-gray-400 leading-tight line-clamp-2 cursor-pointer"
                                           v-html="highlight(product.description)"
                                           @click="openModal(product)"></p>
                                        <!-- Detail link -->
                                        <button
                                            class="mt-1.5 self-start flex items-center gap-1 text-xs text-indigo-400 hover:text-indigo-600 transition-colors"
                                            @click.stop="openModal(product)"
                                        >
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            detail
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="searchResults.length > 0"
                             class="flex-shrink-0 border-t border-gray-100 px-4 py-2 text-xs text-gray-400 flex items-center gap-2">
                            <span>{{ displayedResults.length }} z {{ searchResults.length }} výsledkov</span>
                            <span v-if="searchResults.length >= 40" class="text-gray-300">(max. {{ showingAllInCategory ? 120 : 40 }})</span>
                        </div>

                        </div><!-- end products tab -->

                        <!-- ══ Tab: Kategórie ═════════════════════════════════════ -->
                        <div v-show="activeTab === 'categories'" class="flex-1 flex flex-col overflow-hidden">

                            <!-- Add category typeahead -->
                            <div class="flex-shrink-0 border-b border-gray-100 px-4 py-3" ref="catAssignWrapperRef">
                                <label class="mb-1.5 block text-xs font-medium text-gray-500">Pridať kategóriu</label>
                                <div class="relative">
                                    <div
                                        class="flex w-full cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors"
                                        :class="showCatAssignMenu
                                            ? 'border-indigo-400 ring-1 ring-indigo-400 bg-white'
                                            : 'border-gray-200 bg-white hover:border-gray-300'"
                                        @click="openCatAssignMenu"
                                    >
                                        <svg class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        <input
                                            v-if="showCatAssignMenu"
                                            ref="catAssignInputRef"
                                            v-model="catAssignQuery"
                                            type="text"
                                            placeholder="Hľadaj kategóriu..."
                                            class="flex-1 bg-transparent outline-none placeholder-gray-400 text-gray-700 text-sm"
                                            @keydown.enter.prevent="addFirstFilteredCategory"
                                            @click.stop
                                        />
                                        <span v-else class="flex-1 text-gray-400 text-sm">Vybrať kategóriu...</span>
                                        <svg class="h-4 w-4 flex-shrink-0 text-gray-400 transition-transform"
                                             :class="{ 'rotate-180': showCatAssignMenu }"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>

                                    <!-- Dropdown -->
                                    <div v-if="showCatAssignMenu"
                                         class="absolute left-0 right-0 top-full z-30 mt-1 max-h-56 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg">
                                        <div v-if="filteredCatAssign.length === 0"
                                             class="px-3 py-4 text-sm text-center text-gray-400 italic">
                                            {{ catAssignQuery ? 'Žiadne zhody' : 'Všetky kategórie sú priradené' }}
                                        </div>
                                        <div v-for="cat in filteredCatAssign"
                                             :key="cat.category_id"
                                             @click="addCategory(cat)"
                                             class="flex items-center cursor-pointer hover:bg-indigo-50 transition-colors"
                                             :style="{ paddingLeft: (12 + cat.depth * 14) + 'px',
                                                       paddingRight: '12px',
                                                       paddingTop: cat.depth === 0 ? '8px' : '6px',
                                                       paddingBottom: cat.depth === 0 ? '8px' : '6px' }"
                                        >
                                            <span v-if="cat.depth > 0" class="mr-1.5 text-gray-300 text-xs select-none">
                                                {{ '└' + '─'.repeat(cat.depth - 1) }}
                                            </span>
                                            <span class="text-sm truncate"
                                                  :class="cat.depth === 0 ? 'font-semibold text-gray-700' : 'text-gray-600'"
                                                  v-html="highlightCatAssign(cat.name)">
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Assigned categories list -->
                            <div class="flex-1 overflow-y-auto">
                                <div v-if="assignedCategoriesList.length === 0"
                                     class="flex flex-col items-center justify-center py-12 text-gray-300">
                                    <svg class="h-10 w-10 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    <p class="text-sm">Žiadne kategórie</p>
                                </div>

                                <div v-for="cat in assignedCategoriesList" :key="cat.category_id"
                                     class="flex items-center gap-2 border-b border-gray-50 px-4 py-2.5 hover:bg-gray-50 transition-colors">
                                    <!-- Depth indent -->
                                    <div class="flex-1 flex items-center gap-1 min-w-0">
                                        <span v-if="cat.depth > 0" class="flex-shrink-0 text-gray-300 text-xs select-none">
                                            {{ '└' + '─'.repeat(cat.depth - 1) }}
                                        </span>
                                        <span class="truncate text-sm"
                                              :class="cat.depth === 0 ? 'font-semibold text-gray-800' : 'text-gray-600'">
                                            {{ cat.name }}
                                        </span>
                                    </div>
                                    <button @click="removeCategory(cat.category_id)"
                                            class="flex-shrink-0 rounded p-1 text-gray-300 hover:bg-red-50 hover:text-red-400 transition-colors"
                                            title="Odstrániť">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Save categories bar -->
                            <div class="flex-shrink-0 border-t border-gray-100 bg-gray-50 px-4 py-3 flex items-center justify-between">
                                <span class="text-sm text-gray-500">
                                    <strong class="text-gray-800">{{ assignedCategoryIds.size }}</strong> kategórií
                                    <span v-if="categoryHasChanges" class="ml-2 text-amber-500 font-medium">● neuložené zmeny</span>
                                </span>
                                <button
                                    @click="saveCategoryAssignments"
                                    :disabled="isSavingCategories || !categoryHasChanges"
                                    class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors"
                                    :class="categoryHasChanges && !isSavingCategories
                                        ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                                >
                                    <svg v-if="isSavingCategories" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    {{ isSavingCategories ? 'Ukladám...' : 'Uložiť kategórie' }}
                                </button>
                            </div>

                        </div><!-- end categories tab -->

                    </div><!-- end right panel -->
                </div>

                <!-- ═══ Product detail modal ══════════════════════════════════ -->
                <Teleport to="body">
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0"
                        enter-to-class="opacity-100"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0"
                    >
                        <div v-if="modalProduct"
                             class="fixed inset-0 z-50 flex items-center justify-center p-4"
                             @click.self="closeModal">
                            <!-- Backdrop -->
                            <div class="absolute inset-0 bg-black/50" @click="closeModal"></div>

                            <!-- Modal panel -->
                            <div class="relative z-10 flex max-h-[85vh] w-full max-w-2xl flex-col rounded-xl bg-white shadow-2xl overflow-hidden">
                                <!-- Header -->
                                <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-6 py-4">
                                    <h3 class="text-base font-semibold text-gray-900 leading-snug">{{ modalProduct.name }}</h3>
                                    <button @click="closeModal"
                                            class="flex-shrink-0 rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Body -->
                                <div class="flex-1 overflow-y-auto">
                                    <div class="flex gap-5 p-6">
                                        <!-- Image -->
                                        <div class="flex-shrink-0">
                                            <div class="h-40 w-40 overflow-hidden rounded-lg border border-gray-100 bg-gray-50">
                                                <img v-if="modalProduct.image"
                                                     :src="modalProduct.image"
                                                     :alt="modalProduct.name"
                                                     class="h-full w-full object-contain"
                                                     @error="$event.target.style.display='none'" />
                                                <div v-else class="flex h-full items-center justify-center text-gray-200">
                                                    <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Description -->
                                        <div class="flex-1 min-w-0">
                                            <div v-if="modalProduct.description_html"
                                                 class="prose prose-sm max-w-none text-gray-600 leading-relaxed whitespace-pre-line"
                                                 v-html="modalProduct.description_html">
                                            </div>
                                            <p v-else class="text-sm text-gray-400 italic">Žiadny popis</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="flex items-center justify-between border-t border-gray-100 px-6 py-3 bg-gray-50">
                                    <span class="text-xs text-gray-400">ID: {{ modalProduct.product_id }}</span>
                                    <div class="flex items-center gap-3">
                                        <button
                                            @click="toggleProduct(modalProduct.product_id); closeModal()"
                                            class="rounded-md px-4 py-2 text-sm font-medium transition-colors"
                                            :class="selectedIds.has(modalProduct.product_id)
                                                ? 'bg-red-50 text-red-600 hover:bg-red-100'
                                                : 'bg-indigo-50 text-indigo-600 hover:bg-indigo-100'"
                                        >
                                            {{ selectedIds.has(modalProduct.product_id) ? 'Odpriradiť' : 'Priradiť' }}
                                        </button>
                                        <a :href="'https://titi.shopweb.sk/produkt/detail/' + modalProduct.product_id"
                                           target="_blank" rel="noopener noreferrer"
                                           class="inline-flex items-center gap-1.5 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 transition-colors">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                            Otvoriť na eshope
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </Teleport>

                <!-- Save bar -->
                <div class="mt-4 flex items-center justify-between rounded-lg bg-white px-6 py-3 shadow">
                    <p class="text-sm text-gray-500">
                        Vybraných: <strong class="text-gray-800">{{ selectedIds.size }}</strong> produktov
                        <span v-if="hasUnsavedChanges" class="ml-2 text-amber-500 font-medium">
                            ●
                            <span v-if="changesSummary.toAdd.length && changesSummary.toRemove.length">
                                +{{ changesSummary.toAdd.length }} / −{{ changesSummary.toRemove.length }}
                            </span>
                            <span v-else-if="changesSummary.toAdd.length">+{{ changesSummary.toAdd.length }}</span>
                            <span v-else>−{{ changesSummary.toRemove.length }}</span>
                            neuložené
                        </span>
                    </p>
                    <button
                        @click="openConfirm"
                        :disabled="isSaving || !hasUnsavedChanges"
                        class="inline-flex items-center gap-2 rounded-md px-5 py-2 text-sm font-medium transition-colors"
                        :class="hasUnsavedChanges && !isSaving
                            ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                    >
                        <svg v-if="isSaving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        {{ isSaving ? 'Ukladám...' : 'Uložiť zmeny' }}
                    </button>
                </div>

                <!-- ═══ Confirm save modal ════════════════════════════════════ -->
                <Teleport to="body">
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div v-if="showConfirmModal"
                             class="fixed inset-0 z-50 flex items-center justify-center p-4">
                            <div class="absolute inset-0 bg-black/40" @click="showConfirmModal = false"></div>

                            <div class="relative z-10 flex max-h-[80vh] w-full max-w-xl flex-col rounded-xl bg-white shadow-2xl overflow-hidden">
                                <!-- Header -->
                                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Prehľad zmien</h3>
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            Skontroluj zmeny pred uložením
                                        </p>
                                    </div>
                                    <button @click="showConfirmModal = false"
                                            class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Body -->
                                <div class="flex-1 overflow-y-auto divide-y divide-gray-50">

                                    <!-- Products to ADD -->
                                    <div v-if="changesSummary.toAdd.length" class="px-6 py-4">
                                        <div class="mb-3 flex items-center gap-2">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-green-100">
                                                <svg class="h-3 w-3 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                                </svg>
                                            </span>
                                            <span class="text-sm font-medium text-green-700">
                                                Pridať {{ changesSummary.toAdd.length }} {{ changesSummary.toAdd.length === 1 ? 'produkt' : changesSummary.toAdd.length < 5 ? 'produkty' : 'produktov' }}
                                            </span>
                                        </div>
                                        <ul class="space-y-1.5">
                                            <li v-for="p in changesSummary.toAdd" :key="'add-' + p.product_id"
                                                class="flex items-center gap-2.5 rounded-lg bg-green-50 px-3 py-2">
                                                <img v-if="p.image" :src="p.image" :alt="p.name"
                                                     class="h-8 w-8 flex-shrink-0 rounded object-cover"
                                                     @error="$event.target.style.display='none'" />
                                                <div v-else class="h-8 w-8 flex-shrink-0 rounded bg-green-100"></div>
                                                <span class="text-xs text-gray-700 leading-tight line-clamp-2 flex-1">{{ p.name }}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <!-- Products to REMOVE -->
                                    <div v-if="changesSummary.toRemove.length" class="px-6 py-4">
                                        <div class="mb-3 flex items-center gap-2">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-red-100">
                                                <svg class="h-3 w-3 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/>
                                                </svg>
                                            </span>
                                            <span class="text-sm font-medium text-red-700">
                                                Odobrať {{ changesSummary.toRemove.length }} {{ changesSummary.toRemove.length === 1 ? 'produkt' : changesSummary.toRemove.length < 5 ? 'produkty' : 'produktov' }}
                                            </span>
                                        </div>
                                        <ul class="space-y-1.5">
                                            <li v-for="p in changesSummary.toRemove" :key="'rm-' + p.product_id"
                                                class="flex items-center gap-2.5 rounded-lg bg-red-50 px-3 py-2">
                                                <img v-if="p.image" :src="p.image" :alt="p.name"
                                                     class="h-8 w-8 flex-shrink-0 rounded object-cover"
                                                     @error="$event.target.style.display='none'" />
                                                <div v-else class="h-8 w-8 flex-shrink-0 rounded bg-red-100"></div>
                                                <span class="text-xs text-gray-700 leading-tight line-clamp-2 flex-1">{{ p.name }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
                                    <button @click="showConfirmModal = false"
                                            class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                        Zrušiť
                                    </button>
                                    <button @click="confirmAndSave"
                                            class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
                                        <svg v-if="isSaving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                        </svg>
                                        Potvrdiť a uložiť
                                    </button>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </Teleport>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';

const props = defineProps({
    filter:               Object,
    assignedProducts:     Array,
    categories:           Array,   // [{ category_id, name, depth }] — flat tree order
    assignedCategoryIds:  Array,   // [category_id, ...] from titi_category_filter
});

// ─── Tabs ─────────────────────────────────────────────────────────────────────
const activeTab = ref('products'); // 'products' | 'categories'

// ─── Product detail modal ─────────────────────────────────────────────────────

const modalProduct = ref(null);

function openModal(product) {
    modalProduct.value = product;
}

function closeModal() {
    modalProduct.value = null;
}

// ─── Confirm save modal ───────────────────────────────────────────────────────

const showConfirmModal = ref(false);

/** Computes which products will be added / removed compared to the saved state */
const changesSummary = computed(() => {
    const toAdd    = [];
    const toRemove = [];

    for (const id of selectedIds) {
        if (!originalIds.has(id)) {
            toAdd.push(knownProducts.get(id) ?? { product_id: id, name: `Produkt #${id}`, image: null });
        }
    }
    for (const id of originalIds) {
        if (!selectedIds.has(id)) {
            toRemove.push(knownProducts.get(id) ?? { product_id: id, name: `Produkt #${id}`, image: null });
        }
    }
    return { toAdd, toRemove };
});

function openConfirm() {
    if (!hasUnsavedChanges.value || isSaving.value) return;
    showConfirmModal.value = true;
}

async function confirmAndSave() {
    showConfirmModal.value = false;
    await doSave();
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        if (showConfirmModal.value)  { showConfirmModal.value = false; return; }
        if (modalProduct.value)      { closeModal(); return; }
        if (showCatAssignMenu.value) { closeCatAssignMenu(); return; }
        if (showCategoryMenu.value)  { closeCategoryMenu(); }
    }
}
onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));

// ─── Toast ────────────────────────────────────────────────────────────────────

const toast = reactive({ show: false, type: 'success', message: '' });
function showToast(msg, type = 'success') {
    Object.assign(toast, { show: true, type, message: msg });
    setTimeout(() => { toast.show = false; }, 3000);
}

// ─── Category typeahead ───────────────────────────────────────────────────────

const selectedCategoryId  = ref(0);
const categoryQuery       = ref('');
const showCategoryMenu    = ref(false);
const categoryWrapperRef  = ref(null);
const categoryInputRef    = ref(null);

// Flat list filtered by the typed query
const filteredCategories = computed(() => {
    const q = categoryQuery.value.trim().toLowerCase();
    if (!q) return props.categories;
    // When searching, flatten — show all matches regardless of depth
    return props.categories.filter(c => c.name.toLowerCase().includes(q));
});

const selectedCategoryName = computed(() =>
    props.categories.find(c => c.category_id === selectedCategoryId.value)?.name ?? ''
);

async function openCategoryMenu() {
    if (showCategoryMenu.value) return;
    categoryQuery.value  = '';
    showCategoryMenu.value = true;
    await nextTick();
    categoryInputRef.value?.focus();
}

function closeCategoryMenu() {
    showCategoryMenu.value = false;
    categoryQuery.value    = '';
}

function selectCategory(cat) {
    selectedCategoryId.value = cat ? cat.category_id : 0;
    closeCategoryMenu();
    onCategoryChange();
}

function clearCategory() {
    selectedCategoryId.value = 0;
    onCategoryChange();
}

function selectFirstFiltered() {
    if (filteredCategories.value.length > 0) {
        selectCategory(filteredCategories.value[0]);
    }
}

function highlightCat(text) {
    const q = categoryQuery.value.trim();
    if (!q || !text) return text;
    const re = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(re, '<mark class="bg-yellow-200 rounded">$1</mark>');
}

// Close dropdown when clicking outside the wrapper
function onDocumentClick(e) {
    if (categoryWrapperRef.value && !categoryWrapperRef.value.contains(e.target)) {
        closeCategoryMenu();
    }
}
onMounted(() => document.addEventListener('mousedown', onDocumentClick));
onUnmounted(() => document.removeEventListener('mousedown', onDocumentClick));
// (Escape is handled in the global onKeydown listener defined above)

// ─── Category → product IDs (for left panel filtering) ───────────────────────

const categoryProductIds = ref(null); // null = no filter; Set<number> = active filter
const isFetchingCategory = ref(false);

async function fetchCategoryProductIds(categoryId) {
    if (categoryId <= 0) {
        categoryProductIds.value = null;
        return;
    }
    isFetchingCategory.value = true;
    try {
        const res  = await fetch(route('category.product-ids') + '?category_id=' + categoryId);
        const ids  = await res.json();
        categoryProductIds.value = new Set(ids);
    } catch (e) {
        console.error('Failed to fetch category products', e);
        categoryProductIds.value = null;
    } finally {
        isFetchingCategory.value = false;
    }
}

// ─── Product state ────────────────────────────────────────────────────────────

const selectedIds   = reactive(new Set(props.assignedProducts.map(p => p.product_id)));
const knownProducts = reactive(new Map(props.assignedProducts.map(p => [p.product_id, p])));
const originalIds   = new Set(props.assignedProducts.map(p => p.product_id));

// ─── Category assignments (titi_category_filter) ─────────────────────────────

const assignedCategoryIds   = reactive(new Set(props.assignedCategoryIds ?? []));
const originalCategoryIds   = new Set(props.assignedCategoryIds ?? []);
const isSavingCategories     = ref(false);

const categoryHasChanges = computed(() => {
    if (assignedCategoryIds.size !== originalCategoryIds.size) return true;
    for (const id of assignedCategoryIds) { if (!originalCategoryIds.has(id)) return true; }
    return false;
});

// Flat list of assigned categories in tree order (for display)
const assignedCategoriesList = computed(() =>
    props.categories.filter(c => assignedCategoryIds.has(c.category_id))
);

// Category assignment typeahead refs
const catAssignQuery    = ref('');
const showCatAssignMenu = ref(false);
const catAssignWrapperRef = ref(null);
const catAssignInputRef   = ref(null);

const filteredCatAssign = computed(() => {
    const q = catAssignQuery.value.trim().toLowerCase();
    return props.categories.filter(c => {
        if (assignedCategoryIds.has(c.category_id)) return false; // already assigned
        return !q || c.name.toLowerCase().includes(q);
    });
});

function highlightCatAssign(text) {
    const q = catAssignQuery.value.trim();
    if (!q || !text) return text;
    const re = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(re, '<mark class="bg-yellow-200 rounded">$1</mark>');
}

async function openCatAssignMenu() {
    if (showCatAssignMenu.value) return;
    catAssignQuery.value   = '';
    showCatAssignMenu.value = true;
    await nextTick();
    catAssignInputRef.value?.focus();
}

function closeCatAssignMenu() {
    showCatAssignMenu.value = false;
    catAssignQuery.value    = '';
}

function addCategory(cat) {
    assignedCategoryIds.add(cat.category_id);
    closeCatAssignMenu();
}

function removeCategory(catId) {
    assignedCategoryIds.delete(catId);
}

function addFirstFilteredCategory() {
    if (filteredCatAssign.value.length > 0) addCategory(filteredCatAssign.value[0]);
}

// Click-outside for category assignment dropdown
function onDocumentClickCatAssign(e) {
    if (catAssignWrapperRef.value && !catAssignWrapperRef.value.contains(e.target)) {
        closeCatAssignMenu();
    }
}
onMounted(() => document.addEventListener('mousedown', onDocumentClickCatAssign));
onUnmounted(() => document.removeEventListener('mousedown', onDocumentClickCatAssign));

async function saveCategoryAssignments() {
    if (!categoryHasChanges.value || isSavingCategories.value) return;
    isSavingCategories.value = true;
    try {
        await axios.post(
            route('filters.sync-categories', props.filter.filter_id),
            { category_ids: [...assignedCategoryIds] },
            { headers: { 'X-Inertia': undefined } }
        );
        originalCategoryIds.clear();
        assignedCategoryIds.forEach(id => originalCategoryIds.add(id));
        assignedCategoryIds.add(-1); assignedCategoryIds.delete(-1); // nudge reactivity
        showToast('Kategórie boli úspešne uložené.');
    } catch (e) {
        console.error('Save categories failed', e);
        showToast('Nastala chyba pri ukladaní kategórií.', 'error');
    } finally {
        isSavingCategories.value = false;
    }
}

// ─── Search ───────────────────────────────────────────────────────────────────

const searchQuery          = ref('');
const searchResults        = ref([]);
const isSearching          = ref(false);
const isSaving             = ref(false);
const showingAllInCategory = ref(false);
let   searchTimer          = null;

// ─── Assigned filter ──────────────────────────────────────────────────────────

const assignedFilter = ref('all'); // 'all' | 'assigned' | 'unassigned'
const assignedFilterOptions = [
    { value: 'all',        label: 'Všetky' },
    { value: 'assigned',   label: 'Priradené' },
    { value: 'unassigned', label: 'Nepriradené' },
];

// ─── Computed ─────────────────────────────────────────────────────────────────

const hasUnsavedChanges = computed(() => {
    if (selectedIds.size !== originalIds.size) return true;
    for (const id of selectedIds) {
        if (!originalIds.has(id)) return true;
    }
    return false;
});

/** Products that are selected AND appear in search results (already category-filtered by server) */
const assignedInSearch = computed(() =>
    searchResults.value.filter(p => selectedIds.has(p.product_id))
);

/** Products that are selected, NOT in current search results, AND in active category (if any) */
const assignedNotInSearch = computed(() => {
    const searchIds = new Set(searchResults.value.map(p => p.product_id));
    return [...selectedIds]
        .filter(id => !searchIds.has(id))
        .filter(id => !categoryProductIds.value || categoryProductIds.value.has(id))
        .map(id => knownProducts.get(id))
        .filter(Boolean);
});

/** Count of assigned products hidden because they're not in the active category */
const hiddenAssignedCount = computed(() => {
    if (!categoryProductIds.value) return 0;
    const visibleInSearch = new Set(assignedInSearch.value.map(p => p.product_id));
    return [...selectedIds].filter(id =>
        !visibleInSearch.has(id) && !categoryProductIds.value.has(id)
    ).length;
});

/** Total visible assigned products count (those shown in the left panel) */
const visibleAssignedCount = computed(() =>
    assignedInSearch.value.length + assignedNotInSearch.value.length
);

/** Search results filtered by the assigned toggle */
const displayedResults = computed(() => {
    if (assignedFilter.value === 'assigned')   return searchResults.value.filter(p => selectedIds.has(p.product_id));
    if (assignedFilter.value === 'unassigned') return searchResults.value.filter(p => !selectedIds.has(p.product_id));
    return searchResults.value;
});

// ─── Actions ──────────────────────────────────────────────────────────────────

function toggleProduct(productId) {
    if (selectedIds.has(productId)) {
        selectedIds.delete(productId);
    } else {
        selectedIds.add(productId);
        const fromSearch = searchResults.value.find(p => p.product_id === productId);
        if (fromSearch) knownProducts.set(productId, fromSearch);
    }
}

function onSearchInput() {
    showingAllInCategory.value = false;
    clearTimeout(searchTimer);
    if (!searchQuery.value.trim()) {
        searchResults.value = [];
        return;
    }
    searchTimer = setTimeout(doSearch, 300);
}

function showAllInCategory() {
    searchQuery.value          = '';
    showingAllInCategory.value = true;
    clearTimeout(searchTimer);
    doSearch();
}

async function onCategoryChange() {
    await fetchCategoryProductIds(selectedCategoryId.value);
    showingAllInCategory.value = false;
    searchResults.value = [];
    if (searchQuery.value.trim()) {
        clearTimeout(searchTimer);
        await doSearch();
    }
}

async function doSearch() {
    isSearching.value = true;
    try {
        const params = new URLSearchParams({ q: searchQuery.value });
        if (selectedCategoryId.value > 0) params.set('category_id', selectedCategoryId.value);
        const res  = await fetch(route('filters.search', props.filter.filter_id) + '?' + params);
        const data = await res.json();
        searchResults.value = data;
        data.forEach(p => knownProducts.set(p.product_id, p));
    } catch (e) {
        console.error('Search failed', e);
    } finally {
        isSearching.value = false;
    }
}

function clearSearch() {
    searchQuery.value          = '';
    searchResults.value        = [];
    showingAllInCategory.value = false;
}

function highlight(text) {
    const q = searchQuery.value.trim();
    if (!q || !text) return text;
    const re = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return String(text).replace(re, '<mark class="bg-yellow-200 rounded px-0.5">$1</mark>');
}

async function doSave() {
    isSaving.value = true;
    try {
        const ids = [...selectedIds];
        await axios.post(
            route('filters.sync', props.filter.filter_id),
            { product_ids: ids },
            { headers: { 'X-Inertia': undefined } }
        );
        originalIds.clear();
        ids.forEach(id => originalIds.add(id));
        // Nudge Vue reactivity so hasUnsavedChanges recomputes
        selectedIds.add(-1); selectedIds.delete(-1);
        showToast('Produkty boli úspešne uložené.');
    } catch (e) {
        console.error('Save failed', e);
        showToast('Nastala chyba pri ukladaní.', 'error');
    } finally {
        isSaving.value = false;
    }
}
</script>
