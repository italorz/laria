<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Home, LogOut, Plus, Search, Sparkles, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

defineProps<{ title?: string }>();

const page = usePage();
const user = computed(() => page.props.auth.user as { id: number; name: string; avatar_url?: string | null });
const flash = computed(() => (page.props.flash as { status?: string } | undefined)?.status);

// Config global de IA — visível apenas para o admin (e-mail italorzuliani*).
const ai = computed(
    () =>
        page.props.ai as {
            canManage: boolean;
            provider?: string;
            options?: { value: string; label: string }[];
        },
);
const savingProvider = ref(false);

function changeProvider(event: Event) {
    const provider = (event.target as HTMLSelectElement).value;
    savingProvider.value = true;
    router.put(
        '/admin/ai-provider',
        { provider },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (savingProvider.value = false),
        },
    );
}

// Snackbar de feedback (flash de sessão).
const snackbar = ref<string | null>(null);
watch(
    flash,
    (value) => {
        if (!value) return;
        snackbar.value = value;
        setTimeout(() => (snackbar.value = null), 4000);
    },
    { immediate: true },
);

const tabs = computed(() => [
    { label: 'Início', href: '/feed', icon: Home, active: page.url.startsWith('/feed') },
    { label: 'Buscar', href: '/buscar', icon: Search, active: page.url.startsWith('/buscar') },
    { label: 'Criar', href: '/criar', icon: Plus, active: page.url.startsWith('/criar') },
    {
        label: 'Perfil',
        href: `/perfil/${user.value?.id}`,
        icon: User,
        active: page.url.startsWith('/perfil'),
    },
]);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-lavender-bg text-[#2A2233]">
        <!-- Cabeçalho -->
        <header class="sticky top-0 z-30 border-b border-black/5 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-14 max-w-xl items-center justify-between px-4">
                <Link href="/feed" class="text-xl font-extrabold tracking-tight text-lavender-deep">
                    Laria
                </Link>
                <div class="flex items-center gap-1">
                    <!-- Combobox do provider de IA (somente admin) -->
                    <label
                        v-if="ai.canManage"
                        class="mr-1 flex items-center gap-1.5 rounded-xl bg-lavender-tint px-2.5 py-1.5"
                        title="IA usada na geração de imagens (configuração global)"
                    >
                        <Sparkles class="h-4 w-4 shrink-0 text-lavender-deep" />
                        <select
                            :value="ai.provider"
                            class="max-w-[140px] cursor-pointer border-0 bg-transparent p-0 text-xs font-semibold text-lavender-deep outline-none disabled:opacity-50 sm:max-w-none"
                            :disabled="savingProvider"
                            @change="changeProvider"
                        >
                            <option v-for="option in ai.options" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <span class="mr-1 hidden text-sm text-black/60 sm:inline">{{ user?.name }}</span>
                    <button
                        type="button"
                        class="rounded-full p-2 text-black/50 transition hover:bg-lavender-tint hover:text-lavender-deep"
                        title="Sair"
                        @click="logout"
                    >
                        <LogOut class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </header>

        <!-- Conteúdo -->
        <main class="mx-auto w-full max-w-xl px-0 pb-24 sm:px-4">
            <h1 v-if="title" class="px-4 pt-4 text-lg font-bold sm:px-0">{{ title }}</h1>
            <slot />
        </main>

        <!-- Navegação inferior -->
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-black/5 bg-white">
            <div class="mx-auto flex max-w-xl items-stretch justify-around">
                <Link
                    v-for="tab in tabs"
                    :key="tab.label"
                    :href="tab.href"
                    class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] font-semibold transition"
                    :class="tab.active ? 'text-lavender-deep' : 'text-black/40 hover:text-black/60'"
                >
                    <span
                        class="rounded-full px-4 py-1 transition"
                        :class="tab.active ? 'bg-lavender/20' : ''"
                    >
                        <component :is="tab.icon" class="h-5 w-5" />
                    </span>
                    {{ tab.label }}
                </Link>
            </div>
        </nav>

        <!-- Snackbar -->
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="translate-y-4 opacity-0"
            leave-active-class="transition duration-200"
            leave-to-class="translate-y-4 opacity-0"
        >
            <div
                v-if="snackbar"
                class="fixed bottom-20 left-1/2 z-40 -translate-x-1/2 rounded-xl bg-[#2A2233] px-4 py-2.5 text-sm text-white shadow-lg"
            >
                {{ snackbar }}
            </div>
        </Transition>
    </div>
</template>
