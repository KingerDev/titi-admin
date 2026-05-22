<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WysiwygEditor from '@/Components/WysiwygEditor.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    campaign: Object,
});

const isEdit = computed(() => !!props.campaign);
const page   = usePage();

// ── Toast ────────────────────────────────────────────────────────────────────

const toast = ref({ show: false, message: '', type: 'success' });
function showToast(message, type = 'success') {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 4000);
}
if (page.props.flash?.success) showToast(page.props.flash.success);

// ── Campaign form ────────────────────────────────────────────────────────────

const form = useForm({
    name:              props.campaign?.name              ?? '',
    title:             props.campaign?.title             ?? '',
    image:             props.campaign?.image             ?? '',
    short_description: props.campaign?.short_description ?? '',
    long_description:  props.campaign?.long_description  ?? '',
    action_url:        props.campaign?.action_url        ?? '',
    status:            props.campaign?.status            ?? 'draft',
    starts_at:         props.campaign?.starts_at         ?? '',
    expires_at:        props.campaign?.expires_at        ?? '',
});

function save() {
    if (isEdit.value) {
        form.put(route('campaigns.update', props.campaign.id), {
            onSuccess: () => showToast('Kampaň bola uložená.'),
            onError:   () => showToast('Skontrolujte chyby vo formulári.', 'error'),
        });
    } else {
        form.post(route('campaigns.store'), {
            onError: () => showToast('Skontrolujte chyby vo formulári.', 'error'),
        });
    }
}

function deleteCampaign() {
    if (!confirm('Zmazať kampaň a všetky jej push notifikácie?')) return;
    router.delete(route('campaigns.destroy', props.campaign.id));
}

// ── action_url split ─────────────────────────────────────────────────────────

const SCHEME = 'titiapp://';
const actionPath = ref(
    props.campaign?.action_url?.startsWith(SCHEME)
        ? props.campaign.action_url.slice(SCHEME.length)
        : (props.campaign?.action_url ?? '')
);
watch(actionPath, (v) => { form.action_url = v ? SCHEME + v : ''; });

function useCampaignLink() {
    actionPath.value = `ucet/centrum-upozorneni/${props.campaign.id}`;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

const statusLabel = { draft: 'Draft', testing: 'Testovanie', active: 'Aktívna' };
const statusClass  = { draft: 'bg-gray-100 text-gray-600', testing: 'bg-amber-100 text-amber-700', active: 'bg-green-100 text-green-700' };
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a :href="route('campaigns.index')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </a>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ isEdit ? campaign.name : 'Nová kampaň' }}
                    </h2>
                    <span v-if="campaign?.status"
                          :class="statusClass[campaign.status]"
                          class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                        {{ statusLabel[campaign.status] }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="isEdit"
                        @click="deleteCampaign"
                        class="rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors"
                    >
                        Zmazať
                    </button>
                    <button
                        @click="save"
                        :disabled="form.processing"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
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
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Basic info -->
                <div class="rounded-xl bg-white p-6 shadow space-y-4">
                    <h3 class="text-base font-semibold text-gray-900">Základné informácie</h3>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Interný názov <span class="text-red-500">*</span></label>
                        <input v-model="form.name" type="text" placeholder="napr. Späť do školy 2025"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nadpis notifikácie <span class="text-red-500">*</span></label>
                        <input v-model="form.title" type="text" placeholder="napr. Späť do školy"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Krátky popis (text push notifikácie) <span class="text-red-500">*</span></label>
                        <textarea v-model="form.short_description" rows="2" placeholder="napr. Nová kolekcia školských pomôcok je tu!"
                                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                        <p v-if="form.errors.short_description" class="mt-1 text-xs text-red-500">{{ form.errors.short_description }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dlhý popis (obsah detailu)</label>
                        <WysiwygEditor v-model="form.long_description"/>
                    </div>
                </div>

                <!-- Media & link -->
                <div class="rounded-xl bg-white p-6 shadow space-y-4">
                    <h3 class="text-base font-semibold text-gray-900">Médiá a odkaz</h3>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL obrázka</label>
                        <input v-model="form.image" type="url" placeholder="https://..."
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                        <img v-if="form.image" :src="form.image" class="mt-2 h-24 w-auto rounded-lg object-cover"/>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-700">Odkaz tlačidla v detaile</label>
                            <button v-if="isEdit" type="button" @click="useCampaignLink"
                                    class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 transition-colors">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                </svg>
                                Použiť odkaz na túto kampaň
                            </button>
                        </div>
                        <div class="flex rounded-lg border border-gray-200 overflow-hidden focus-within:border-indigo-400 focus-within:ring-1 focus-within:ring-indigo-400">
                            <span class="flex items-center bg-gray-100 px-3 text-sm font-mono text-gray-500 border-r border-gray-200 whitespace-nowrap select-none">titiapp://</span>
                            <input v-model="actionPath" type="text" placeholder="titi-predajne"
                                   class="flex-1 px-3 py-2 text-sm font-mono focus:outline-none"/>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Kam sa presmeruje používateľ po kliknutí na tlačidlo v detaile kampane.</p>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Stav kampane</label>
                        <div class="grid grid-cols-3 gap-2">
                            <label v-for="opt in [
                                { value: 'draft',   label: 'Draft',          desc: 'Neviditeľná',            icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' },
                                { value: 'testing', label: 'Testovanie',     desc: 'Len testeri',            icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
                                { value: 'active',  label: 'Aktívna',        desc: 'Viditeľná všetkým',      icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' },
                            ]" :key="opt.value"
                                :class="form.status === opt.value
                                    ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500'
                                    : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                                class="relative flex flex-col items-center gap-1.5 rounded-lg border p-3 cursor-pointer transition-colors text-center">
                                <input v-model="form.status" type="radio" :value="opt.value" class="sr-only"/>
                                <svg class="h-5 w-5" :class="form.status === opt.value ? 'text-indigo-600' : 'text-gray-400'"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="opt.icon"/>
                                </svg>
                                <span class="text-xs font-semibold" :class="form.status === opt.value ? 'text-indigo-700' : 'text-gray-700'">{{ opt.label }}</span>
                                <span class="text-xs" :class="form.status === opt.value ? 'text-indigo-500' : 'text-gray-400'">{{ opt.desc }}</span>
                            </label>
                        </div>
                        <p v-if="form.errors.status" class="mt-1 text-xs text-red-500">{{ form.errors.status }}</p>
                    </div>

                    <!-- Dates -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Aktívna od
                                <span v-if="form.status !== 'active'" class="text-gray-400 font-normal">(len pre Aktívna)</span>
                            </label>
                            <input v-model="form.starts_at" type="datetime-local"
                                   :disabled="form.status !== 'active'"
                                   class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:opacity-40 disabled:cursor-not-allowed"/>
                            <p class="mt-1 text-xs text-gray-400">Kedy sa zobrazí všetkým v notif. centre.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Aktívna do</label>
                            <input v-model="form.expires_at" type="datetime-local"
                                   class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"/>
                            <p class="mt-1 text-xs text-gray-400">Po tomto dátume zmizne z notif. centra.</p>
                        </div>
                    </div>
                </div>

                <!-- Bottom save -->
                <div class="flex justify-end pb-8">
                    <button @click="save" :disabled="form.processing"
                            class="rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition-colors disabled:opacity-50">
                        Uložiť
                    </button>
                </div>

            </div>
        </div>

    </AuthenticatedLayout>
</template>
