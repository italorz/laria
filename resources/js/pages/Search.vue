<script setup lang="ts">
import UserAvatar from '@/components/laria/UserAvatar.vue';
import LariaLayout from '@/layouts/LariaLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, SearchX } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    q: string;
    users: { id: number; displayName: string; avatarUrl: string | null; bio: string | null }[];
    posts: { id: string; imageUrl: string; caption: string | null; createdAt: string }[];
}>();

const query = ref(props.q);

// Busca com debounce, preservando o estado da página.
let timer: ReturnType<typeof setTimeout> | undefined;
watch(query, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/buscar', value.trim() ? { q: value.trim() } : {}, {
            preserveState: true,
            replace: true,
        });
    }, 350);
});
</script>

<template>
    <Head title="Buscar" />
    <LariaLayout>
        <div class="px-4 pt-4 sm:px-0">
            <div class="relative">
                <Search class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-black/35" />
                <input
                    v-model="query"
                    type="search"
                    placeholder="Buscar pessoas ou produtos…"
                    class="w-full rounded-2xl border-0 bg-white py-3.5 pl-11 pr-4 text-[15px] shadow-sm outline-none ring-lavender transition focus:ring-2"
                />
            </div>
        </div>

        <!-- Sem resultados -->
        <div
            v-if="q && !users.length && !posts.length"
            class="flex flex-col items-center gap-3 pt-20 text-center"
        >
            <SearchX class="h-12 w-12 text-black/20" />
            <p class="text-black/55">Nada encontrado para “{{ q }}”.</p>
        </div>

        <!-- Pessoas -->
        <section v-if="users.length" class="mx-3 mt-4 rounded-2xl bg-white p-2 shadow-sm sm:mx-0">
            <h2 class="px-2 pb-1 pt-2 font-bold">Pessoas</h2>
            <Link
                v-for="user in users"
                :key="user.id"
                :href="`/perfil/${user.id}`"
                class="flex items-center gap-3 rounded-xl p-2 transition hover:bg-lavender-tint"
            >
                <UserAvatar :src="user.avatarUrl" :alt="user.displayName" />
                <span class="min-w-0">
                    <span class="block truncate font-semibold">{{ user.displayName }}</span>
                    <span v-if="user.bio" class="block truncate text-sm text-black/50">{{ user.bio }}</span>
                </span>
            </Link>
        </section>

        <!-- Publicações -->
        <section v-if="posts.length" class="mt-4 px-3 sm:px-0">
            <h2 class="px-1 pb-2 font-bold">Publicações</h2>
            <div class="grid grid-cols-3 gap-0.5 sm:gap-1">
                <Link
                    v-for="post in posts"
                    :key="post.id"
                    :href="`/posts/${post.id}`"
                    class="relative aspect-square overflow-hidden rounded-sm bg-black/5"
                >
                    <img
                        :src="post.imageUrl"
                        :alt="post.caption ?? 'Publicação'"
                        class="h-full w-full object-cover"
                        loading="lazy"
                    />
                </Link>
            </div>
        </section>
    </LariaLayout>
</template>
