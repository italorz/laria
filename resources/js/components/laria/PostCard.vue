<script setup lang="ts">
import UserAvatar from '@/components/laria/UserAvatar.vue';
import type { Post } from '@/lib/laria';
import { Link } from '@inertiajs/vue3';
import { Heart, ShoppingBag } from 'lucide-vue-next';

defineProps<{ post: Post }>();
defineEmits<{ like: [] }>();
</script>

<template>
    <article class="mx-3 my-3 overflow-hidden rounded-2xl bg-white shadow-sm sm:mx-0">
        <!-- Autor -->
        <Link
            :href="`/perfil/${post.author.id}`"
            class="flex items-center gap-3 px-3 py-2.5"
        >
            <UserAvatar :src="post.author.avatarUrl" :alt="post.author.displayName" />
            <span class="font-bold">{{ post.author.displayName }}</span>
        </Link>

        <!-- Imagem -->
        <Link :href="`/posts/${post.id}`" class="relative block aspect-square bg-black/5">
            <img
                :src="post.imageUrl"
                :alt="post.caption ?? 'Publicação'"
                class="h-full w-full object-cover"
                loading="lazy"
            />
            <span
                v-if="post.products.length"
                class="absolute bottom-2.5 left-2.5 flex items-center gap-1 rounded-full bg-black/55 px-2.5 py-1 text-xs text-white"
            >
                <ShoppingBag class="h-3.5 w-3.5" />
                {{ post.products.length }}
            </span>
        </Link>

        <!-- Ações -->
        <div class="flex items-center gap-1.5 px-2 py-1">
            <button
                type="button"
                class="rounded-full p-2 transition hover:bg-lavender-tint active:scale-90"
                :aria-label="post.liked ? 'Descurtir' : 'Curtir'"
                @click="$emit('like')"
            >
                <Heart
                    class="h-6 w-6 transition"
                    :class="post.liked ? 'fill-pink-500 stroke-pink-500' : 'stroke-black/60'"
                />
            </button>
            <span class="text-sm font-medium">{{ post.likeCount }}</span>
        </div>

        <p v-if="post.caption" class="line-clamp-2 px-4 pb-3 text-[15px]">
            {{ post.caption }}
        </p>
    </article>
</template>
