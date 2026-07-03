<script setup lang="ts">
import UserAvatar from '@/components/laria/UserAvatar.vue';
import type { Post } from '@/lib/laria';
import LariaLayout from '@/layouts/LariaLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Camera, Heart, ShoppingBag } from 'lucide-vue-next';

defineProps<{
    profile: {
        id: number;
        displayName: string;
        avatarUrl: string | null;
        bio: string | null;
        postCount: number;
        isMe: boolean;
    };
    posts: Post[];
}>();
</script>

<template>
    <Head :title="profile.displayName" />
    <LariaLayout>
        <!-- Cabeçalho do perfil -->
        <section class="flex flex-col items-center gap-2 px-4 pb-5 pt-8 text-center">
            <UserAvatar :src="profile.avatarUrl" :alt="profile.displayName" size="lg" />
            <h2 class="text-xl font-bold">{{ profile.displayName }}</h2>
            <p v-if="profile.bio" class="max-w-sm text-sm text-black/55">{{ profile.bio }}</p>
            <p class="text-sm text-black/45">
                <strong class="text-black/70">{{ profile.postCount }}</strong>
                {{ profile.postCount === 1 ? 'publicação' : 'publicações' }}
            </p>
        </section>

        <!-- Vazio -->
        <div v-if="!posts.length" class="flex flex-col items-center gap-3 pt-10 text-center">
            <Camera class="h-12 w-12 text-black/20" />
            <p class="text-black/55">
                {{ profile.isMe ? 'Você ainda não publicou nada.' : 'Nenhuma publicação ainda.' }}
            </p>
            <Link
                v-if="profile.isMe"
                href="/criar"
                class="mt-1 rounded-2xl bg-lavender px-6 py-3 font-semibold text-white transition hover:bg-lavender-deep"
            >
                Criar a primeira
            </Link>
        </div>

        <!-- Grade de posts -->
        <div v-else class="grid grid-cols-3 gap-0.5 sm:gap-1">
            <Link
                v-for="post in posts"
                :key="post.id"
                :href="`/posts/${post.id}`"
                class="group relative aspect-square overflow-hidden bg-black/5"
            >
                <img
                    :src="post.imageUrl"
                    :alt="post.caption ?? 'Publicação'"
                    class="h-full w-full object-cover transition group-hover:scale-105"
                    loading="lazy"
                />
                <span
                    class="absolute inset-0 flex items-center justify-center gap-3 bg-black/40 text-sm font-semibold text-white opacity-0 transition group-hover:opacity-100"
                >
                    <span class="flex items-center gap-1"><Heart class="h-4 w-4 fill-white" />{{ post.likeCount }}</span>
                    <span v-if="post.products.length" class="flex items-center gap-1">
                        <ShoppingBag class="h-4 w-4" />{{ post.products.length }}
                    </span>
                </span>
            </Link>
        </div>
    </LariaLayout>
</template>
