<script setup lang="ts">
import PostCard from '@/components/laria/PostCard.vue';
import type { Post } from '@/lib/laria';
import { apiFetch } from '@/lib/laria';
import LariaLayout from '@/layouts/LariaLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Camera, Loader2 } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    posts: Post[];
    nextCursor: string | null;
}>();

const posts = ref<Post[]>([...props.posts]);
const cursor = ref(props.nextCursor);
const loadingMore = ref(false);
const sentinel = ref<HTMLElement | null>(null);

// Like otimista: atualiza na hora e reverte se a chamada falhar.
async function toggleLike(post: Post) {
    const index = posts.value.findIndex((p) => p.id === post.id);
    if (index < 0) return;
    const previous = { liked: post.liked, likeCount: post.likeCount };
    posts.value[index] = {
        ...post,
        liked: !post.liked,
        likeCount: post.likeCount + (post.liked ? -1 : 1),
    };
    try {
        const res = await apiFetch<{ liked: boolean; likeCount: number }>(
            `/posts/${post.id}/like`,
            { method: 'POST' },
        );
        posts.value[index] = { ...posts.value[index], ...res };
    } catch {
        posts.value[index] = { ...posts.value[index], ...previous };
    }
}

// Scroll infinito por cursor.
async function loadMore() {
    if (loadingMore.value || !cursor.value) return;
    loadingMore.value = true;
    try {
        const res = await apiFetch<{ posts: Post[]; nextCursor: string | null }>(
            `/feed?cursor=${encodeURIComponent(cursor.value)}`,
        );
        posts.value.push(...res.posts);
        cursor.value = res.nextCursor;
    } finally {
        loadingMore.value = false;
    }
}

let observer: IntersectionObserver | null = null;
onMounted(() => {
    observer = new IntersectionObserver((entries) => {
        if (entries[0]?.isIntersecting) loadMore();
    });
    if (sentinel.value) observer.observe(sentinel.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="Início" />
    <LariaLayout>
        <!-- Vazio -->
        <div v-if="!posts.length" class="flex flex-col items-center gap-3 pt-28 text-center">
            <Camera class="h-14 w-14 text-black/20" />
            <p class="text-black/55">
                Ainda não há publicações.<br />
                Crie a primeira na aba <strong>Criar</strong>!
            </p>
            <Link
                href="/criar"
                class="mt-2 rounded-2xl bg-lavender px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-lavender-deep"
            >
                Criar agora
            </Link>
        </div>

        <!-- Lista -->
        <template v-else>
            <PostCard
                v-for="post in posts"
                :key="post.id"
                :post="post"
                @like="toggleLike(post)"
            />
            <div ref="sentinel" class="flex justify-center py-4">
                <Loader2 v-if="loadingMore" class="h-6 w-6 animate-spin text-lavender-deep" />
            </div>
        </template>
    </LariaLayout>
</template>
